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
     *     salesSeries: list<array{date: string, sales: int}>,
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
            'regionsServed' => $this->regionsServed($supplierCompanyId, $limit),
        ];
    }

    /**
     * Regions served: fulfilled-order footprint grouped by the buyer's region,
     * ranked by spend.
     *
     * @return list<array{region: string|null, city: string|null, ordersCount: int, totalSpent: float}>
     */
    private function regionsServed(?int $supplierCompanyId, int $limit): array
    {
        $rows = $this->scopedOrders($supplierCompanyId)
            ->where('orders.status', OrderStatus::DELIVERED)
            ->join('companies as buyers', 'buyers.id', '=', 'orders.buyer_company_id')
            ->leftJoin('regions', 'regions.id', '=', 'buyers.region_id')
            ->leftJoin('cities', 'cities.id', '=', 'regions.city_id')
            ->whereNull('buyers.deleted_at')
            ->selectRaw("COALESCE(regions.name, 'غير محدد') as region_name, COALESCE(cities.name, '') as city_name, COUNT(*) as orders_count, SUM(orders.total_amount) as total_spent")
            ->groupBy('regions.name', 'cities.name')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'region' => (string) $row->getAttribute('region_name'),
                'city' => $row->getAttribute('city_name') !== '' ? (string) $row->getAttribute('city_name') : null,
                'ordersCount' => (int) $row->getAttribute('orders_count'),
                'totalSpent' => round((float) $row->getAttribute('total_spent'), 2),
            ];
        }

        return $result;
    }

    /**
     * Build the buyer dashboard overview payload.
     *
     * All metrics are scoped to the buying company (pharmacy or salesman buyer).
     * Spend-based metrics only count fulfilled (delivered) orders.
     *
     * @return array{
     *     kpis: list<array<string, mixed>>,
     *     spendSeries: list<array{date: string, spend: float}>,
     *     topSuppliers: list<array{id: int, name: string, region: string|null, ordersCount: int, totalSpent: float}>,
     *     topProducts: list<array{id: int, name: string, totalUnits: int, totalSpent: float}>
     * }
     */
    public function buyerOverview(int $buyerCompanyId, int $days = 7, int $limit = 5): array
    {
        return [
            'kpis' => $this->buyerKpis($buyerCompanyId),
            'spendSeries' => $this->spendSeries($buyerCompanyId, $days),
            'topSuppliers' => $this->topSuppliers($buyerCompanyId, $limit),
            'topProducts' => $this->topPurchasedProducts($buyerCompanyId, $limit),
        ];
    }

    /**
     * Buyer headline KPI cards shaped for the frontend `DashboardKpi` contract.
     *
     * @return list<array<string, mixed>>
     */
    private function buyerKpis(int $buyerCompanyId): array
    {
        $now = Carbon::now();

        $pendingOrders = $this->scopedBuyerOrders($buyerCompanyId)
            ->where('status', OrderStatus::PENDING)
            ->count();

        $activeShipments = $this->scopedBuyerOrders($buyerCompanyId)
            ->where('status', OrderStatus::SHIPPED)
            ->count();

        $totalOrders = $this->scopedBuyerOrders($buyerCompanyId)->count();

        $totalSpent = (float) $this->scopedBuyerOrders($buyerCompanyId)
            ->where('status', OrderStatus::DELIVERED)
            ->sum('total_amount');

        $ordersChange = $this->monthOverMonthChangeFor(
            $this->scopedBuyerOrders($buyerCompanyId),
            'created_at',
            $now
        );

        return [
            $this->kpi(
                id: 'total_orders',
                title: 'إجمالي الطلبات',
                value: $this->formatNumber($totalOrders),
                description: 'منذ الشهر الماضي',
                trend: $this->trend($ordersChange, goodWhenUp: true),
            ),
            $this->kpi(
                id: 'pending_orders',
                title: 'الطلبات المعلقة',
                value: $this->formatNumber($pendingOrders),
                description: $pendingOrders > 0 ? 'في انتظار قبول المورّد' : 'لا توجد طلبات معلقة',
            ),
            $this->kpi(
                id: 'active_shipments',
                title: 'شحنات قيد التوصيل',
                value: $this->formatNumber($activeShipments),
                description: 'قيد الطريق إليك',
            ),
            $this->kpi(
                id: 'total_spent',
                title: 'إجمالي الإنفاق',
                value: $this->formatMoney($totalSpent),
                description: 'على الطلبات المسلَّمة',
            ),
        ];
    }

    /**
     * Daily spend across fulfilled (delivered) orders for the trailing $days window
     * (zero-filled), oldest first.
     *
     * @return list<array{date: string, spend: float}>
     */
    private function spendSeries(int $buyerCompanyId, int $days): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $spendByDay = $this->scopedBuyerOrders($buyerCompanyId)
            ->where('status', OrderStatus::DELIVERED)
            ->where('delivered_at', '>=', $start)
            ->selectRaw('DATE(delivered_at) as day, SUM(total_amount) as daily_spend')
            ->groupBy('day')
            ->pluck('daily_spend', 'day');

        $series = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset);

            $series[] = [
                'date' => $date->toDateString(),
                'spend' => round((float) ($spendByDay[$date->toDateString()] ?? 0), 2),
            ];
        }

        return $series;
    }

    /**
     * Top suppliers by total amount spent across fulfilled (delivered) orders.
     *
     * @return list<array{id: int, name: string, region: string|null, ordersCount: int, totalSpent: float}>
     */
    private function topSuppliers(int $buyerCompanyId, int $limit): array
    {
        $rows = $this->scopedBuyerOrders($buyerCompanyId)
            ->where('orders.status', OrderStatus::DELIVERED)
            ->join('companies', 'companies.id', '=', 'orders.supplier_company_id')
            ->leftJoin('regions', 'regions.id', '=', 'companies.region_id')
            ->whereNull('companies.deleted_at')
            ->selectRaw('companies.id as supplier_id, companies.name as supplier_name, regions.name as region_name, COUNT(*) as orders_count, SUM(orders.total_amount) as total_spent')
            ->groupBy('companies.id', 'companies.name', 'regions.name')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        $suppliers = [];
        foreach ($rows as $row) {
            $region = $row->getAttribute('region_name');

            $suppliers[] = [
                'id' => (int) $row->getAttribute('supplier_id'),
                'name' => (string) $row->getAttribute('supplier_name'),
                'region' => $region !== null ? (string) $region : null,
                'ordersCount' => (int) $row->getAttribute('orders_count'),
                'totalSpent' => round((float) $row->getAttribute('total_spent'), 2),
            ];
        }

        return $suppliers;
    }

    /**
     * Most-purchased products by units across fulfilled (delivered) orders.
     *
     * @return list<array{id: int, name: string, totalUnits: int, totalSpent: float}>
     */
    private function topPurchasedProducts(int $buyerCompanyId, int $limit): array
    {
        $rows = \App\Models\OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.buyer_company_id', $buyerCompanyId)
            ->where('orders.status', OrderStatus::DELIVERED)
            ->whereNull('products.deleted_at')
            ->selectRaw('products.id as product_id, products.name as product_name, SUM(order_items.quantity) as total_units, SUM(order_items.total_price) as total_spent')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_units')
            ->limit($limit)
            ->get();

        $products = [];
        foreach ($rows as $row) {
            $products[] = [
                'id' => (int) $row->getAttribute('product_id'),
                'name' => (string) $row->getAttribute('product_name'),
                'totalUnits' => (int) $row->getAttribute('total_units'),
                'totalSpent' => round((float) $row->getAttribute('total_spent'), 2),
            ];
        }

        return $products;
    }

    /**
     * Order query scoped to a buying company.
     *
     * @return Builder<Order>
     */
    private function scopedBuyerOrders(int $buyerCompanyId): Builder
    {
        return Order::query()->where('buyer_company_id', $buyerCompanyId);
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
     * Resolve a trend tone: a move in the "good" direction is positive, a move against
     * it is danger, and no change is warning. Tone is always one of
     * positive/warning/danger.
     */
    private function tone(string $direction, bool $goodWhenUp): string
    {
        if ($direction === 'flat') {
            return 'warning';
        }

        return (($direction === 'up') === $goodWhenUp) ? 'positive' : 'danger';
    }

    /**
     * Number of fulfilled (delivered) orders per day for the trailing $days window
     * (zero-filled), oldest first. Each entry is keyed by its ISO date (Y-m-d) so
     * the frontend can render a continuous date-ranged chart.
     *
     * @return list<array{date: string, sales: int}>
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
                'date' => $date->toDateString(),
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
     * Format a money metric for display (thousands separated, two decimals).
     */
    private function formatMoney(float $value): string
    {
        return number_format($value, 2);
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
        return $this->monthOverMonthChangeFor($this->scopedOrders($supplierCompanyId), $column, $now);
    }

    /**
     * Month-over-month change over an arbitrary (already-scoped) order query.
     *
     * @param  Builder<Order>  $query
     * @return array{percent: float, direction: string, period: string}
     */
    private function monthOverMonthChangeFor(Builder $query, string $column, CarbonInterface $now): array
    {
        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $thisMonthStart->copy()->subMonth();

        $current = (clone $query)
            ->where($column, '>=', $thisMonthStart)
            ->count();

        $previous = (clone $query)
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
