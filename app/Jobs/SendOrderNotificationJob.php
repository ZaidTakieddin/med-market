<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus,
        public ?int $userId = null
    ) {
        // Set queue name
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Reload order with relationships
            $this->order->load(['buyerCompany', 'supplierCompany', 'creator', 'items.product']);

            // Get recipients
            $recipients = $this->getRecipients($this->order);

            if (empty($recipients)) {
                Log::warning('No recipients found for order notification', [
                    'order_id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                ]);

                return;
            }

            // Send notification to each recipient
            foreach ($recipients as $recipient) {
                try {
                    // In a real implementation, you would send email here
                    // For now, we'll log it (since mail is configured to 'log' driver)
                    Mail::raw($this->buildEmailContent($this->order, $this->oldStatus, $this->newStatus), function ($message) use ($recipient) {
                        $message->to($recipient['email'], $recipient['name'])
                            ->subject($this->getEmailSubject($this->order, $this->newStatus));
                    });

                    Log::info('Order notification sent', [
                        'order_id' => $this->order->id,
                        'order_number' => $this->order->order_number,
                        'recipient' => $recipient['email'],
                        'status_change' => "{$this->oldStatus} -> {$this->newStatus}",
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send order notification', [
                        'order_id' => $this->order->id,
                        'recipient' => $recipient['email'],
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other recipients
                }
            }
        } catch (\Exception $e) {
            Log::error('Order notification job failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Get recipients for the notification.
     */
    private function getRecipients(Order $order): array
    {
        $recipients = [];

        if ($order->buyerCompany) {
            $buyerUsers = $order->buyerCompany->users()
                ->whereIn('role', ['buyer', 'salesman'])
                ->whereNotNull('email')
                ->get();

            foreach ($buyerUsers as $user) {
                $recipients[] = [
                    'email' => $user->email,
                    'name' => $user->name,
                    'type' => 'buyer',
                ];
            }
        }

        // Add supplier company users (suppliers)
        if ($order->supplierCompany) {
            $supplierUsers = $order->supplierCompany->users()
                ->whereIn('role', ['supplier', 'admin'])
                ->whereNotNull('email')
                ->get();

            foreach ($supplierUsers as $user) {
                $recipients[] = [
                    'email' => $user->email,
                    'name' => $user->name,
                    'type' => 'supplier',
                ];
            }
        }

        // Add order creator if not already included
        if ($order->creator && ! collect($recipients)->contains('email', $order->creator->email)) {
            $recipients[] = [
                'email' => $order->creator->email,
                'name' => $order->creator->name,
                'type' => 'creator',
            ];
        }

        return $recipients;
    }

    /**
     * Build email content.
     */
    private function buildEmailContent(Order $order, string $oldStatus, string $newStatus): string
    {
        $content = "Order Status Update\n\n";
        $content .= "Order Number: {$order->order_number}\n";
        $content .= "Status Changed: {$oldStatus} → {$newStatus}\n";
        $content .= "Buyer: {$order->buyerCompany->name}\n";
        $content .= "Supplier: {$order->supplierCompany->name}\n";
        $content .= 'Total Amount: '.number_format((float) $order->total_amount, 2)." SAR\n";
        $content .= "Items: {$order->items->count()}\n\n";

        if ($order->notes) {
            $content .= "Notes: {$order->notes}\n\n";
        }

        $content .= "Thank you for using Med Market!\n";

        return $content;
    }

    /**
     * Get email subject based on status.
     */
    private function getEmailSubject(Order $order, string $status): string
    {
        $statusLabels = [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'prepared' => 'Prepared',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        $statusLabel = $statusLabels[$status] ?? ucfirst($status);

        return "Order {$order->order_number} - {$statusLabel}";
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Order notification job permanently failed', [
            'order_id' => $this->order->id ?? null,
            'order_number' => $this->order->order_number ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
