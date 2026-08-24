<?php

namespace App\Jobs;

use App\Constants\ExportStatus;
use App\Models\Order;
use App\Models\OrderExport;
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
     * Max seconds the job may run before timing out. Kept below the queue's
     * retry_after (90s) so a hung job is killed before it can be retried in parallel.
     * Very large exports should move to chunked/batched processing.
     */
    public int $timeout = 75;

    /**
     * Generate an order-export file for a tracked OrderExport record. The export is
     * always scoped to a single company (buyer or supplier side); status transitions
     * (queued → processing → completed/failed) are persisted so clients can poll.
     */
    public function __construct(
        public OrderExport $export
    ) {
        $this->onQueue('exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->export->update([
                'status' => ExportStatus::PROCESSING,
                'started_at' => now(),
            ]);

            $query = Order::with(['buyerCompany', 'supplierCompany', 'items.product', 'creator'])
                ->where(function ($q): void {
                    $q->where('buyer_company_id', $this->export->company_id)
                        ->orWhere('supplier_company_id', $this->export->company_id);
                });

            /** @var array<string, mixed> $filters */
            $filters = $this->export->filters ?? [];

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            // Get orders
            $orders = $query->orderBy('created_at', 'desc')->get();

            // Generate export file
            $filePath = $this->generateExportFile($orders, $this->export->format);

            $this->export->update([
                'status' => ExportStatus::COMPLETED,
                'file_path' => $filePath,
                'orders_count' => $orders->count(),
                'error' => null,
                'completed_at' => now(),
            ]);

            // Log export generation
            Log::info('Orders export generated successfully', [
                'order_export_id' => $this->export->id,
                'user_id' => $this->export->user_id,
                'company_id' => $this->export->company_id,
                'orders_count' => $orders->count(),
                'format' => $this->export->format,
                'file_path' => $filePath,
            ]);
        } catch (\Throwable $e) {
            Log::error('Orders export job failed', [
                'order_export_id' => $this->export->id ?? null,
                'user_id' => $this->export->user_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Generate the export file, branching to a native Excel writer for xlsx.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Order>  $orders
     */
    private function generateExportFile($orders, string $format): string
    {
        if ($format === 'xlsx') {
            return $this->storeExcelExport($orders);
        }

        $directory = 'exports/orders/'.now()->format('Y/m');
        $timestamp = now()->format('Ymd_His');
        $filename = "orders_export_{$this->export->id}_{$timestamp}.{$format}";
        $path = "{$directory}/{$filename}";

        // Ensure directory exists
        Storage::makeDirectory($directory);

        $content = match ($format) {
            'csv' => $this->generateCsvContent($orders),
            'json' => $this->generateJsonContent($orders),
            default => $this->generateCsvContent($orders),
        };

        Storage::put($path, $content);

        return $path;
    }

    /**
     * Write an .xlsx workbook via maatwebsite/excel onto the default disk.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Order>  $orders
     */
    private function storeExcelExport($orders): string
    {
        $path = 'exports/orders/'.now()->format('Y/m').'/orders_export_'.$this->export->id.'_'.now()->format('Ymd_His').'.xlsx';

        \Maatwebsite\Excel\Facades\Excel::store(
            new \App\Domain\Orders\Exports\OrdersExport($orders),
            $path,
        );

        return $path;
    }

    /**
     * Generate CSV content.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Order>  $orders
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
                number_format((float) $order->subtotal, 2),
                number_format((float) $order->tax_amount, 2),
                number_format((float) $order->total_amount, 2),
                $order->items->count(),
                $order->created_at?->format('Y-m-d H:i:s'),
                $order->creator->name ?? 'N/A',
            ];
        }

        // Convert to CSV string
        $csv = '';
        foreach ($lines as $line) {
            $csv .= implode(',', array_map(function ($field) {
                return '"'.str_replace('"', '""', (string) $field).'"';
            }, $line))."\n";
        }

        return $csv;
    }

    /**
     * Generate JSON content.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Order>  $orders
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
                'created_at' => $order->created_at?->toIso8601String(),
                'created_by' => $order->creator->name ?? null,
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product->name ?? null,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ];
                })->all(),
            ];
        });

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->export->update([
            'status' => ExportStatus::FAILED,
            'error' => 'The export could not be generated.',
            'completed_at' => now(),
        ]);

        Log::error('Orders export job permanently failed', [
            'order_export_id' => $this->export->id ?? null,
            'user_id' => $this->export->user_id ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
