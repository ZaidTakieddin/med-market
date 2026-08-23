<?php

namespace Tests\Feature\Orders;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderTemplate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_creates_template_and_company_users_share_it(): void
    {
        $buyer = Company::factory()->pharmacy()->create();
        $owner = User::factory()->buyer()->forCompany($buyer)->create();
        $colleague = User::factory()->buyer()->forCompany($buyer)->create();
        $productA = Product::factory()->withStock(100)->create(['price' => 20, 'pharmacist_price' => 20]);
        $productB = Product::factory()->withStock(100)->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/order-templates', [
            'name' => 'Monthly restock',
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 5],
                ['product_id' => $productB->id, 'quantity' => 2],
            ],
        ])->assertCreated()->assertJsonPath('data.name', 'Monthly restock');

        // Colleague sees the same template (company-shared).
        Sanctum::actingAs($colleague);
        $list = collect($this->getJson('/api/v1/order-templates')->json('data'));
        $this->assertSame(1, $list->count());
        $this->assertSame(2, $list->first()['itemsCount']);
    }

    public function test_supplier_cannot_create_templates(): void
    {
        Sanctum::actingAs(User::factory()->supplier()->create());
        $product = Product::factory()->withStock(10)->create();

        // Even a fully valid payload is blocked by the policy.
        $this->postJson('/api/v1/order-templates', [
            'name' => 'X',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_reorder_places_orders_per_supplier_at_current_prices(): void
    {
        $supplierA = Company::factory()->retail()->create();
        $supplierB = Company::factory()->retail()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $owner = User::factory()->buyer()->forCompany($buyer)->create();

        // One product per supplier; current price 30 each.
        $p1 = Product::factory()->withStock(50)->create([
            'supplier_company_id' => $supplierA->id,
            'price' => 30,
            'pharmacist_price' => 30,
        ]);
        $p2 = Product::factory()->withStock(50)->create([
            'supplier_company_id' => $supplierB->id,
            'price' => 30,
            'pharmacist_price' => 30,
        ]);

        Sanctum::actingAs($owner);
        $templateId = $this->postJson('/api/v1/order-templates', [
            'name' => 'T',
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
        ])->json('data.id');

        // Price changes after the template was saved must be honoured on reorder.
        $p1->update(['pharmacist_price' => 45, 'price' => 45]);

        $response = $this->postJson("/api/v1/order-templates/{$templateId}/reorder")->assertOk();

        $this->assertSame(2, Order::query()->count());
        $orders = collect($response->json('data'));

        $orderForA = $orders->firstWhere(fn ($o) => $o['data'] ?? null === null ? false : false) ?? null;
        // Verify via DB: each supplier got exactly one order with correct totals.
        $orderA = Order::query()->where('supplier_company_id', $supplierA->id)->firstOrFail();
        $this->assertEqualsWithDelta(90.0, (float) $orderA->total_amount, 0.001); // 2 × 45 current price
        $orderB = Order::query()->where('supplier_company_id', $supplierB->id)->firstOrFail();
        $this->assertEqualsWithDelta(30.0, (float) $orderB->total_amount, 0.001);
        $this->assertNotNull($response->json('message'));
    }

    public function test_reorder_rejects_when_a_supplier_becomes_unreachable(): void
    {
        // Wholesale-only supplier is invisible to pharmacies.
        $wholesaleSupplier = Company::factory()->wholesale()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $owner = User::factory()->buyer()->forCompany($buyer)->create();
        $p = Product::factory()->withStock(10)->create([
            'supplier_company_id' => $wholesaleSupplier->id,
            'wholesale_price' => 10,
            'wholesale_min_qty' => 1,
            'pharmacist_price' => 12,
            'price' => 12,
        ]);

        $template = OrderTemplate::factory()->create([
            'buyer_company_id' => $buyer->id,
            'created_by' => $owner->id,
        ]);
        $template->items()->create(['product_id' => $p->id, 'quantity' => 1]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/order-templates/{$template->id}/reorder")->assertStatus(422);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_update_and_delete_flows(): void
    {
        $buyer = Company::factory()->pharmacy()->create();
        $user = User::factory()->buyer()->forCompany($buyer)->create();
        $p = Product::factory()->withStock(10)->create();
        $template = OrderTemplate::factory()->create([
            'buyer_company_id' => $buyer->id,
            'created_by' => $user->id,
        ]);
        $template->items()->create(['product_id' => $p->id, 'quantity' => 3]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/order-templates/{$template->id}", [
            'name' => 'Renamed',
            'items' => [['product_id' => $p->id, 'quantity' => 9]],
        ])->assertOk();

        $this->assertSame(1, $template->items()->count());
        $this->assertSame(9, $template->items()->first()->quantity);

        $this->deleteJson("/api/v1/order-templates/{$template->id}")->assertOk();
        $this->assertSoftDeleted('order_templates', ['id' => $template->id]);
    }

    public function test_outside_company_cannot_touch_template(): void
    {
        $template = OrderTemplate::factory()->create();
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->getJson("/api/v1/order-templates/{$template->id}")->assertForbidden();
    }
}
