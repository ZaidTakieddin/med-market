<?php

namespace Tests\Feature\Dashboard;

use App\Constants\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_platform_overview(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $creator = User::factory()->buyer()->forCompany($buyer)->create();
        $driver = User::factory()->driver()->forCompany($supplier)->create();

        Order::factory()->delivered()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'created_by' => $creator->id,
            'driver_id' => $driver->id,
            'total_amount' => 400,
            'subtotal' => 400,
        ]);
        Order::factory()->pending()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'created_by' => $creator->id,
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/analytics/overview')->assertOk();

        $this->assertSame(1, $response->json('data.totals.suppliers'));
        $this->assertSame(1, $response->json('data.totals.buyers'));
        $this->assertSame(2, $response->json('data.totals.orders'));
        $this->assertSame(1, $response->json('data.ordersByStatus.'.OrderStatus::PENDING));
        $this->assertSame(1, $response->json('data.ordersByStatus.'.OrderStatus::DELIVERED));
        $this->assertEqualsWithDelta(400.0, $response->json('data.deliveredRevenue'), 0.001);
        $this->assertEqualsWithDelta(400.0, $response->json('data.avgDeliveredOrderValue'), 0.001);
    }

    public function test_order_trends_bucket_daily_and_count_revenue_for_delivered_only(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();

        Order::factory()->delivered()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'total_amount' => 250,
            'subtotal' => 250,
        ]);
        Order::factory()->pending()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/analytics/orders?days=7')->assertOk();

        $series = collect($response->json('data.series'));
        $this->assertCount(7, $series);
        $today = $series->last();
        $this->assertSame(now()->toDateString(), $today['period']);
        $this->assertSame(2, $today['orders']);
        $this->assertEqualsWithDelta(250.0, $today['revenue'], 0.001); // pending order adds no revenue
    }

    public function test_driver_performance_lists_delivered_and_active_counts(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $driver = User::factory()->driver()->forCompany($supplier)->create();

        Order::factory()->delivered()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'driver_id' => $driver->id,
            'shipped_at' => now()->subHours(5),
            'delivered_at' => now(),
        ]);
        Order::factory()->shipped()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'driver_id' => $driver->id,
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/analytics/drivers')->assertOk();

        $row = collect($response->json('data.drivers'))->firstWhere('id', $driver->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['deliveredOrders']);
        $this->assertSame(1, $row['activeAssignments']);
        $this->assertEqualsWithDelta(5.0, $row['avgDeliveryHours'], 0.5);
    }

    public function test_non_admin_is_forbidden_from_analytics(): void
    {
        Sanctum::actingAs(User::factory()->supplier()->create());

        $this->getJson('/api/v1/analytics/overview')->assertForbidden();
        $this->getJson('/api/v1/analytics/orders')->assertForbidden();
        $this->getJson('/api/v1/analytics/drivers')->assertForbidden();
    }
}
