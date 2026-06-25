<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     * Create a new job instance.
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
            // Reload product with supplier company
            $product->load('supplierCompany');

            if (! $product->supplierCompany) {
                Log::warning('Product has no supplier company for low stock alert', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                ]);

                return;
            }

            // Get supplier company users (suppliers and admins)
            $recipients = $product->supplierCompany->users()
                ->whereIn('role', ['supplier', 'admin'])
                ->whereNotNull('email')
                ->get();

            if ($recipients->isEmpty()) {
                Log::warning('No recipients found for low stock alert', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_company_id' => $product->supplier_company_id,
                ]);

                return;
            }

            // Send alert to each recipient
            foreach ($recipients as $user) {
                try {
                    // In a real implementation, you would send email here
                    // For now, we'll log it (since mail is configured to 'log' driver)
                    Mail::raw($this->buildEmailContent($product), function ($message) use ($user, $product) {
                        $message->to($user->email, $user->name)
                            ->subject("Low Stock Alert: {$product->name}");
                    });

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
     * Build email content.
     */
    private function buildEmailContent(Product $product): string
    {
        $content = "Low Stock Alert\n\n";
        $content .= "Product: {$product->name}\n";
        $content .= "SKU: {$product->sku}\n";
        $content .= "Current Stock: {$product->stock_quantity}\n";
        $content .= "Minimum Stock Level: {$product->min_stock_level}\n";
        $content .= 'Stock Deficit: '.max(0, $product->min_stock_level - $product->stock_quantity)."\n\n";

        if ($product->category) {
            $content .= "Category: {$product->category}\n";
        }

        $content .= "\n";
        $content .= "Please consider restocking this product to avoid stockouts.\n\n";
        $content .= "Thank you for using Med Market!\n";

        return $content;
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
