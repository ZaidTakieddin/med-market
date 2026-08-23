<?php

namespace Tests\Feature\Invoices;

use App\Constants\PaymentTerms;
use App\Domain\Orders\OrderCreationService;
use App\Models\BuyerSupplierRelationship;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: Company, 2: BuyerSupplierRelationship, 3: Invoice, 4: User}
     */
    private function makeScenario(): array
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $relationship = BuyerSupplierRelationship::factory()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => 1000,
            'current_balance' => 0,
            'is_approved' => true,
        ]);
        $product = Product::factory()->withStock(1000)->create([
            'supplier_company_id' => $supplier->id,
            'price' => 100,
            'pharmacist_price' => 100,
        ]);
        $creator = User::factory()->buyer()->forCompany($buyer)->create();

        // Net-30 order of 5 × 100 = 500 → reserves 500 of credit.
        $order = app(OrderCreationService::class)->create(
            buyerCompanyId: $buyer->id,
            supplierCompanyId: $supplier->id,
            createdBy: $creator->id,
            items: [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100]],
            paymentMethod: PaymentTerms::NET_30,
        );

        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.$order->order_number,
            'order_id' => $order->id,
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'status' => 'generated',
            'payment_status' => 'pending',
            'subtotal' => $order->subtotal,
            'tax_amount' => $order->tax_amount,
            'total_amount' => $order->total_amount,
            'paid_amount' => 0,
            'file_path' => 'invoices/test/'.$order->order_number.'.txt',
            'file_format' => 'txt',
            'generated_at' => now(),
        ]);

        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();

        return [$supplier, $buyer, $relationship, $invoice, $supplierUser];
    }

    public function test_partial_payments_accumulate_and_release_credit_incrementally(): void
    {
        [, , $relationship, $invoice, $supplierUser] = $this->makeScenario();

        Sanctum::actingAs($supplierUser);

        // First partial payment: 200 of 500.
        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", ['amount' => 200])->assertOk();
        $this->assertEqualsWithDelta(200.0, (float) $invoice->fresh()->paid_amount, 0.001);
        $this->assertEqualsWithDelta(300.0, (float) $relationship->fresh()->current_balance, 0.001);

        // Second partial payment: another 200. paid_amount must accumulate to 400.
        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", ['amount' => 200])->assertOk();
        $this->assertEqualsWithDelta(400.0, (float) $invoice->fresh()->paid_amount, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $relationship->fresh()->current_balance, 0.001);

        // Final payment (default = remaining total): fully paid, credit fully released.
        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", [])->assertOk();
        $this->assertEqualsWithDelta(500.0, (float) $invoice->fresh()->paid_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $relationship->fresh()->current_balance, 0.001);
    }

    public function test_overpaying_an_invoice_is_rejected_with_422(): void
    {
        [, , $relationship, $invoice, $supplierUser] = $this->makeScenario();

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", ['amount' => 999])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEqualsWithDelta(500.0, (float) $relationship->fresh()->current_balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->fresh()->paid_amount, 0.001);
    }

    public function test_repeated_full_payment_does_not_double_release_credit(): void
    {
        [, , $relationship, $invoice, $supplierUser] = $this->makeScenario();

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", [])->assertOk();
        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", [])->assertOk();

        $this->assertEqualsWithDelta(0.0, (float) $relationship->fresh()->current_balance, 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $invoice->fresh()->paid_amount, 0.001);
    }
}
