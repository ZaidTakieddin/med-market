<?php

namespace App\Domain\Inventory;

use App\Exceptions\DomainException;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Collection;

class ProductBatchService
{
    /**
     * Whether the product is batch-tracked (it has at least one batch bucket).
     * Products that never received a batch stay in legacy mode: movements adjust
     * only the product's stock_quantity.
     */
    public function isTracked(Product $product): bool
    {
        return $product->batches()->exists();
    }

    /**
     * Add stock to the product's batch buckets. A null batch_number goes to the
     * single unbatched bucket; otherwise the (batch_number, expiry_date) bucket is
     * created or topped up.
     */
    public function receive(Product $product, int $quantity, ?string $batchNumber, ?string $expiryDate): ProductBatch
    {
        $bucket = $product->batches()
            ->when($batchNumber !== null, fn ($q) => $q->where('batch_number', $batchNumber))
            ->when($batchNumber === null, fn ($q) => $q->whereNull('batch_number'))
            ->first();

        if ($bucket === null) {
            /** @var ProductBatch */
            return $product->batches()->create([
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate !== null ? \Carbon\Carbon::parse($expiryDate)->startOfDay() : null,
                'quantity' => $quantity,
            ]);
        }

        $bucket->quantity += $quantity;

        // A receipt may refine the expiry of an existing batch.
        if ($expiryDate !== null && $bucket->expiry_date === null) {
            $bucket->expiry_date = \Carbon\Carbon::parse($expiryDate)->startOfDay();
        }

        $bucket->save();

        return $bucket->refresh();
    }

    /**
     * Allocate $quantity out of the product's batches, earliest-expiry-first
     * (FEFO), never-expiring last. Throws when the batch ledger cannot cover it —
     * which also catches ledger drift below zero stock. Empty buckets are deleted.
     *
     * @return Collection<int, array{batch_id: int, batch_number: string|null, quantity: int}>
     *
     * @throws DomainException When the batches hold less than the requested quantity
     */
    public function consumeFefo(Product $product, int $quantity): Collection
    {
        $remaining = $quantity;
        $allocations = [];

        /** @var Collection<int, ProductBatch> $buckets */
        $buckets = $product->batches()->fefo()->lockForUpdate()->get();

        foreach ($buckets as $bucket) {
            if ($remaining === 0) {
                break;
            }

            if ($bucket->quantity <= 0) {
                continue;
            }

            $take = min($bucket->quantity, $remaining);
            $bucket->quantity -= $take;
            $remaining -= $take;

            $allocations[] = [
                'batch_id' => $bucket->id,
                'batch_number' => $bucket->batch_number,
                'quantity' => $take,
            ];

            if ($bucket->quantity === 0) {
                $bucket->delete();
            } else {
                $bucket->save();
            }
        }

        if ($remaining > 0) {
            throw new DomainException(
                "Insufficient batched stock for {$product->sku}. Short by {$remaining}."
            );
        }

        return collect($allocations);
    }
}
