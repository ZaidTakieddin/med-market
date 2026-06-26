<?php

namespace Tests\Feature\Dashboard;

use App\Models\Company;
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

    public function test_buyer_is_forbidden_from_dashboard(): void
    {
        $buyer = Company::factory()->pharmacy()->create();
        Sanctum::actingAs(User::factory()->buyer()->forCompany($buyer)->create());

        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }
}
