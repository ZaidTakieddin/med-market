<?php

namespace App\Observers;

use App\Constants\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Only log to application log, not to OrderStatusLog
        // OrderStatusLog entries are created by OrderStateTransitionService
        Log::info('Order created', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'buyer_company_id' => $order->buyer_company_id,
            'supplier_company_id' => $order->supplier_company_id,
            'created_by' => $order->created_by,
            'total_amount' => $order->total_amount,
        ]);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Only log to application log, not to OrderStatusLog
        // OrderStatusLog entries are created by OrderStateTransitionService
        if ($order->wasChanged('status')) {
            $originalStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            Log::info('Order status changed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'from' => $originalStatus,
                'to' => $newStatus,
                'user_id' => auth()->id(),
            ]);

            // Log cancellation reason if order was cancelled
            if ($newStatus === OrderStatus::CANCELLED && $order->cancellation_reason) {
                Log::info('Order cancelled', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'reason' => $order->cancellation_reason,
                    'user_id' => auth()->id(),
                ]);
            }
        }

        // Log amount changes
        if ($order->wasChanged(['subtotal', 'tax_amount', 'total_amount'])) {
            Log::info('Order amounts updated', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'total_amount' => $order->total_amount,
            ]);
        }
    }

    /**
     * Handle the Order "deleted" event (soft delete).
     */
    public function deleted(Order $order): void
    {
        Log::warning('Order soft deleted', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        Log::info('Order restored', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => auth()->id(),
        ]);
    }
}
