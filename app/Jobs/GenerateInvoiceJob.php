<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 120;

    /**
     * Max seconds the job may run before timing out. Kept below the queue's
     * retry_after (90s) so a hung job is killed before it can be retried in parallel.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order
    ) {
        // Set queue name
        $this->onQueue('invoices');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Reload order with relationships
            $this->order->load(['buyerCompany', 'supplierCompany', 'creator', 'items.product', 'driver']);

            // Generate invoice content
            $invoiceContent = $this->generateInvoiceContent($this->order);

            // Save invoice to storage
            $invoicePath = $this->saveInvoice($this->order, $invoiceContent);

            // Generate invoice number
            $invoiceNumber = 'INV-'.$this->order->order_number;

            // Check if invoice already exists
            $invoice = Invoice::where('order_id', $this->order->id)->first();

            if ($invoice) {
                // Update existing invoice
                $invoice->update([
                    'file_path' => $invoicePath,
                    'subtotal' => $this->order->subtotal,
                    'tax_amount' => $this->order->tax_amount,
                    'total_amount' => $this->order->total_amount,
                    'generated_at' => now(),
                ]);
            } else {
                // Create new invoice record
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'order_id' => $this->order->id,
                    'buyer_company_id' => $this->order->buyer_company_id,
                    'supplier_company_id' => $this->order->supplier_company_id,
                    'status' => 'generated',
                    'payment_status' => 'pending',
                    'subtotal' => $this->order->subtotal,
                    'tax_amount' => $this->order->tax_amount,
                    'total_amount' => $this->order->total_amount,
                    'file_path' => $invoicePath,
                    'file_format' => 'txt',
                    'generated_at' => now(),
                ]);
            }

            // Log invoice generation
            Log::info('Invoice generated successfully', [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_path' => $invoicePath,
            ]);

            // In a real implementation, you might:
            // - Send invoice via email
            // - Generate PDF version
            // - Send to accounting system

        } catch (\Exception $e) {
            Log::error('Invoice generation failed', [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Generate invoice content (text format).
     * In production, you would generate PDF using libraries like DomPDF or Snappy.
     */
    private function generateInvoiceContent(Order $order): string
    {
        $content = "═══════════════════════════════════════\n";
        $content .= "           INVOICE\n";
        $content .= "═══════════════════════════════════════\n\n";

        $content .= "Invoice Number: INV-{$order->order_number}\n";
        $content .= "Order Number: {$order->order_number}\n";
        $content .= 'Date: '.$order->created_at->format('Y-m-d H:i:s')."\n\n";

        $content .= "Buyer Information:\n";
        $content .= "  Company: {$order->buyerCompany->name}\n";
        if ($order->buyerCompany->address) {
            $content .= "  Address: {$order->buyerCompany->address}\n";
        }
        $content .= "\n";

        $content .= "Supplier Information:\n";
        $content .= "  Company: {$order->supplierCompany->name}\n";
        if ($order->supplierCompany->address) {
            $content .= "  Address: {$order->supplierCompany->address}\n";
        }
        $content .= "\n";

        $content .= "═══════════════════════════════════════\n";
        $content .= "Items:\n";
        $content .= "═══════════════════════════════════════\n\n";

        foreach ($order->items as $item) {
            $product = $item->product;
            $content .= sprintf(
                "%-30s %5d x %8.2f = %10.2f SAR\n",
                $product->name ?? 'N/A',
                $item->quantity,
                $item->unit_price,
                $item->total_price
            );
        }

        $content .= "\n";
        $content .= "═══════════════════════════════════════\n";
        $content .= sprintf("Subtotal: %30.2f SAR\n", $order->subtotal);
        $content .= sprintf("Tax: %35.2f SAR\n", $order->tax_amount);
        $content .= sprintf("Total: %33.2f SAR\n", $order->total_amount);
        $content .= "═══════════════════════════════════════\n\n";

        if ($order->notes) {
            $content .= "Notes: {$order->notes}\n\n";
        }

        if ($order->driver) {
            $content .= "Driver: {$order->driver->name}\n";
        }

        $content .= "\n";
        $content .= 'Status: '.strtoupper($order->status)."\n";
        $content .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";

        return $content;
    }

    /**
     * Save invoice to storage.
     */
    private function saveInvoice(Order $order, string $content): string
    {
        $directory = 'invoices/'.now()->format('Y/m');
        $filename = "INV-{$order->order_number}.txt";
        $path = "{$directory}/{$filename}";

        // Ensure directory exists
        Storage::makeDirectory($directory);

        // Save invoice
        Storage::put($path, $content);

        return $path;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Invoice generation job permanently failed', [
            'order_id' => $this->order->id ?? null,
            'order_number' => $this->order->order_number ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
