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
}
