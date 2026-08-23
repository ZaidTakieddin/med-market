<?php

namespace App\Domain\Inventory;

use App\Exceptions\DomainException;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockMovementService
{
    public function __construct(
        private ProductBatchService $productBatchService
    ) {}

    /**
    /**
     * Record a stock movement and update product stock.
     *
     * @param  int  $quantity  Positive for 'in', negative for 'out'
     *
     * @throws \InvalidArgumentException If quantity is invalid
     */
    public function recordMovement(
        Product $product,
        string $type,
        int $quantity,
        ?int $orderId = null,
        ?int $userId = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $batchNumber = null,
        ?string $expiryDate = null
    ): StockMovement {
        if ($quantity === 0) {
            throw new DomainException('Stock movement quantity cannot be zero');
        }

        return DB::transaction(function () use (
            $product,
            $type,
            $quantity,
            $orderId,
            $userId,
            $reason,
            $referenceType,
            $referenceId,
            $batchNumber,
            $expiryDate
        ) {
            // Pessimistically lock the product row so two concurrent movements cannot
            // both read the same quantity and oversell (stock < 0). lockForUpdate is a
            // no-op on sqlite (tests) and enforced on PostgreSQL.
            $lockedProduct = Product::whereKey($product->getKey())->lockForUpdate()->first();

            if ($lockedProduct === null) {
                throw new DomainException("Product {$product->getKey()} not found for stock movement");
            }

            $quantityBefore = $lockedProduct->stock_quantity;
            $quantityAfter = $quantityBefore + $quantity;

            if ($quantityAfter < 0) {
                throw new DomainException(
                    "Insufficient stock. Available: {$quantityBefore}, Requested: ".abs($quantity)
                );
            }

            // Update product stock on the locked row, keeping the caller's instance in sync.
            $lockedProduct->stock_quantity = $quantityAfter;

            // Keep the product's current batch/expiry in sync when the receipt carries one.
            if ($batchNumber !== null) {
                $lockedProduct->batch_number = $batchNumber;
                $product->batch_number = $batchNumber;
            }
            if ($expiryDate !== null) {
                $expiry = Carbon::parse($expiryDate)->startOfDay();
                $lockedProduct->expiry_date = $expiry;
                $product->expiry_date = $expiry;
            }

            // Maintain the batch ledger (FEFO). Positive movements receive into a
            // bucket; negative movements allocate earliest-expiry-first. Products
            // never received with a batch stay in legacy (untracked) mode.
            if ($quantity > 0 && ($batchNumber !== null || $this->productBatchService->isTracked($lockedProduct))) {
                $this->productBatchService->receive($lockedProduct, $quantity, $batchNumber, $batchNumber !== null ? $expiryDate : null);
            } elseif ($quantity < 0 && $this->productBatchService->isTracked($lockedProduct)) {
                $this->productBatchService->consumeFefo($lockedProduct, abs($quantity));
            }

            $lockedProduct->save();
            $product->stock_quantity = $quantityAfter;

            // Record the movement
            $movement = StockMovement::create([
                'product_id' => $product->id,
                'order_id' => $orderId,
                'user_id' => $userId,
                'type' => $type,
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
            ]);

            // Log to application log
            Log::info('Stock movement recorded', [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'type' => $type,
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'order_id' => $orderId,
                'user_id' => $userId,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
            ]);

            return $movement;
        });
    }

    /**
     * Record stock increase (incoming stock). Batch/expiry, when provided, are stored
     * on the movement and become the product's current batch/expiry.
     */
    public function recordIn(
        Product $product,
        int $quantity,
        ?int $userId = null,
        ?string $reason = null,
        ?string $batchNumber = null,
        ?string $expiryDate = null
    ): StockMovement {
        return $this->recordMovement(
            product: $product,
            type: 'in',
            quantity: abs($quantity),
            userId: $userId,
            reason: $reason,
            batchNumber: $batchNumber,
            expiryDate: $expiryDate
        );
    }

    /**
     * Record stock decrease (outgoing stock).
     */
    public function recordOut(
        Product $product,
        int $quantity,
        ?int $orderId = null,
        ?int $userId = null,
        ?string $reason = null
    ): StockMovement {
        return $this->recordMovement($product, 'out', -abs($quantity), $orderId, $userId, $reason, 'order', (string) $orderId);
    }
}
