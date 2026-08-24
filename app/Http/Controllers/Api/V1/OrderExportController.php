<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ExportStatus;
use App\Domain\Orders\OrderExportService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Orders\CreateOrderExportRequest;
use App\Http\Resources\OrderExportResource;
use App\Models\OrderExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[OA\Tag(name: 'Order Exports', description: 'Async order exports (CSV/JSON). Exports are queued, scoped to a single company, and downloaded once completed.')]
class OrderExportController extends BaseApiController
{
    public function __construct(
        private OrderExportService $orderExportService
    ) {}

    /**
     * Request an order export.
     */
    #[OA\Post(
        path: '/orders/exports',
        summary: 'Request an order export',
        description: 'Queue an async export of orders (CSV or JSON) for one company. The response is **202 Accepted** with the export record (status `queued`) — poll `GET /orders/exports/{id}` until status is `completed`, then download via `GET /orders/exports/{id}/download`. Users export their own company\'s orders; admins may pass `company_id` to target any company. Optional filters: `status`, `date_from`, `date_to`.',
        tags: ['Order Exports'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['format'],
                properties: [
                    new OA\Property(property: 'format', type: 'string', enum: ['csv', 'json', 'xlsx'], example: 'csv'),
                    new OA\Property(property: 'status', type: 'string', nullable: true, description: 'Filter by order status', enum: ['pending', 'accepted', 'prepared', 'shipped', 'delivered', 'cancelled']),
                    new OA\Property(property: 'date_from', type: 'string', format: 'date', nullable: true, description: 'Orders created on/after this date'),
                    new OA\Property(property: 'date_to', type: 'string', format: 'date', nullable: true, description: 'Orders created on/before this date'),
                    new OA\Property(property: 'company_id', type: 'integer', nullable: true, description: 'Admin only — scope the export to this company instead of the admin account (admins have no company)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 202, description: 'Export queued successfully. Returns the export record with status "queued".'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(CreateOrderExportRequest $request): JsonResponse
    {
        $user = $this->currentUser();
        $this->authorize('create', OrderExport::class);

        $filters = array_filter([
            'status' => $request->status,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ], fn ($value): bool => $value !== null);

        $export = $this->orderExportService->request(
            user: $user,
            format: (string) $request->format,
            filters: $filters,
            companyId: $request->company_id !== null ? (int) $request->company_id : null,
        );

        return $this->success(
            new OrderExportResource($export->load(['company', 'user'])),
            'Export queued successfully',
            202
        );
    }

    /**
     * List order exports.
     */
    #[OA\Get(
        path: '/orders/exports',
        summary: 'List order exports',
        description: 'List order exports for the current company (newest first), including status so clients can poll for completion. Admins see all exports.',
        tags: ['Order Exports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Exports retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $this->authorize('viewAny', OrderExport::class);

        $query = OrderExport::with(['company', 'user'])->latest();

        if (! $user->isAdmin()) {
            $query->where('company_id', (int) $user->company_id);
        }

        [$exports, $pagination] = $this->paginateOrAll($query, $request);

        $additional = [
            'success' => true,
            'message' => 'Exports retrieved successfully',
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return OrderExportResource::collection($exports)
            ->additional($additional)
            ->response();
    }

    /**
     * Get an export's status/details.
     */
    #[OA\Get(
        path: '/orders/exports/{id}',
        summary: 'Get export status',
        description: 'Poll this endpoint while an export is queued/processing. Once `status` is `completed`, download via `/orders/exports/{id}/download`.',
        tags: ['Order Exports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Export retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $export = OrderExport::with(['company', 'user'])->findOrFail($id);
        $this->authorize('view', $export);

        return $this->success(
            new OrderExportResource($export),
            'Export retrieved successfully'
        );
    }

    /**
     * Download a completed export file.
     */
    #[OA\Get(
        path: '/orders/exports/{id}/download',
        summary: 'Download a completed export',
        description: 'Stream the generated export file. Only available once the export status is `completed`; otherwise 422.',
        tags: ['Order Exports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File download stream'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function download(int $id): StreamedResponse|JsonResponse
    {
        $export = OrderExport::findOrFail($id);
        $this->authorize('view', $export);

        if ($export->status !== ExportStatus::COMPLETED || $export->file_path === null || ! Storage::exists($export->file_path)) {
            return $this->error('The export file is not available yet.', null, 422);
        }

        $filename = basename($export->file_path);

        return Storage::download($export->file_path, $filename);
    }
}
