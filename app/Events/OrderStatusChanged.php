<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Pushed to both sides of the order the moment a transition commits, so
     * dashboards and order lists update without polling.
     */
    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("supplier.{$this->order->supplier_company_id}"),
            new PrivateChannel("buyer.{$this->order->buyer_company_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'total_amount' => (float) $this->order->total_amount,
        ];
    }
}
