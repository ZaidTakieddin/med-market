<?php

namespace Tests\Feature\Orders;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesmanClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPricingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_supplied_unit_price_is_ignored_on_direct_order(): void
    {
        $supplier = Company::factory()->retail()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $buyerUser = User::factory()->buyer()->forCompany($buyer)->create();

        $product = Product::factory()->withStock(100)->create([
            'supplier_company_id' => $supplier->id,
            'price' => 100,
            'pharmacist_price' => 100,
        ]);

        Sanctum::actingAs($buyerUser);

        // Attempt to buy at 0.01/unit — the server must resolve the real price.
        $this->postJson('/api/v1/orders', [
            'supplier_company_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 0.01],
            ],
        ])->assertCreated();

        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $item->unit_price, 0.001);
    }

    public function test_client_supplied_unit_price_is_ignored_for_salesman_place_order(): void
    {
        $supplier = Company::factory()->retail()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $salesman = User::factory()->salesman()->forCompany($supplier)->create();
        SalesmanClient::factory()->create([
            'salesman_user_id' => $salesman->id,
            'salesman_company_id' => $supplier->id,
            'pharmacy_company_id' => $buyer->id,
        ]);

        $product = Product::factory()->withStock(100)->create([
            'supplier_company_id' => $supplier->id,
            'price' => 100,
            'pharmacist_price' => 100,
        ]);

        Sanctum::actingAs($salesman);

        $this->postJson("/api/v1/salesmen/{$salesman->id}/place-order", [
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 0.01],
            ],
        ])->assertCreated();

        $order = Order::firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $item->unit_price, 0.001);
        $this->assertSame($salesman->id, $order->placed_by_salesman_id);
    }
}
