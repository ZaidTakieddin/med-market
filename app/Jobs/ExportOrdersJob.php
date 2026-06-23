<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportOrdersJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public array $filters = [],
        public string $format = 'csv'
    ) {
        // Set queue name
        $this->onQueue('exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Build query based on filters
            $query = Order::with(['buyerCompany', 'supplierCompany', 'items.product']);

            // Apply filters
            if (isset($this->filters['company_id'])) {
                $query->where(function ($q) {
                    $q->where('buyer_company_id', $this->filters['company_id'])
                        ->orWhere('supplier_company_id', $this->filters['company_id']);
                });
            }

            if (isset($this->filters['status'])) {
                $query->where('status', $this->filters['status']);
            }

            if (isset($this->filters['date_from'])) {
                $query->whereDate('created_at', '>=', $this->filters['date_from']);
            }

            if (isset($this->filters['date_to'])) {
                $query->whereDate('created_at', '<=', $this->filters['date_to']);
            }

            // Get orders
            $orders = $query->orderBy('created_at', 'desc')->get();

            // Generate export file
            $filePath = $this->generateExportFile($orders, $this->format);

            // Log export generation
            Log::info('Orders export generated successfully', [
                'user_id' => $this->user->id,
                'orders_count' => $orders->count(),
                'format' => $this->format,
                'file_path' => $filePath,
            ]);

            // In a real implementation, you might:
            // - Send export file via email
            // - Store export reference in database
            // - Provide download link
            // - Generate different formats (Excel, PDF, etc.)

        } catch (\Exception $e) {
            Log::error('Orders export job failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Generate export file.
     */
    private function generateExportFile($orders, string $format): string
    {
        $directory = 'exports/orders/'.now()->format('Y/m');
        $timestamp = now()->format('Ymd_His');
        $filename = "orders_export_{$timestamp}.{$format}";
        $path = "{$directory}/{$filename}";

        // Ensure directory exists
        Storage::makeDirectory($directory);

        // Generate content based on format
        $content = match ($format) {
            'csv' => $this->generateCsvContent($orders),
            'json' => $this->generateJsonContent($orders),
            default => $this->generateCsvContent($orders),
        };

        // Save file
        Storage::put($path, $content);

        return $path;
    }

    /**
     * Generate CSV content.
     */
    private function generateCsvContent($orders): string
    {
        $lines = [];

        // Header
        $lines[] = [
            'Order Number',
            'Buyer Company',
            'Supplier Company',
            'Status',
            'Subtotal',
            'Tax',
            'Total',
            'Items Count',
            'Created At',
            'Created By',
        ];

        // Data rows
        foreach ($orders as $order) {
            $lines[] = [
                $order->order_number,
                $order->buyerCompany->name ?? 'N/A',
                $order->supplierCompany->name ?? 'N/A',
                $order->status,
                number_format($order->subtotal, 2),
                number_format($order->tax_amount, 2),
                number_format($order->total_amount, 2),
                $order->items->count(),
                $order->created_at->format('Y-m-d H:i:s'),
                $order->creator->name ?? 'N/A',
            ];
        }

        // Convert to CSV string
        $csv = '';
        foreach ($lines as $line) {
            $csv .= implode(',', array_map(function ($field) {
                return '"'.str_replace('"', '""', $field).'"';
            }, $line))."\n";
        }

        return $csv;
    }

    /**
     * Generate JSON content.
     */
    private function generateJsonContent($orders): string
    {
        $data = $orders->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'buyer_company' => $order->buyerCompany->name ?? null,
                'supplier_company' => $order->supplierCompany->name ?? null,
                'status' => $order->status,
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'total_amount' => $order->total_amount,
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at->toIso8601String(),
                'created_by' => $order->creator->name ?? null,
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product->name ?? null,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ];
                }),
            ];
        });

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Orders export job permanently failed', [
            'user_id' => $this->user->id ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
