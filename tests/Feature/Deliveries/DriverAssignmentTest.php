<?php

namespace Tests\Feature\Deliveries;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_assigns_own_driver_to_own_order(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $driver = User::factory()->driver()->forCompany($supplier)->create();
        $order = Order::factory()->pending()->create(['supplier_company_id' => $supplier->id]);

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/orders/{$order->id}/assign-driver", [
            'driver_id' => $driver->id,
        ])->assertOk();

        $this->assertSame($driver->id, $order->fresh()->driver_id);
    }

    public function test_buyer_cannot_assign_driver(): void
    {
        [$supplier, , $order, , $buyerUser] = $this->makeScenario();

        Sanctum::actingAs($buyerUser);

        $this->postJson("/api/v1/orders/{$order->id}/assign-driver", [
            'driver_id' => User::factory()->driver()->forCompany($supplier)->create()->id,
        ])->assertForbidden();
    }

    public function test_supplier_cannot_assign_driver_to_another_suppliers_order(): void
    {
        $otherSupplierUser = User::factory()->supplier()->create();
        [, , $order, $driver] = $this->makeScenario();

        Sanctum::actingAs($otherSupplierUser);

        $this->postJson("/api/v1/orders/{$order->id}/assign-driver", [
            'driver_id' => $driver->id,
        ])->assertForbidden();
    }

    public function test_assigning_a_non_driver_user_is_rejected_with_422(): void
    {
        [$supplier, $supplierUser, $order] = $this->makeScenario();
        $notADriver = User::factory()->supplier()->forCompany($supplier)->create();

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/orders/{$order->id}/assign-driver", [
            'driver_id' => $notADriver->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull($order->fresh()->driver_id);
    }

    public function test_assigning_a_driver_from_another_company_is_rejected_with_422(): void
    {
        [, $supplierUser, $order] = $this->makeScenario();
        $foreignDriver = User::factory()->driver()->forCompany(Company::factory()->supplier()->create())->create();

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/orders/{$order->id}/assign-driver", [
            'driver_id' => $foreignDriver->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull($order->fresh()->driver_id);
    }

    public function test_admin_can_assign_any_driver(): void
    {
        [$supplier, , $order] = $this->makeScenario();
        $driver = User::factory()->driver()->forCompany($supplier)->create();
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/orders/{$order->id}/assign-driver", [
            'driver_id' => $driver->id,
        ])->assertOk();

        $this->assertSame($driver->id, $order->fresh()->driver_id);
    }

    /**
     * @return array{0: Company, 1: User, 2: Order, 3: User, 4: User}
     */
    private function makeScenario(): array
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $buyer = Company::factory()->pharmacy()->create();
        $buyerUser = User::factory()->buyer()->forCompany($buyer)->create();
        $order = Order::factory()->pending()->create([
            'supplier_company_id' => $supplier->id,
            'buyer_company_id' => $buyer->id,
        ]);
        $driver = User::factory()->driver()->forCompany($supplier)->create();

        return [$supplier, $supplierUser, $order, $driver, $buyerUser];
    }
}
