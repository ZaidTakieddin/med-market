<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartCheckoutPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_current_price_not_stale_cart_price(): void
    {
        $supplier = Company::factory()->retail()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $buyerUser = User::factory()->buyer()->forCompany($buyer)->create();

        // Current price is 100; the cart was added at a stale 50.
        $product = Product::factory()->withStock(100)->create([
            'supplier_company_id' => $supplier->id,
            'price' => 100,
            'pharmacist_price' => 100,
        ]);

        $cart = Cart::create(['user_id' => $buyerUser->id, 'company_id' => $buyer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50, // stale
            'total_price' => 100,
            'pricing_type' => 'retail',
        ]);

        Sanctum::actingAs($buyerUser);

        $this->postJson('/api/v1/cart/checkout', [])->assertOk();

        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $item->unit_price, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $item->total_price, 0.001);
    }
}
