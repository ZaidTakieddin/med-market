<?php

namespace App\Jobs;

use App\Constants\ProductImportStatus;
use App\Domain\Products\Import\ProductRowsImport;
use App\Models\ProductImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Coordinator job: marks the import as processing and kicks off maatwebsite's
 * streamed, chunked read. Each chunk runs as its own short job; a final
 * FinalizeProductImportJob is chained to run after every chunk completes.
 */
class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted. One attempt only — the
     * underlying chunk jobs do the heavy lifting and re-dispatching this would
     * risk duplicate processing.
     */
    public int $tries = 1;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Reading sheet dimensions + dispatching chunk jobs is quick; the actual row
     * processing happens in the chunk jobs. Kept below the queue's retry_after
     * (90s) so a slow coordinator can't be re-run in parallel.
     */
    public int $timeout = 60;

    public function __construct(public int $importId)
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $import = ProductImport::find($this->importId);

        if ($import === null) {
            return;
        }

        $import->update([
            'status' => ProductImportStatus::PROCESSING,
            'started_at' => now(),
        ]);

        (new ProductRowsImport($import->id, $import->supplier_company_id))
            ->queue($import->stored_path, 'local')
            ->allOnQueue('imports')
            ->chain([
                new FinalizeProductImportJob($import->id, $import->supplier_company_id),
            ]);
    }

    public function failed(\Throwable $exception): void
    {
        $import = ProductImport::find($this->importId);

        if ($import !== null && ! in_array($import->status, [
            ProductImportStatus::COMPLETED,
            ProductImportStatus::COMPLETED_WITH_ERRORS,
        ], true)) {
            $import->update([
                'status' => ProductImportStatus::FAILED,
                'completed_at' => now(),
            ]);
        }

        Log::error('Product import failed to start', [
            'import_id' => $this->importId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
