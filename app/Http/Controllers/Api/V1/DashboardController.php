<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboard\DashboardService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Dashboard', description: 'Aggregated analytics for the dashboard home page')]
class DashboardController extends BaseApiController
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Supplier dashboard overview.
     */
    #[OA\Get(
        path: '/dashboard',
        summary: 'Dashboard overview',
        description: 'Aggregated analytics for the supplier dashboard home page: KPI cards (pending orders, active shipments, total products, total orders with trend), a daily sales series, best-selling products, and top buyers. Revenue-based metrics (sales series, top products, top buyers) only count fulfilled (delivered) orders. Suppliers see metrics scoped to their own company. Admins see global metrics, or a single supplier when `supplier_company_id` is provided. Other roles are forbidden.',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'days', in: 'query', schema: new OA\Schema(type: 'integer', default: 7, minimum: 1, maximum: 90), description: 'Number of trailing days for the sales overview series'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 5, minimum: 1, maximum: 20), description: 'Number of rows for top products and top buyers'),
            new OA\Parameter(name: 'supplier_company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Admin only. Scope metrics to a single supplier company.'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard data retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'kpis',
                                    type: 'array',
                                    description: 'KPI cards. `trend` is present only for metrics with a period-over-period comparison; `trend.tone` is one of positive/warning/danger/neutral.',
                                    items: new OA\Items(type: 'object', properties: [
                                        new OA\Property(property: 'id', type: 'string', example: 'total_orders'),
                                        new OA\Property(property: 'title', type: 'string', example: 'إجمالي الطلبات'),
                                        new OA\Property(property: 'value', type: 'string', example: '450'),
                                        new OA\Property(property: 'description', type: 'string', example: 'منذ الشهر الماضي'),
                                        new OA\Property(property: 'trend', type: 'object', nullable: true, properties: [
                                            new OA\Property(property: 'value', type: 'string', example: '+12%'),
                                            new OA\Property(property: 'direction', type: 'string', enum: ['up', 'down', 'flat'], example: 'up'),
                                            new OA\Property(property: 'tone', type: 'string', enum: ['positive', 'warning', 'danger', 'neutral'], example: 'positive'),
                                        ]),
                                    ])
                                ),
                                new OA\Property(
                                    property: 'salesSeries',
                                    type: 'array',
                                    description: 'Number of fulfilled (delivered) orders per day, oldest first, zero-filled.',
                                    items: new OA\Items(type: 'object', properties: [
                                        new OA\Property(property: 'day', type: 'string', example: 'Fri'),
                                        new OA\Property(property: 'sales', type: 'integer', example: 8),
                                    ])
                                ),
                                new OA\Property(
                                    property: 'topProducts',
                                    type: 'array',
                                    items: new OA\Items(type: 'object', properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'Paracetamol 500mg'),
                                        new OA\Property(property: 'stock', type: 'integer', example: 120),
                                        new OA\Property(property: 'price', type: 'number', format: 'float', example: 10),
                                        new OA\Property(property: 'totalSold', type: 'integer', example: 340),
                                    ])
                                ),
                                new OA\Property(
                                    property: 'topBuyers',
                                    type: 'array',
                                    items: new OA\Items(type: 'object', properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 7),
                                        new OA\Property(property: 'name', type: 'string', example: 'Al Noor Pharmacy'),
                                        new OA\Property(property: 'region', type: 'string', nullable: true, example: 'Central Region'),
                                        new OA\Property(property: 'ordersCount', type: 'integer', example: 42),
                                        new OA\Property(property: 'totalSpent', type: 'number', format: 'float', example: 5200),
                                    ])
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - only suppliers and admins can access the dashboard'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isSupplier() && ! $user->isAdmin()) {
            return $this->error('Only suppliers and admins can access the dashboard', null, 403);
        }

        $days = min(max($request->integer('days', 7), 1), 90);
        $limit = min(max($request->integer('limit', 5), 1), 20);

        if ($user->isAdmin()) {
            $supplierCompanyId = $request->filled('supplier_company_id')
                ? $request->integer('supplier_company_id')
                : null;
        } else {
            $supplierCompanyId = $user->company_id;
        }

        $data = $this->dashboardService->supplierOverview($supplierCompanyId, $days, $limit);

        return $this->success($data, 'Dashboard data retrieved successfully');
    }
}
