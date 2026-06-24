<?php

namespace Tests\Feature\Orders;

use App\Constants\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: Company, 2: Order}
     */
    private function pendingOrder(): array
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $product = Product::factory()->withStock(100)->create(['supplier_company_id' => $supplier->id]);

        $order = Order::factory()->pending()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        return [$supplier, $buyer, $order];
    }

    public function test_buyer_cannot_accept_their_own_order(): void
    {
        [$supplier, $buyer, $order] = $this->pendingOrder();
        $buyerUser = User::factory()->buyer()->forCompany($buyer)->create();

        Sanctum::actingAs($buyerUser);

        $this->postJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::ACCEPTED])
            ->assertForbidden();

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_supplier_can_accept_an_order(): void
    {
        [$supplier, $buyer, $order] = $this->pendingOrder();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::ACCEPTED])
            ->assertOk();

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
    }

    public function test_supplier_cannot_accept_another_suppliers_order(): void
    {
        [$supplier, $buyer, $order] = $this->pendingOrder();
        $otherSupplierUser = User::factory()->supplier()->create();

        Sanctum::actingAs($otherSupplierUser);

        $this->postJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::ACCEPTED])
            ->assertForbidden();

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_buyer_can_still_cancel_their_pending_order_via_status(): void
    {
        [$supplier, $buyer, $order] = $this->pendingOrder();
        $buyerUser = User::factory()->buyer()->forCompany($buyer)->create();

        Sanctum::actingAs($buyerUser);

        $this->postJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::CANCELLED])
            ->assertOk();

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
    }
}
