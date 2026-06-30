<?php

namespace App\Domain\Products\Import;

use App\Domain\Products\ProductImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Streams the uploaded sheet in bounded-memory chunks. Each chunk runs as its
 * own short queue job (kept under the queue retry_after) and delegates the row
 * work to ProductImportService.
 */
class ProductRowsImport implements ShouldQueue, ToCollection, WithChunkReading, WithHeadingRow
{
    use Importable;
    use RemembersChunkOffset;

    /**
     * Applied to every chunk job (maatwebsite copies these onto each ReadChunk).
     * Single try + sub-retry_after timeout so a chunk can never be re-run in
     * parallel and double-import.
     */
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        private readonly int $importId,
        private readonly int $companyId,
    ) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $collection
     */
    public function collection(Collection $collection): void
    {
        app(ProductImportService::class)->processChunk(
            $this->importId,
            $this->companyId,
            $collection,
            (int) ($this->getChunkOffset() ?? 2),
        );
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
