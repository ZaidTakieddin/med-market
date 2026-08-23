<?php

namespace App\Domain\Dashboard;

use App\Constants\CompanyType;
use App\Constants\OrderStatus;
use App\Constants\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Platform-wide totals and revenue analytics (admin).
     *
     * @return array{
     *     totals: array{suppliers: int, buyers: int, users: int, activeProducts: int, orders: int},
     *     ordersByStatus: array<string, int>,
     *     deliveredRevenue: float,
     *     avgDeliveredOrderValue: float
     * }
     */
    public function overview(): array
    {
        $deliveredQuery = $this->orderQuery()->where('status', OrderStatus::DELIVERED);

        $deliveredRevenue = (float) (clone $deliveredQuery)->sum('total_amount');
        $deliveredCount = (int) (clone $deliveredQuery)->count();

        return [
            'totals' => [
                'suppliers' => Company::query()->where('type', CompanyType::SUPPLIER)->count(),
                'buyers' => Company::query()->where('type', CompanyType::BUYER)->count(),
                'users' => User::query()->count(),
                'activeProducts' => Product::query()->where('is_active', true)->count(),
                'orders' => $this->orderQuery()->count(),
            ],
            'ordersByStatus' => $this->orderQuery()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($value): int => (int) $value)
                ->all(),
            'deliveredRevenue' => round($deliveredRevenue, 2),
            'avgDeliveredOrderValue' => $deliveredCount > 0 ? round($deliveredRevenue / $deliveredCount, 2) : 0.0,
        ];
    }

    /**
     * Order trend series over a trailing window bucketed per day/week/month,
     * zero-filled oldest first. "orders" counts every order created in the bucket;
     * "revenue" sums delivered orders only.
     *
     * @return list<array{period: string, orders: int, revenue: float}>
     */
    public function orderTrends(int $days, string $granularity = 'daily'): array
    {
        [$start, $step] = match ($granularity) {
            'weekly' => [Carbon::now()->subWeeks($days - 1)->startOfWeek(), 'week'],
            'monthly' => [Carbon::now()->subMonths($days - 1)->startOfMonth(), 'month'],
            default => [Carbon::now()->subDays($days - 1)->startOfDay(), 'day'],
        };

        /** @var Collection<int, Order> $rows */
        $rows = $this->orderQuery()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'status', 'total_amount']);

        $counts = [];
        $revenue = [];
        foreach ($rows as $order) {
            $key = $this->bucketKey($order->created_at, $granularity);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            if ($order->status === OrderStatus::DELIVERED) {
                $revenue[$key] = ($revenue[$key] ?? 0) + (float) $order->total_amount;
            }
        }

        $series = [];
        $cursor = $start->copy();
        for ($i = 0; $i < $days; $i++) {
            $key = $this->bucketKey($cursor, $granularity);

            $series[] = [
                'period' => $cursor->toDateString(),
                'orders' => $counts[$key] ?? 0,
                'revenue' => round($revenue[$key] ?? 0, 2),
            ];

            match ($step) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
                default => $cursor->addDay(),
            };
        }

        return $series;
    }

    /**
     * Per-driver delivery performance: delivered count, active assignments and the
     * average shipped→delivered duration in hours.
     *
     * @return list<array{id: int, name: string, company: string|null, deliveredOrders: int, activeAssignments: int, avgDeliveryHours: float|null}>
     */
    public function driverPerformance(int $limit = 50): array
    {
        $drivers = User::query()
            ->where('role', UserRole::DRIVER)
            ->with('company:id,name')
            ->withCount(['driverOrders as delivered_orders' => fn (Builder $q) => $q->where('status', OrderStatus::DELIVERED)])
            ->withCount(['driverOrders as active_assignments' => fn (Builder $q) => $q->where('status', OrderStatus::SHIPPED)])
            ->orderByDesc('delivered_orders')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($drivers as $driver) {
            $result[] = [
                'id' => $driver->id,
                'name' => $driver->name,
                'company' => $driver->company?->name,
                'deliveredOrders' => (int) $driver->getAttribute('delivered_orders'),
                'activeAssignments' => (int) $driver->getAttribute('active_assignments'),
                'avgDeliveryHours' => $this->avgDeliveryHours((int) $driver->id),
            ];
        }

        return $result;
    }

    /**
     * Average hours from shipped_at to delivered_at across a driver's delivered orders.
     */
    private function avgDeliveryHours(int $driverId): ?float
    {
        $orders = $this->orderQuery()
            ->where('driver_id', $driverId)
            ->where('status', OrderStatus::DELIVERED)
            ->whereNotNull(['shipped_at', 'delivered_at'])
            ->get(['shipped_at', 'delivered_at']);

        if ($orders->isEmpty()) {
            return null;
        }

        $totalHours = $orders->sum(fn (Order $order): float => $order->shipped_at->diffInHours($order->delivered_at));

        return round($totalHours / $orders->count(), 1);
    }

    private function bucketKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'weekly' => $date->copy()->startOfWeek()->toDateString(),
            'monthly' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    /**
     * @return Builder<Order>
     */
    private function orderQuery(): Builder
    {
        return Order::query();
    }
}
