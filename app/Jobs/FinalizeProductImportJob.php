<?php

namespace App\Jobs;

use App\Constants\ProductImportStatus;
use App\Domain\Companies\SupplierTaxonomySyncService;
use App\Models\ProductImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs once after all chunk jobs complete (chained). Performs the single
 * supplier taxonomy sync (the per-row ProductObserver is bypassed during the
 * bulk import) and records the final status from the accumulated counts.
 */
class FinalizeProductImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Kept below the queue's retry_after (90s) to avoid overlapping retries.
     */
    public int $timeout = 60;

    public function __construct(public int $importId, public int $companyId)
    {
        $this->onQueue('imports');
    }

    public function handle(SupplierTaxonomySyncService $taxonomySync): void
    {
        $import = ProductImport::find($this->importId);

        if ($import === null) {
            return;
        }

        $taxonomySync->syncForCompany($this->companyId);

        $import->update([
            'status' => $import->failed_count > 0
                ? ProductImportStatus::COMPLETED_WITH_ERRORS
                : ProductImportStatus::COMPLETED,
            'total_rows' => $import->created_count + $import->updated_count + $import->failed_count,
            'completed_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Product import finalization failed', [
            'import_id' => $this->importId,
            'error' => $exception->getMessage(),
        ]);
    }
}
