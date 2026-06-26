<?php

namespace Tests\Feature\Exceptions;

use App\Constants\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Domain business-rule violations (DomainException) are no longer caught per-controller;
 * the central Handler renders them as 422 with the standardized error body. This guards
 * that contract end-to-end.
 */
class DomainExceptionRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_order_transition_renders_as_422_via_handler(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $buyer = Company::factory()->pharmacy()->create();

        // A delivered order is final; advancing it back to "accepted" is an invalid
        // transition, so OrderStateTransitionService throws a DomainException.
        $order = Order::factory()->create([
            'supplier_company_id' => $supplier->id,
            'buyer_company_id' => $buyer->id,
            'status' => OrderStatus::DELIVERED,
        ]);

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::ACCEPTED])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['error' => ['title', 'message']]);
    }
}
