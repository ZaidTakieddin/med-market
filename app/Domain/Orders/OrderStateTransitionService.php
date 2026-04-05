<?php

namespace App\Domain\Orders;

use App\Constants\OrderStatus;
use App\Domain\Inventory\StockMovementService;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderStateTransitionService
{
    public function __construct(
        private StockMovementService $stockMovementService
    ) {}

    /**
     * Transition an order to a new state with validation and logging.
     *
     * @throws \InvalidArgumentException If transition is invalid
     */
    public function transition(Order $order, string $newStatus, ?int $userId = null, ?string $notes = null): Order
    {
        if (!OrderStatus::isValid($newStatus)) {
            throw new \InvalidArgumentException("Invalid order state: {$newStatus}");
        }

        if (!$order->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition order from {$order->status} to {$newStatus}"
            );
        }

        return DB::transaction(function () use ($order, $newStatus, $userId, $notes) {
            $oldStatus = $order->status;

            // Handle stock operations based on status transitions
            $this->handleStockOperations($order, $oldStatus, $newStatus, $userId);

            // Update order status and timestamp
            $order->status = $newStatus;
            $this->updateStatusTimestamp($order, $newStatus);
            $order->save();

            // Log the status change
            OrderStatusLog::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
                'notes' => $notes,
            ]);

            // Log to application log
            Log::info('Order status changed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'from' => $oldStatus,
                'to' => $newStatus,
                'user_id' => $userId,
            ]);

            // Dispatch notification job
            SendOrderNotificationJob::dispatch($order->fresh(), $oldStatus, $newStatus, $userId);

            // Generate invoice when order is accepted
            if ($newStatus === OrderStatus::ACCEPTED) {
                GenerateInvoiceJob::dispatch($order->fresh());
            }

            return $order->fresh();
        });
    }

    /**
     * Handle stock operations based on order status transitions.
     */
    private function handleStockOperations(Order $order, string $oldStatus, string $newStatus, ?int $userId): void
    {
        // Load order items with products if not already loaded
        if (!$order->relationLoaded('items')) {
            $order->load('items.product');
        }

        // Deduct stock when order is accepted or prepared
        if (in_array($newStatus, [OrderStatus::ACCEPTED, OrderStatus::PREPARED], true)) {
            // Only deduct if we're transitioning from pending or accepted
            if (in_array($oldStatus, [OrderStatus::PENDING, OrderStatus::ACCEPTED], true)) {
                // If already accepted and moving to prepared, skip (stock already deducted)
                if ($oldStatus === OrderStatus::ACCEPTED && $newStatus === OrderStatus::PREPARED) {
                    return;
                }

                $this->deductStockForOrder($order, $userId);
            }
        }

        // Revert stock if order is cancelled
        if ($newStatus === OrderStatus::CANCELLED) {
            // Only revert if stock was already deducted (order was accepted or prepared)
            if (in_array($oldStatus, [OrderStatus::ACCEPTED, OrderStatus::PREPARED], true)) {
                $this->revertStockForOrder($order, $userId);
            }
        }
    }

    /**
     * Deduct stock for all items in an order.
     */
    private function deductStockForOrder(Order $order, ?int $userId): void
    {
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);

            if (!$product) {
                Log::warning('Order item has no product', [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                ]);
                continue;
            }

            try {
                $this->stockMovementService->recordOut(
                    product: $product,
                    quantity: $item->quantity,
                    orderId: $order->id,
                    userId: $userId,
                    reason: "Order {$order->order_number} - {$order->status}"
                );
            } catch (\InvalidArgumentException $e) {
                Log::error('Failed to deduct stock for order', [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'error' => $e->getMessage(),
                ]);
                throw $e; // Re-throw to rollback transaction
            }
        }
    }

    /**
     * Revert stock for all items in a cancelled order.
     */
    private function revertStockForOrder(Order $order, ?int $userId): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            if (!$product) {
                continue;
            }

            try {
                // Add stock back (positive quantity)
                $this->stockMovementService->recordIn(
                    product: $product,
                    quantity: $item->quantity,
                    userId: $userId,
                    reason: "Order {$order->order_number} cancelled - stock reverted"
                );
            } catch (\InvalidArgumentException $e) {
                Log::error('Failed to revert stock for cancelled order', [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'error' => $e->getMessage(),
                ]);
                // Don't throw here - we want to continue reverting other items
            }
        }
    }

    /**
     * Update the appropriate timestamp field based on status.
     */
    private function updateStatusTimestamp(Order $order, string $status): void
    {
        match ($status) {
            OrderStatus::ACCEPTED => $order->accepted_at = now(),
            OrderStatus::PREPARED => $order->prepared_at = now(),
            OrderStatus::SHIPPED => $order->shipped_at = now(),
            OrderStatus::DELIVERED => $order->delivered_at = now(),
            OrderStatus::CANCELLED => $order->cancelled_at = now(),
            default => null,
        };
    }
}

