<?php

namespace App\Domain\Dashboard;

use App\Constants\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    /**
     * Build the supplier dashboard overview payload.
     *
     * When $supplierCompanyId is null the metrics span every supplier (admin view);
     * otherwise they are scoped to the given supplier company. Revenue-based metrics
     * (sales series, top products, top buyers) only count fulfilled (delivered) orders.
     *
     * @return array{
     *     kpis: list<array<string, mixed>>,
     *     salesSeries: list<array{day: string, sales: int}>,
     *     topProducts: list<array{id: int, name: string, stock: int, price: float, totalSold: int}>,
     *     topBuyers: list<array{id: int, name: string, region: string|null, ordersCount: int, totalSpent: float}>
     * }
     */
    public function supplierOverview(?int $supplierCompanyId, int $days = 7, int $limit = 5): array
    {
        return [
            'kpis' => $this->kpis($supplierCompanyId),
            'salesSeries' => $this->salesSeries($supplierCompanyId, $days),
            'topProducts' => $this->topProducts($supplierCompanyId, $limit),
            'topBuyers' => $this->topBuyers($supplierCompanyId, $limit),
        ];
    }

    /**
     * Headline KPI cards shaped for the frontend `DashboardKpi` contract.
     *
     * @return list<array<string, mixed>>
     */
    private function kpis(?int $supplierCompanyId): array
    {
        $now = Carbon::now();

        $pendingOrders = $this->scopedOrders($supplierCompanyId)
            ->where('status', OrderStatus::PENDING)
            ->count();

        $activeShipments = $this->scopedOrders($supplierCompanyId)
            ->where('status', OrderStatus::SHIPPED)
            ->count();

        $totalProducts = $this->scopedProducts($supplierCompanyId)
            ->where('is_active', true)
            ->count();

        $totalOrders = $this->scopedOrders($supplierCompanyId)->count();

        $shipmentsChange = $this->weekOverWeekChange($supplierCompanyId, 'shipped_at', $now);
        $ordersChange = $this->monthOverMonthChange($supplierCompanyId, 'created_at', $now);

        return [
            $this->kpi(
                id: 'pending_orders',
                title: 'الطلبات المعلقة',
                value: $this->formatNumber($pendingOrders),
                description: $pendingOrders > 0 ? 'يحتاج إلى انتباه' : 'لا توجد طلبات معلقة',
            ),
            $this->kpi(
                id: 'active_shipments',
                title: 'الشحنات النشطة',
                value: $this->formatNumber($activeShipments),
                description: 'مقارنة بالأسبوع الماضي',
                trend: $this->trend($shipmentsChange, goodWhenUp: true),
            ),
            $this->kpi(
                id: 'total_products',
                title: 'إجمالي المنتجات',
                value: $this->formatNumber($totalProducts),
                description: 'الكتالوج النشط',
            ),
            $this->kpi(
                id: 'total_orders',
                title: 'إجمالي الطلبات',
                value: $this->formatNumber($totalOrders),
                description: 'منذ الشهر الماضي',
                trend: $this->trend($ordersChange, goodWhenUp: true),
            ),
        ];
    }

    /**
     * Assemble a single KPI card, omitting the trend when there is none.
     *
     * @param  array{value: string, direction: string, tone: string}|null  $trend
     * @return array<string, mixed>
     */
    private function kpi(string $id, string $title, string $value, string $description, ?array $trend = null): array
    {
        $kpi = [
            'id' => $id,
            'title' => $title,
            'value' => $value,
            'description' => $description,
        ];

        if ($trend !== null) {
            $kpi['trend'] = $trend;
        }

        return $kpi;
    }

    /**
     * Map a normalized change into the frontend trend shape (signed value + tone).
     *
     * @param  array{percent: float, direction: string, period: string}  $change
     * @return array{value: string, direction: string, tone: string}
     */
    private function trend(array $change, bool $goodWhenUp): array
    {
        $sign = match ($change['direction']) {
            'up' => '+',
            'down' => '-',
            default => '',
        };

        return [
            'value' => $sign.$change['percent'].'%',
            'direction' => $change['direction'],
            'tone' => $this->tone($change['direction'], $goodWhenUp),
        ];
    }

    /**
     * Resolve a trend tone: a move in the "good" direction is positive, otherwise danger.
     */
    private function tone(string $direction, bool $goodWhenUp): string
    {
        if ($direction === 'flat') {
            return 'neutral';
        }

        return (($direction === 'up') === $goodWhenUp) ? 'positive' : 'danger';
    }

    /**
     * Number of fulfilled (delivered) orders per day for the trailing $days window
     * (zero-filled), oldest first.
     *
     * @return list<array{day: string, sales: int}>
     */
    private function salesSeries(?int $supplierCompanyId, int $days): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $countsByDay = $this->scopedOrders($supplierCompanyId)
            ->where('status', OrderStatus::DELIVERED)
            ->where('delivered_at', '>=', $start)
            ->selectRaw('DATE(delivered_at) as day, COUNT(*) as orders_count')
            ->groupBy('day')
            ->pluck('orders_count', 'day');

        $series = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset);

            $series[] = [
                'day' => $date->format('D'),
                'sales' => (int) ($countsByDay[$date->toDateString()] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Format an integer metric for display (thousands separated).
     */
    private function formatNumber(int $value): string
    {
        return number_format($value);
    }

    /**
     * Best-selling products by quantity sold across fulfilled (delivered) orders.
     *
     * @return list<array{id: int, name: string, stock: int, price: float, totalSold: int}>
     */
    private function topProducts(?int $supplierCompanyId, int $limit): array
    {
        $soldFilter = function (Builder $query): void {
            $query->whereHas('order', function (Builder $orderQuery): void {
                $orderQuery->where('status', OrderStatus::DELIVERED);
            });
        };

        $products = $this->scopedProducts($supplierCompanyId)
            ->whereHas('orderItems', $soldFilter)
            ->withSum(['orderItems as total_sold' => $soldFilter], 'quantity')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($products as $product) {
            $result[] = [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => (int) $product->stock_quantity,
                'price' => round((float) $product->price, 2),
                'totalSold' => (int) $product->getAttribute('total_sold'),
            ];
        }

        return $result;
    }

    /**
     * Top buyers by total amount spent across fulfilled (delivered) orders.
     *
     * @return list<array{id: int, name: string, region: string|null, ordersCount: int, totalSpent: float}>
     */
    private function topBuyers(?int $supplierCompanyId, int $limit): array
    {
        $rows = $this->scopedOrders($supplierCompanyId)
            ->where('orders.status', OrderStatus::DELIVERED)
            ->join('companies', 'companies.id', '=', 'orders.buyer_company_id')
            ->leftJoin('regions', 'regions.id', '=', 'companies.region_id')
            ->whereNull('companies.deleted_at')
            ->selectRaw('companies.id as buyer_id, companies.name as buyer_name, regions.name as region_name, COUNT(*) as orders_count, SUM(orders.total_amount) as total_spent')
            ->groupBy('companies.id', 'companies.name', 'regions.name')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        $buyers = [];
        foreach ($rows as $row) {
            $region = $row->getAttribute('region_name');

            $buyers[] = [
                'id' => (int) $row->getAttribute('buyer_id'),
                'name' => (string) $row->getAttribute('buyer_name'),
                'region' => $region !== null ? (string) $region : null,
                'ordersCount' => (int) $row->getAttribute('orders_count'),
                'totalSpent' => round((float) $row->getAttribute('total_spent'), 2),
            ];
        }

        return $buyers;
    }

    /**
     * Week-over-week change for the count of orders timestamped on $column.
     *
     * @return array{percent: float, direction: string, period: string}
     */
    private function weekOverWeekChange(?int $supplierCompanyId, string $column, CarbonInterface $now): array
    {
        $thisWeekStart = $now->copy()->startOfWeek();
        $lastWeekStart = $thisWeekStart->copy()->subWeek();

        $current = $this->scopedOrders($supplierCompanyId)
            ->where($column, '>=', $thisWeekStart)
            ->count();

        $previous = $this->scopedOrders($supplierCompanyId)
            ->whereBetween($column, [$lastWeekStart, $thisWeekStart])
            ->count();

        return $this->buildChange($current, $previous, 'week');
    }

    /**
     * Month-over-month change for the count of orders timestamped on $column.
     *
     * @return array{percent: float, direction: string, period: string}
     */
    private function monthOverMonthChange(?int $supplierCompanyId, string $column, CarbonInterface $now): array
    {
        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $thisMonthStart->copy()->subMonth();

        $current = $this->scopedOrders($supplierCompanyId)
            ->where($column, '>=', $thisMonthStart)
            ->count();

        $previous = $this->scopedOrders($supplierCompanyId)
            ->whereBetween($column, [$lastMonthStart, $thisMonthStart])
            ->count();

        return $this->buildChange($current, $previous, 'month');
    }

    /**
     * Normalize a current-vs-previous comparison into a percent + direction.
     *
     * @return array{percent: float, direction: string, period: string}
     */
    private function buildChange(int $current, int $previous, string $period): array
    {
        if ($previous === 0) {
            $percent = $current > 0 ? 100.0 : 0.0;
        } else {
            $percent = round((($current - $previous) / $previous) * 100, 1);
        }

        $direction = match (true) {
            $percent > 0 => 'up',
            $percent < 0 => 'down',
            default => 'flat',
        };

        return [
            'percent' => abs($percent),
            'direction' => $direction,
            'period' => $period,
        ];
    }

    /**
     * Order query scoped to a supplier (or all suppliers for admin).
     *
     * @return Builder<Order>
     */
    private function scopedOrders(?int $supplierCompanyId): Builder
    {
        return Order::query()
            ->when($supplierCompanyId !== null, fn (Builder $query) => $query->where('supplier_company_id', $supplierCompanyId));
    }

    /**
     * Product query scoped to a supplier (or all suppliers for admin).
     *
     * @return Builder<Product>
     */
    private function scopedProducts(?int $supplierCompanyId): Builder
    {
        return Product::query()
            ->when($supplierCompanyId !== null, fn (Builder $query) => $query->where('supplier_company_id', $supplierCompanyId));
    }
}
