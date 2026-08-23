<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Audit Logs', description: 'Immutable audit trail of sensitive operations (credit adjustments, invoice payments, stock transfers, account deletions). Admin only; IP-allowlisted when configured.')]
class AuditLogController extends BaseApiController
{
    /**
     * List audit entries.
     */
    #[OA\Get(
        path: '/audit-logs',
        summary: 'List audit log entries',
        description: 'Newest-first trail of sensitive actions. Filterable by `action` and `subject_type`.',
        tags: ['Audit Logs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'action', in: 'query', schema: new OA\Schema(type: 'string'), description: 'e.g. credit.balance.adjusted, invoice.payment.recorded, stock.transferred, user.deleted'),
            new OA\Parameter(name: 'subject_type', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Fully-qualified or basename class filter'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Audit logs retrieved successfully'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $this->currentUser()->isAdmin()) {
            return $this->error('Only admins can view the audit trail', null, 403);
        }

        $query = AuditLog::query()->with('user:id,name')->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('subject_type')) {
            $type = (string) $request->input('subject_type');
            $query->where(fn ($q) => $q
                ->where('subject_type', $type)
                ->orWhere('subject_type', 'like', '%\\'.$type));
        }

        [$logs, $pagination] = $this->paginateOrAll($query, $request);

        $additional = ['success' => true, 'message' => 'Audit logs retrieved successfully'];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return \App\Http\Resources\AuditLogResource::collection($logs)->additional($additional)->response();
    }
}
