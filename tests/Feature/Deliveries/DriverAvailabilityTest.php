<?php

namespace Tests\Feature\Deliveries;

use App\Constants\DriverStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_endpoint_updates_driver(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $driver = User::factory()->driver()->forCompany($supplier)->create();

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/drivers/{$driver->id}/status", [
            'driver_status' => DriverStatus::OFF,
        ])->assertOk()->assertJsonPath('data.driverStatus', DriverStatus::OFF);

        $this->assertSame(DriverStatus::OFF, $driver->fresh()->driver_status);
    }

    public function test_available_list_excludes_at_capacity_and_off_drivers(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $buyer = Company::factory()->pharmacy()->create();

        $free = User::factory()->driver()->forCompany($supplier)->create();
        $busy = User::factory()->driver()->forCompany($supplier)->create();
        $off = User::factory()->driver()->forCompany($supplier)->create(['driver_status' => DriverStatus::OFF]);

        // Fill the busy driver to capacity (default 5).
        for ($i = 0; $i < 5; $i++) {
            Order::factory()->shipped()->create([
                'supplier_company_id' => $supplier->id,
                'buyer_company_id' => $buyer->id,
                'driver_id' => $busy->id,
            ]);
        }

        Sanctum::actingAs($supplierUser);

        $resp = $this->getJson('/api/v1/drivers/available');
        dump($resp->json());
        $ids = collect($resp->json('data'))->pluck('id');
        $this->assertContains($free->id, $ids);
        $this->assertNotContains($busy->id, $ids);
        $this->assertNotContains($off->id, $ids);
    }

    public function test_suggested_driver_is_least_loaded_same_company_available(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();

        $loaded = User::factory()->driver()->forCompany($supplier)->create();
        $idle = User::factory()->driver()->forCompany($supplier)->create();

        Order::factory()->shipped()->create([
            'supplier_company_id' => $supplier->id,
            'buyer_company_id' => $buyer->id,
            'driver_id' => $loaded->id,
        ]);

        $order = Order::factory()->prepared()->create([
            'supplier_company_id' => $supplier->id,
            'buyer_company_id' => $buyer->id,
        ]);

        Sanctum::actingAs($supplierUser);

        $response = $this->getJson("/api/v1/orders/{$order->id}/suggested-driver")->assertOk();
        $this->assertSame($idle->id, $response->json('data.driver.id'));
    }
}
