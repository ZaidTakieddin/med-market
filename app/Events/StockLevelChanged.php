<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockLevelChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Realtime inventory updates: pushed to the owning supplier's feed whenever a
     * stock movement changes a product's quantity.
     */
    public function __construct(
        public Product $product,
        public int $quantityBefore,
        public int $quantityAfter,
        public string $movementType
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("supplier.{$this->product->supplier_company_id}");
    }

    public function broadcastAs(): string
    {
        return 'stock.level.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->product->id,
            'sku' => $this->product->sku,
            'name' => $this->product->name,
            'quantity_before' => $this->quantityBefore,
            'quantity_after' => $this->quantityAfter,
            'movement_type' => $this->movementType,
            'is_low_stock' => $this->product->isLowStock(),
        ];
    }
}
