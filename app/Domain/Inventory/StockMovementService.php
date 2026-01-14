<?php

namespace App\Domain\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockMovementService
{
    /**
     * Record a stock movement and update product stock.
     *
     * @param int $quantity Positive for 'in', negative for 'out'
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
        ?string $referenceId = null
    ): StockMovement {
        if ($quantity === 0) {
            throw new \InvalidArgumentException('Stock movement quantity cannot be zero');
        }

        return DB::transaction(function () use (
            $product,
            $type,
            $quantity,
            $orderId,
            $userId,
            $reason,
            $referenceType,
            $referenceId
        ) {
            $quantityBefore = $product->stock_quantity;
            $quantityAfter = $quantityBefore + $quantity;

            if ($quantityAfter < 0) {
                throw new \InvalidArgumentException(
                    "Insufficient stock. Available: {$quantityBefore}, Requested: " . abs($quantity)
                );
            }

            // Update product stock
            $product->stock_quantity = $quantityAfter;
            $product->save();

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
            ]);

            return $movement;
        });
    }

    /**
     * Record stock increase (incoming stock).
     */
    public function recordIn(
        Product $product,
        int $quantity,
        ?int $userId = null,
        ?string $reason = null
    ): StockMovement {
        return $this->recordMovement($product, 'in', abs($quantity), null, $userId, $reason);
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

