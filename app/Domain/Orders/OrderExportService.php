<?php

namespace App\Domain\Orders;

use App\Constants\ExportStatus;
use App\Exceptions\DomainException;
use App\Jobs\ExportOrdersJob;
use App\Models\OrderExport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderExportService
{
    /**
     * Queue an order export. Every export is scoped to exactly one company — the
     * buyer or supplier side of the matching orders — so exports can never leak
     * across the tenancy boundary. Users export their own company's orders; admins
     * may target any company.
     *
     * @param  array<string, mixed>  $filters  Optional status/date_from/date_to filters
     *
     * @throws DomainException When a non-admin targets another company or no target
     *                         company can be resolved
     */
    public function request(User $user, string $format, array $filters = [], ?int $companyId = null): OrderExport
    {
        if ($companyId !== null && ! $user->isAdmin() && $user->company_id !== $companyId) {
            throw new DomainException('You can only export orders for your own company.');
        }

        $targetCompanyId = $companyId ?? $user->company_id;

        if ($targetCompanyId === null) {
            throw new DomainException('A company_id is required to scope the export.');
        }

        /** @var OrderExport */
        $export = DB::transaction(function () use ($user, $format, $filters, $targetCompanyId): OrderExport {
            return OrderExport::create([
                'user_id' => $user->id,
                'company_id' => $targetCompanyId,
                'status' => ExportStatus::QUEUED,
                'format' => $format,
                'filters' => $filters !== [] ? $filters : null,
            ]);
        });

        ExportOrdersJob::dispatch($export);

        return $export;
    }
}
