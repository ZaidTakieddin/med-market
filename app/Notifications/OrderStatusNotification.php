<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus
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
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->message())
            ->line("Order Number: {$this->order->order_number}")
            ->line("Buyer: {$this->order->buyerCompany?->name}")
            ->line("Supplier: {$this->order->supplierCompany?->name}")
            ->line('Total Amount: '.number_format((float) $this->order->total_amount, 2))
            ->line("Items: {$this->order->items()->count()}");
    }

    /**
     * Get the array representation for the database channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'order_status',
            'title' => $this->title(),
            'message' => $this->message(),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }

    private function title(): string
    {
        return "Order {$this->order->order_number} - ".ucfirst($this->newStatus);
    }

    private function message(): string
    {
        return "Order status changed from {$this->oldStatus} to {$this->newStatus}.";
    }
}
