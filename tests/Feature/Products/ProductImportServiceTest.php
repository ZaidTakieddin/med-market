<?php

namespace Tests\Feature\Products;

use App\Constants\ProductImportStatus;
use App\Domain\Companies\SupplierTaxonomySyncService;
use App\Domain\Products\ProductImportService;
use App\Jobs\FinalizeProductImportJob;
use App\Models\Category;
use App\Models\Company;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductImportService
    {
        return app(ProductImportService::class);
    }

    public function test_processes_create_update_and_errors_with_correct_counts(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $category = Category::factory()->create(['name' => 'Painkillers']);
        $manufacturer = Manufacturer::factory()->create(['name' => 'Acme Pharma']);
        $import = ProductImport::factory()->create(['supplier_company_id' => $supplier->id]);

        $existing = Product::factory()->create([
            'supplier_company_id' => $supplier->id,
            'sku' => 'SKU-UPDATE',
            'name' => 'Old Name',
            'pharmacist_price' => 10,
            'stock_quantity' => 500,
        ]);

        $otherCompany = Company::factory()->supplier()->create();
        Product::factory()->create([
            'supplier_company_id' => $otherCompany->id,
            'sku' => 'SKU-OTHER',
        ]);

        $rows = $this->rows([
            ['name' => 'New Product', 'sku' => 'SKU-NEW', 'pharmacist_price' => '20', 'category' => 'Painkillers', 'manufacturer' => 'Acme Pharma', 'unit' => 'box', 'stock_quantity' => '80', 'is_active' => 'true'],
            ['name' => 'Updated Name', 'sku' => 'SKU-UPDATE', 'pharmacist_price' => '12', 'stock_quantity' => '999'],
            ['name' => 'Bad Category', 'sku' => 'SKU-BADCAT', 'pharmacist_price' => '5', 'category' => 'Nonexistent'],
            ['name' => 'Taken Elsewhere', 'sku' => 'SKU-OTHER', 'pharmacist_price' => '5'],
            ['name' => '', 'sku' => 'SKU-INVALID', 'pharmacist_price' => 'notanumber'],
        ]);

        $this->service()->processChunk($import->id, $supplier->id, $rows, 2);

        $import->refresh();
        $this->assertSame(1, $import->created_count);
        $this->assertSame(1, $import->updated_count);
        $this->assertSame(3, $import->failed_count);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-NEW',
            'supplier_company_id' => $supplier->id,
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'stock_quantity' => 80,
        ]);

        $existing->refresh();
        $this->assertSame('Updated Name', $existing->name);
        $this->assertEquals(12.0, (float) $existing->pharmacist_price);
        $this->assertSame(500, $existing->stock_quantity, 'Stock must NOT be overwritten on update.');

        $this->assertSame(3, $import->errors()->count());
        $this->assertDatabaseHas('product_import_errors', ['product_import_id' => $import->id, 'sku' => 'SKU-OTHER']);
        $this->assertDatabaseHas('product_import_errors', ['product_import_id' => $import->id, 'sku' => 'SKU-BADCAT']);
    }

    public function test_duplicate_sku_within_chunk_is_reported_once(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $import = ProductImport::factory()->create(['supplier_company_id' => $supplier->id]);

        $rows = $this->rows([
            ['name' => 'First', 'sku' => 'DUP-1', 'pharmacist_price' => '10'],
            ['name' => 'Second', 'sku' => 'DUP-1', 'pharmacist_price' => '11'],
        ]);

        $this->service()->processChunk($import->id, $supplier->id, $rows, 2);

        $import->refresh();
        $this->assertSame(1, $import->created_count);
        $this->assertSame(1, $import->failed_count);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_blank_rows_are_skipped_entirely(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $import = ProductImport::factory()->create(['supplier_company_id' => $supplier->id]);

        $rows = $this->rows([
            ['name' => '', 'sku' => '', 'pharmacist_price' => ''],
            ['name' => 'Real', 'sku' => 'REAL-1', 'pharmacist_price' => '9'],
        ]);

        $this->service()->processChunk($import->id, $supplier->id, $rows, 2);

        $import->refresh();
        $this->assertSame(1, $import->created_count);
        $this->assertSame(0, $import->failed_count);
    }

    public function test_finalize_runs_taxonomy_sync_and_sets_status(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $category = Category::factory()->create(['name' => 'Vitamins']);
        $import = ProductImport::factory()->create(['supplier_company_id' => $supplier->id]);

        $rows = $this->rows([
            ['name' => 'Vit D', 'sku' => 'VIT-D-1', 'pharmacist_price' => '7', 'category' => 'Vitamins'],
            ['name' => 'Broken', 'sku' => '', 'pharmacist_price' => 'x'],
        ]);

        $this->service()->processChunk($import->id, $supplier->id, $rows, 2);

        // The per-row observer is bypassed during import, so the supplier has no
        // categories until finalize runs the one-time sync.
        $this->assertSame(0, $supplier->categories()->count());

        (new FinalizeProductImportJob($import->id, $supplier->id))
            ->handle(app(SupplierTaxonomySyncService::class));

        $import->refresh();
        $this->assertSame(ProductImportStatus::COMPLETED_WITH_ERRORS, $import->status);
        $this->assertSame(2, $import->total_rows);
        $this->assertNotNull($import->completed_at);
        $this->assertTrue($supplier->categories()->where('categories.id', $category->id)->exists());
    }

    /**
     * Build a chunk shaped like maatwebsite's ToCollection output: a collection
     * of per-row collections keyed by heading.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, Collection<string, mixed>>
     */
    private function rows(array $rows): Collection
    {
        return collect($rows)->map(fn (array $row): Collection => collect($row));
    }
}
