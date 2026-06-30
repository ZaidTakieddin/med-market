<?php

namespace App\Domain\Products;

use App\Constants\DiscountType;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportError;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductImportService
{
    /**
     * Canonical sheet columns (heading row), in display order. Shared with the
     * sample export so the template can never drift from what the importer reads.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'name',
        'sku',
        'pharmacist_price',
        'description',
        'category',
        'manufacturer',
        'barcode',
        'unit',
        'wholesale_price',
        'wholesale_min_qty',
        'customer_price',
        'discount',
        'discount_type',
        'stock_quantity',
        'min_stock_level',
        'is_active',
    ];

    /** @var list<string> */
    public const UNITS = ['piece', 'box', 'bottle', 'pack', 'carton'];

    /**
     * Columns updated on an existing product when a SKU matches (upsert).
     * Deliberately EXCLUDES stock_quantity (rule #4 — stock is never overwritten
     * by import; it stays on the stock endpoints) and the identity columns.
     *
     * @var list<string>
     */
    private const UPSERT_UPDATE_COLUMNS = [
        'name',
        'description',
        'category_id',
        'manufacturer_id',
        'barcode',
        'unit',
        'price',
        'wholesale_price',
        'wholesale_min_qty',
        'pharmacist_price',
        'customer_price',
        'discount',
        'discount_type',
        'min_stock_level',
        'is_active',
        'updated_at',
    ];

    /** @var array<string, int>|null */
    private ?array $categoryMapCache = null;

    /** @var array<string, int>|null */
    private ?array $manufacturerMapCache = null;

    /**
     * Process one chunk of sheet rows: resolve references, validate, classify
     * create/update/error by SKU, bulk-write, and record counts + per-row errors.
     *
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @param  int  $chunkStartRow  Spreadsheet row number of the first row in this chunk.
     */
    public function processChunk(int $importId, int $companyId, Collection $rows, int $chunkStartRow): void
    {
        $errorRows = [];
        $failed = 0;

        /** @var array<string, int> $seenSkus sku => first row number within this chunk */
        $seenSkus = [];
        /** @var list<array{row: int, sku: string, attrs: array<string, mixed>}> $candidates */
        $candidates = [];

        foreach ($rows->values() as $i => $row) {
            $rowNumber = $chunkStartRow + (int) $i;
            $data = $row->toArray();

            if ($this->isBlankRow($data)) {
                continue;
            }

            [$attrs, $error] = $this->resolveAndValidate($data);

            if ($error !== null) {
                $errorRows[] = $this->errorRow($importId, $rowNumber, $this->str($data['sku'] ?? null), $error);
                $failed++;

                continue;
            }

            $sku = (string) $attrs['sku'];

            if (isset($seenSkus[$sku])) {
                $errorRows[] = $this->errorRow(
                    $importId,
                    $rowNumber,
                    $sku,
                    "Duplicate SKU '{$sku}' within the file (already used on row {$seenSkus[$sku]})."
                );
                $failed++;

                continue;
            }

            $seenSkus[$sku] = $rowNumber;
            $candidates[] = ['row' => $rowNumber, 'sku' => $sku, 'attrs' => $attrs];
        }

        [$creates, $updates, $classifyErrors, $classifyFailed] = $this->classify($importId, $companyId, $candidates, array_keys($seenSkus));
        $errorRows = array_merge($errorRows, $classifyErrors);
        $failed += $classifyFailed;

        [$created, $updated, $writeErrors] = $this->writeChunk($importId, $creates, $updates);
        $errorRows = array_merge($errorRows, $writeErrors);
        $failed += count($writeErrors);

        if ($errorRows !== []) {
            ProductImportError::insert($errorRows);
        }

        $this->bumpCounts($importId, $created, $updated, $failed);
    }

    /**
     * Classify validated candidates against existing products (including trashed).
     *
     * @param  list<array{row: int, sku: string, attrs: array<string, mixed>}>  $candidates
     * @param  list<string>  $skus
     * @return array{0: list<array{row: int, attrs: array<string, mixed>}>, 1: list<array{row: int, attrs: array<string, mixed>}>, 2: list<array<string, mixed>>, 3: int}
     */
    private function classify(int $importId, int $companyId, array $candidates, array $skus): array
    {
        $existing = $skus === []
            ? collect()
            : Product::withTrashed()
                ->whereIn('sku', $skus)
                ->get(['id', 'sku', 'supplier_company_id', 'deleted_at'])
                ->keyBy('sku');

        $creates = [];
        $updates = [];
        $errors = [];
        $failed = 0;

        foreach ($candidates as $candidate) {
            $match = $existing->get($candidate['sku']);
            $sku = $candidate['sku'];

            if ($match === null) {
                $creates[] = ['row' => $candidate['row'], 'attrs' => $this->insertRow($companyId, $candidate['attrs'])];

                continue;
            }

            if ($match->deleted_at !== null) {
                $errors[] = $this->errorRow($importId, $candidate['row'], $sku, "SKU '{$sku}' belongs to a deleted product. Restore it before re-importing.");
                $failed++;

                continue;
            }

            if ((int) $match->supplier_company_id !== $companyId) {
                $errors[] = $this->errorRow($importId, $candidate['row'], $sku, "SKU '{$sku}' already belongs to another supplier.");
                $failed++;

                continue;
            }

            $updates[] = ['row' => $candidate['row'], 'attrs' => $this->upsertRow($companyId, $candidate['attrs'])];
        }

        return [$creates, $updates, $errors, $failed];
    }

    /**
     * Bulk-write a chunk inside a transaction, with a per-row fallback if the
     * bulk statement hits a rare race (e.g. a concurrent unique violation).
     *
     * @param  list<array{row: int, attrs: array<string, mixed>}>  $creates
     * @param  list<array{row: int, attrs: array<string, mixed>}>  $updates
     * @return array{0: int, 1: int, 2: list<array<string, mixed>>}
     */
    private function writeChunk(int $importId, array $creates, array $updates): array
    {
        if ($creates === [] && $updates === []) {
            return [0, 0, []];
        }

        try {
            Product::withoutEvents(function () use ($creates, $updates): void {
                DB::transaction(function () use ($creates, $updates): void {
                    if ($creates !== []) {
                        Product::insert(array_column($creates, 'attrs'));
                    }
                    if ($updates !== []) {
                        Product::upsert(array_column($updates, 'attrs'), ['sku'], self::UPSERT_UPDATE_COLUMNS);
                    }
                });
            });

            return [count($creates), count($updates), []];
        } catch (\Throwable $e) {
            Log::warning('Product import chunk bulk write failed; retrying per-row', [
                'import_id' => $importId,
                'error' => $e->getMessage(),
            ]);

            return $this->writePerRow($importId, $creates, $updates);
        }
    }

    /**
     * @param  list<array{row: int, attrs: array<string, mixed>}>  $creates
     * @param  list<array{row: int, attrs: array<string, mixed>}>  $updates
     * @return array{0: int, 1: int, 2: list<array<string, mixed>>}
     */
    private function writePerRow(int $importId, array $creates, array $updates): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($creates as $create) {
            try {
                Product::withoutEvents(fn () => Product::insert([$create['attrs']]));
                $created++;
            } catch (\Throwable $e) {
                $errors[] = $this->errorRow($importId, $create['row'], (string) $create['attrs']['sku'], 'Could not import row: '.$e->getMessage());
            }
        }

        foreach ($updates as $update) {
            try {
                Product::withoutEvents(fn () => Product::upsert([$update['attrs']], ['sku'], self::UPSERT_UPDATE_COLUMNS));
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = $this->errorRow($importId, $update['row'], (string) $update['attrs']['sku'], 'Could not import row: '.$e->getMessage());
            }
        }

        return [$created, $updated, $errors];
    }

    /**
     * Resolve category/manufacturer names and validate a single row.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: string|null} [attributes, errorMessage|null]
     */
    private function resolveAndValidate(array $data): array
    {
        $errors = [];

        $categoryId = $this->resolveReference($this->str($data['category'] ?? null), $this->categoryMap(), 'category', $errors);
        $manufacturerId = $this->resolveReference($this->str($data['manufacturer'] ?? null), $this->manufacturerMap(), 'manufacturer', $errors);

        $attrs = [
            'name' => $this->str($data['name'] ?? null),
            'sku' => $this->str($data['sku'] ?? null),
            'description' => $this->str($data['description'] ?? null),
            'barcode' => $this->str($data['barcode'] ?? null),
            'unit' => $this->str($data['unit'] ?? null),
            'category_id' => $categoryId,
            'manufacturer_id' => $manufacturerId,
            'pharmacist_price' => $this->str($data['pharmacist_price'] ?? null),
            'wholesale_price' => $this->str($data['wholesale_price'] ?? null),
            'wholesale_min_qty' => $this->str($data['wholesale_min_qty'] ?? null),
            'customer_price' => $this->str($data['customer_price'] ?? null),
            'discount' => $this->str($data['discount'] ?? null),
            'discount_type' => $this->str($data['discount_type'] ?? null),
            'stock_quantity' => $this->str($data['stock_quantity'] ?? null),
            'min_stock_level' => $this->str($data['min_stock_level'] ?? null),
            'is_active' => $this->boolOrNull($data['is_active'] ?? null),
        ];

        $validator = Validator::make($attrs, $this->rules());

        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors()->all());
        }

        return $errors === [] ? [$attrs, null] : [$attrs, implode(' ', $errors)];
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $errors
     */
    private function resolveReference(?string $name, array $map, string $label, array &$errors): ?int
    {
        if ($name === null) {
            return null;
        }

        $key = mb_strtolower($name);

        if (! isset($map[$key])) {
            $errors[] = "Unknown {$label} '{$name}'.";

            return null;
        }

        return $map[$key];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', Rule::in(self::UNITS)],
            'pharmacist_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'wholesale_min_qty' => ['nullable', 'integer', 'min:1', 'required_with:wholesale_price'],
            'customer_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(DiscountType::all())],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'integer'],
            'manufacturer_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Build a full insert row (create). Sets initial stock directly, mirroring
     * the single-create endpoint; price mirrors pharmacist_price.
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function insertRow(int $companyId, array $attrs): array
    {
        $now = now()->toDateTimeString();
        $price = (float) $attrs['pharmacist_price'];

        return [
            'supplier_company_id' => $companyId,
            'name' => $attrs['name'],
            'description' => $attrs['description'],
            'category_id' => $attrs['category_id'],
            'manufacturer_id' => $attrs['manufacturer_id'],
            'sku' => $attrs['sku'],
            'barcode' => $attrs['barcode'],
            'unit' => $attrs['unit'] ?? 'piece',
            'price' => $price,
            'wholesale_price' => $this->floatOrNull($attrs['wholesale_price']),
            'wholesale_min_qty' => $this->intOrNull($attrs['wholesale_min_qty']),
            'pharmacist_price' => $price,
            'customer_price' => $this->floatOrNull($attrs['customer_price']),
            'discount' => $this->floatOrNull($attrs['discount']),
            'discount_type' => $attrs['discount_type'],
            'stock_quantity' => $this->intOrNull($attrs['stock_quantity']) ?? 0,
            'min_stock_level' => $this->intOrNull($attrs['min_stock_level']) ?? 0,
            'is_active' => $attrs['is_active'] ?? true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Build an upsert row (update path). Excludes stock_quantity and created_at;
     * stock is never overwritten by import (rule #4).
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function upsertRow(int $companyId, array $attrs): array
    {
        $price = (float) $attrs['pharmacist_price'];

        return [
            'supplier_company_id' => $companyId,
            'name' => $attrs['name'],
            'description' => $attrs['description'],
            'category_id' => $attrs['category_id'],
            'manufacturer_id' => $attrs['manufacturer_id'],
            'sku' => $attrs['sku'],
            'barcode' => $attrs['barcode'],
            'unit' => $attrs['unit'] ?? 'piece',
            'price' => $price,
            'wholesale_price' => $this->floatOrNull($attrs['wholesale_price']),
            'wholesale_min_qty' => $this->intOrNull($attrs['wholesale_min_qty']),
            'pharmacist_price' => $price,
            'customer_price' => $this->floatOrNull($attrs['customer_price']),
            'discount' => $this->floatOrNull($attrs['discount']),
            'discount_type' => $attrs['discount_type'],
            'min_stock_level' => $this->intOrNull($attrs['min_stock_level']) ?? 0,
            'is_active' => $attrs['is_active'] ?? true,
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    private function bumpCounts(int $importId, int $created, int $updated, int $failed): void
    {
        $import = ProductImport::find($importId);

        if ($import === null) {
            return;
        }

        if ($created > 0) {
            $import->increment('created_count', $created);
        }
        if ($updated > 0) {
            $import->increment('updated_count', $updated);
        }
        if ($failed > 0) {
            $import->increment('failed_count', $failed);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function errorRow(int $importId, int $rowNumber, ?string $sku, string $message): array
    {
        $now = now()->toDateTimeString();

        return [
            'product_import_id' => $importId,
            'row_number' => $rowNumber,
            'sku' => $sku,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function categoryMap(): array
    {
        return $this->categoryMapCache ??= Category::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Category $c): array => [mb_strtolower(trim((string) $c->name)) => (int) $c->id])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function manufacturerMap(): array
    {
        return $this->manufacturerMapCache ??= Manufacturer::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Manufacturer $m): array => [mb_strtolower(trim((string) $m->name)) => (int) $m->id])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isBlankRow(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function floatOrNull(mixed $value): ?float
    {
        return ($value === null || $value === '') ? null : (float) $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    private function boolOrNull(mixed $value): ?bool
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
