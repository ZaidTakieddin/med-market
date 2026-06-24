<?php

namespace Tests\Feature\Authorization;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_can_add_stock_to_own_product_but_not_another_suppliers(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $product = Product::factory()->withStock(0)->create(['supplier_company_id' => $supplier->id]);

        Sanctum::actingAs(User::factory()->supplier()->forCompany($supplier)->create());
        $this->postJson("/api/v1/products/{$product->id}/stock/add", ['quantity' => 10])->assertOk();
        $this->assertSame(10, $product->fresh()->stock_quantity);

        Sanctum::actingAs(User::factory()->supplier()->create());
        $this->postJson("/api/v1/products/{$product->id}/stock/add", ['quantity' => 5])->assertForbidden();
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_only_issuing_supplier_can_mark_invoice_paid(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $order = \App\Models\Order::factory()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-1',
            'order_id' => $order->id,
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'status' => 'generated',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'file_path' => 'invoices/test/INV-TEST-1.txt',
            'file_format' => 'txt',
            'generated_at' => now(),
        ]);

        // A buyer must not be able to mark their own invoice paid (would free their credit).
        Sanctum::actingAs(User::factory()->buyer()->forCompany($buyer)->create());
        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", [])->assertForbidden();
        $this->assertSame('pending', $invoice->fresh()->payment_status);

        // The issuing supplier can.
        Sanctum::actingAs(User::factory()->supplier()->forCompany($supplier)->create());
        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", [])->assertOk();
        $this->assertSame('paid', $invoice->fresh()->payment_status);
    }

    public function test_offer_policy_allows_only_owning_supplier_or_admin(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $owner = User::factory()->supplier()->forCompany($supplier)->create();
        $otherSupplier = User::factory()->supplier()->create();
        $buyer = User::factory()->buyer()->create();
        $offer = new \App\Models\Offer(['supplier_company_id' => $supplier->id]);
        $policy = new \App\Policies\OfferPolicy;

        $this->assertTrue($policy->create($owner));
        $this->assertFalse($policy->create($buyer));
        $this->assertTrue($policy->update($owner, $offer));
        $this->assertFalse($policy->update($otherSupplier, $offer));
    }

    public function test_only_admin_can_create_cities_and_regions(): void
    {
        $city = \App\Models\City::create(['name' => 'Damascus']);

        Sanctum::actingAs(User::factory()->supplier()->create());
        $this->postJson('/api/v1/cities', ['name' => 'Hama'])->assertForbidden();
        $this->postJson('/api/v1/regions', ['name' => 'Mazzeh', 'city_id' => $city->id])->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson('/api/v1/cities', ['name' => 'Hama'])->assertCreated();
        $this->postJson('/api/v1/regions', ['name' => 'Mazzeh', 'city_id' => $city->id])->assertCreated();
    }

    public function test_driver_policy_scopes_to_owning_supplier(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $otherSupplier = User::factory()->supplier()->create();
        $driver = User::factory()->driver()->forCompany($supplier)->create();
        $policy = new \App\Policies\UserPolicy;

        $this->assertTrue($policy->viewDrivers($supplierUser));
        $this->assertFalse($policy->viewDrivers(User::factory()->buyer()->create()));
        $this->assertTrue($policy->manageDriver($supplierUser, $driver));
        $this->assertTrue($policy->manageDriver(User::factory()->admin()->create(), $driver));
        $this->assertFalse($policy->manageDriver($otherSupplier, $driver));
    }

    public function test_buyer_cannot_list_drivers_supplier_can(): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->getJson('/api/v1/drivers')->assertForbidden();

        Sanctum::actingAs(User::factory()->supplier()->create());
        $this->getJson('/api/v1/drivers')->assertOk();
    }

    public function test_salesman_policy_scopes_to_owning_supplier_or_self(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $salesman = User::factory()->salesman()->forCompany($supplier)->create();
        $otherSupplier = User::factory()->supplier()->create();
        $policy = new \App\Policies\UserPolicy;

        $this->assertTrue($policy->accessSalesman($salesman, $salesman)); // self
        $this->assertTrue($policy->accessSalesman($supplierUser, $salesman));
        $this->assertFalse($policy->accessSalesman($otherSupplier, $salesman));
        $this->assertTrue($policy->manageSalesman($supplierUser, $salesman));
        $this->assertFalse($policy->manageSalesman($salesman, $salesman)); // self can't manage
        $this->assertFalse($policy->manageSalesman($otherSupplier, $salesman));
    }
}
