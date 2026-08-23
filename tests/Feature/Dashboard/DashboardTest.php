<?php

namespace Tests\Feature\Dashboard;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_gets_dashboard_overview(): void
    {
        $supplier = Company::factory()->supplier()->create();
        Sanctum::actingAs(User::factory()->supplier()->forCompany($supplier)->create());

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['kpis', 'salesSeries', 'topProducts', 'topBuyers'],
            ]);
    }

    public function test_buyer_gets_purchase_analytics_overview(): void
    {
        $buyer = Company::factory()->pharmacy()->create();
        $supplier = Company::factory()->supplier()->create();

        Order::factory()->delivered()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'total_amount' => 500,
            'subtotal' => 500,
        ]);
        Order::factory()->pending()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
        ]);
        Order::factory()->shipped()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
        ]);

        Sanctum::actingAs(User::factory()->buyer()->forCompany($buyer)->create());

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $response->assertJsonStructure([
            'data' => ['kpis', 'spendSeries', 'topSuppliers', 'topProducts'],
        ]);

        $this->assertSame('3', collect($response->json('data.kpis'))
            ->firstWhere('id', 'total_orders')['value']);
        $this->assertSame('500.00', collect($response->json('data.kpis'))
            ->firstWhere('id', 'total_spent')['value']);

        // Spend series is zero-filled across the trailing window and contains today.
        $series = $response->json('data.spendSeries');
        $this->assertCount(7, $series); // default ?days=7
        $today = now()->toDateString();
        $todaySpend = collect($series)->firstWhere('date', $today);
        $this->assertNotNull($todaySpend);

        $suppliers = $response->json('data.topSuppliers');
        $this->assertCount(1, $suppliers);
        $this->assertSame($supplier->id, $suppliers[0]['id']);
        $this->assertSame(1, $suppliers[0]['ordersCount']); // only delivered counts
    }

    public function test_independent_salesman_sees_buyer_analytics_for_own_company(): void
    {
        $salesmanBuyer = Company::factory()->salesman()->create();
        Sanctum::actingAs(User::factory()->salesman()->forCompany($salesmanBuyer)->create());

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['spendSeries', 'topSuppliers']]);
    }

    public function test_supplier_salesman_sees_supplier_overview_of_own_company(): void
    {
        $supplier = Company::factory()->supplier()->create();
        Sanctum::actingAs(User::factory()->salesman()->forCompany($supplier)->create());

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['salesSeries', 'topBuyers']]);
    }

    public function test_driver_is_forbidden_from_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->driver()->create());

        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_admin_gets_global_supplier_overview(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['kpis', 'salesSeries', 'topProducts', 'topBuyers']]);
    }

    public function test_days_parameter_is_clamped_and_respected(): void
    {
        $buyer = Company::factory()->pharmacy()->create();
        Sanctum::actingAs(User::factory()->buyer()->forCompany($buyer)->create());

        $this->getJson('/api/v1/dashboard?days=200')
            ->assertOk()
            ->assertJsonCount(90, 'data.spendSeries');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }
}
