<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Product $product
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $deficit = max(0, (int) $this->product->min_stock_level - (int) $this->product->stock_quantity);

        return (new MailMessage)
            ->subject("Low Stock Alert: {$this->product->name}")
            ->line($this->message())
            ->line("Product: {$this->product->name}")
            ->line("SKU: {$this->product->sku}")
            ->line("Current Stock: {$this->product->stock_quantity}")
            ->line("Minimum Stock Level: {$this->product->min_stock_level}")
            ->line("Stock Deficit: {$deficit}")
            ->line('Please consider restocking this product to avoid stockouts.');
    }

    /**
     * Get the array representation for the database channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'low_stock',
            'title' => "Low stock: {$this->product->name}",
            'message' => $this->message(),
            'product_id' => $this->product->id,
            'sku' => $this->product->sku,
            'current_stock' => (int) $this->product->stock_quantity,
            'min_stock_level' => (int) $this->product->min_stock_level,
        ];
    }

    private function message(): string
    {
        return "{$this->product->name} ({$this->product->sku}) is below its minimum stock level.";
    }
}
