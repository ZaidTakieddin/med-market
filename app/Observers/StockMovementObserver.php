<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "created" event.
     */
    public function created(StockMovement $stockMovement): void
    {
        $product = $stockMovement->product;

        Log::info('Stock movement recorded', [
            'movement_id' => $stockMovement->id,
            'product_id' => $stockMovement->product_id,
            'product_sku' => $product->sku ?? 'N/A',
            'product_name' => $product->name ?? 'N/A',
            'type' => $stockMovement->type,
            'quantity' => $stockMovement->quantity,
            'quantity_before' => $stockMovement->quantity_before,
            'quantity_after' => $stockMovement->quantity_after,
            'order_id' => $stockMovement->order_id,
            'user_id' => $stockMovement->user_id,
            'reason' => $stockMovement->reason,
        ]);

        // Log low stock warning if applicable
        if ($product && $product->isLowStock()) {
            Log::warning('Product low on stock', [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'current_stock' => $product->stock_quantity,
                'min_stock_level' => $product->min_stock_level,
            ]);
        }
    }

    /**
     * Handle the StockMovement "updated" event.
     */
    public function updated(StockMovement $stockMovement): void
    {
        // Stock movements should rarely be updated, but log if they are
        if ($stockMovement->wasChanged()) {
            Log::warning('Stock movement updated', [
                'movement_id' => $stockMovement->id,
                'product_id' => $stockMovement->product_id,
                'changes' => $stockMovement->getChanges(),
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Handle the StockMovement "deleted" event (soft delete).
     */
    public function deleted(StockMovement $stockMovement): void
    {
        Log::warning('Stock movement soft deleted', [
            'movement_id' => $stockMovement->id,
            'product_id' => $stockMovement->product_id,
            'type' => $stockMovement->type,
            'quantity' => $stockMovement->quantity,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the StockMovement "restored" event.
     */
    public function restored(StockMovement $stockMovement): void
    {
        Log::info('Stock movement restored', [
            'movement_id' => $stockMovement->id,
            'product_id' => $stockMovement->product_id,
            'user_id' => auth()->id(),
        ]);
    }
}

