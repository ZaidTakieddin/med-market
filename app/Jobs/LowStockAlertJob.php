<?php

namespace App\Jobs;

use App\Constants\UserRole;
use App\Models\Product;
use App\Notifications\LowStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LowStockAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 300; // 5 minutes

    /**
     * Max seconds the job may run before timing out. Kept below the queue's
     * retry_after (90s) so a hung job is killed before it can be retried in parallel.
     */
    public int $timeout = 45;

    /**
     * Alert the supplying company's users when a product falls below its minimum
     * stock level, via mail + database channels.
     */
    public function __construct(
        public Product $product
    ) {
        // Set queue name
        $this->onQueue('alerts');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $product = $this->product;

        try {
            $product->load('supplierCompany');

            if ($product->supplierCompany === null) {
                Log::warning('Product has no supplier company for low stock alert', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                ]);

                return;
            }

            /** @var \Illuminate\Support\Collection<int, \App\Models\User> $recipients */
            $recipients = $product->supplierCompany->users()
                ->where('role', UserRole::SUPPLIER)
                ->get();

            if ($recipients->isEmpty()) {
                Log::warning('No recipients found for low stock alert', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_company_id' => $product->supplier_company_id,
                ]);

                return;
            }

            $notification = new LowStockNotification($product);

            foreach ($recipients as $user) {
                try {
                    $user->notify($notification);

                    Log::info('Low stock alert sent', [
                        'product_id' => $product->id,
                        'product_sku' => $product->sku,
                        'product_name' => $product->name,
                        'current_stock' => $product->stock_quantity,
                        'min_stock_level' => $product->min_stock_level,
                        'recipient' => $user->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send low stock alert', [
                        'product_id' => $product->id,
                        'recipient' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other recipients
                }
            }
        } catch (\Exception $e) {
            Log::error('Low stock alert job failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Low stock alert job permanently failed', [
            'product_id' => $this->product->id ?? null,
            'product_sku' => $this->product->sku ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
