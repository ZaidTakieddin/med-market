<?php

namespace App\Jobs;

use App\Constants\UserRole;
use App\Models\Order;
use App\Notifications\OrderStatusNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
     * Max seconds the job may run before timing out. Kept below the queue's
     * retry_after (90s) so a hung job is killed before it can be retried in parallel.
     */
    public int $timeout = 45;

    /**
     * Notify buyer-side and supplier-side users about an order status change via
     * mail + database channels. Recipient failures never abort other recipients;
     * unexpected job-level failures are re-thrown to trigger retry.
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
            $this->order->load(['buyerCompany', 'supplierCompany', 'creator']);

            $recipients = $this->getRecipients($this->order);

            if ($recipients->isEmpty()) {
                Log::warning('No recipients found for order notification', [
                    'order_id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                ]);

                return;
            }

            $notification = new OrderStatusNotification($this->order, $this->oldStatus, $this->newStatus);

            foreach ($recipients as $recipient) {
                try {
                    $recipient->notify($notification);

                    Log::info('Order notification sent', [
                        'order_id' => $this->order->id,
                        'order_number' => $this->order->order_number,
                        'recipient' => $recipient->email,
                        'status_change' => "{$this->oldStatus} -> {$this->newStatus}",
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send order notification', [
                        'order_id' => $this->order->id,
                        'recipient' => $recipient->email,
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
     * Buyer company users (buyers/salesmen), supplier company users (suppliers) and
     * the order creator, deduplicated by id.
     *
     * @return Collection<int, \App\Models\User>
     */
    private function getRecipients(Order $order): Collection
    {
        /** @var Collection<int, \App\Models\User> $recipients */
        $recipients = collect();

        if ($order->buyerCompany !== null) {
            $recipients = $recipients->merge(
                $order->buyerCompany->users()
                    ->whereIn('role', UserRole::buyerRoles())
                    ->get()
            );
        }

        if ($order->supplierCompany !== null) {
            $recipients = $recipients->merge(
                $order->supplierCompany->users()
                    ->where('role', UserRole::SUPPLIER)
                    ->get()
            );
        }

        if ($order->creator !== null) {
            $recipients->push($order->creator);
        }

        return $recipients->unique('id');
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
