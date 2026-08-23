<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboard\AnalyticsService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Analytics', description: 'Platform analytics (admin only): system-wide statistics, order trends and driver performance.')]
class AnalyticsController extends BaseApiController
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    /**
     * Platform overview.
     */
    #[OA\Get(
        path: '/analytics/overview',
        summary: 'Platform overview',
        description: 'System-wide statistics: company/user/product/order totals, orders grouped by status, delivered revenue and average delivered order value. Admin only.',
        tags: ['Analytics'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Overview retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function overview(): JsonResponse
    {
        if (! $this->currentUser()->isAdmin()) {
            return $this->error('Only admins can access platform analytics', null, 403);
        }

        return $this->success(
            $this->analyticsService->overview(),
            'Analytics overview retrieved successfully'
        );
    }

    /**
     * Order trends.
     */
    #[OA\Get(
        path: '/analytics/orders',
        summary: 'Order trends',
        description: 'Trailing-window order trend series bucketed per day (default), week or month, zero-filled oldest first. "orders" counts all orders created in the bucket; "revenue" sums delivered orders only. Admin only.',
        tags: ['Analytics'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'days', in: 'query', schema: new OA\Schema(type: 'integer', default: 30, minimum: 1, maximum: 365), description: 'Window size in units of the chosen granularity'),
            new OA\Parameter(name: 'granularity', in: 'query', schema: new OA\Schema(type: 'string', enum: ['daily', 'weekly', 'monthly'], default: 'daily')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trends retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function orderTrends(Request $request): JsonResponse
    {
        if (! $this->currentUser()->isAdmin()) {
            return $this->error('Only admins can access platform analytics', null, 403);
        }

        $days = min(max($request->integer('days', 30), 1), 365);
        $granularity = in_array($request->input('granularity'), ['daily', 'weekly', 'monthly'], true)
            ? (string) $request->input('granularity')
            : 'daily';

        return $this->success([
            'granularity' => $granularity,
            'series' => $this->analyticsService->orderTrends($days, $granularity),
        ], 'Order trends retrieved successfully');
    }

    /**
     * Driver performance.
     */
    #[OA\Get(
        path: '/analytics/drivers',
        summary: 'Driver performance metrics',
        description: 'Per-driver delivery performance across the platform: delivered orders, currently-active (shipped) assignments, and average shipped→delivered duration in hours. Admin only.',
        tags: ['Analytics'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Driver performance retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function drivers(): JsonResponse
    {
        if (! $this->currentUser()->isAdmin()) {
            return $this->error('Only admins can access platform analytics', null, 403);
        }

        return $this->success(
            ['drivers' => $this->analyticsService->driverPerformance()],
            'Driver performance retrieved successfully'
        );
    }
}
