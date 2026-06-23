<?php

namespace Tests\Feature\Credit;

use App\Constants\OrderStatus;
use App\Constants\PaymentTerms;
use App\Domain\Orders\OrderCreationService;
use App\Domain\Orders\OrderStateTransitionService;
use App\Models\BuyerSupplierRelationship;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: Company, 2: BuyerSupplierRelationship, 3: Product, 4: User}
     */
    private function makeScenario(?float $creditLimit, bool $approved = true): array
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $relationship = BuyerSupplierRelationship::factory()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => $creditLimit,
            'current_balance' => 0,
            'is_approved' => $approved,
        ]);
        $product = Product::factory()->withStock(1000)->create([
            'supplier_company_id' => $supplier->id,
            'price' => 100,
            'pharmacist_price' => 100,
        ]);
        $creator = User::factory()->buyer()->forCompany($buyer)->create();

        return [$supplier, $buyer, $relationship, $product, $creator];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(Product $product, int $quantity): array
    {
        return [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 100]];
    }

    private function placeNetOrder(Company $buyer, Company $supplier, Product $product, User $creator, int $quantity)
    {
        return app(OrderCreationService::class)->create(
            buyerCompanyId: $buyer->id,
            supplierCompanyId: $supplier->id,
            createdBy: $creator->id,
            items: $this->items($product, $quantity),
            paymentMethod: PaymentTerms::NET_30,
        );
    }

    public function test_net_term_order_within_limit_reserves_credit(): void
    {
        [$supplier, $buyer, $relationship, $product, $creator] = $this->makeScenario(1000);

        $order = $this->placeNetOrder($buyer, $supplier, $product, $creator, 5); // total 500

        $this->assertEqualsWithDelta(500.0, (float) $relationship->fresh()->current_balance, 0.001);
        $this->assertSame(PaymentTerms::NET_30, $order->payment_method);
        $this->assertNotNull($order->payment_due_date);
    }

    public function test_net_term_order_exceeding_limit_is_rejected_without_side_effects(): void
    {
        [$supplier, $buyer, $relationship, $product, $creator] = $this->makeScenario(300);

        try {
            $this->placeNetOrder($buyer, $supplier, $product, $creator, 5); // total 500 > 300
            $this->fail('Expected InvalidArgumentException for exceeding credit.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertEqualsWithDelta(0.0, (float) $relationship->fresh()->current_balance, 0.001);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_net_term_order_without_approved_relationship_is_rejected(): void
    {
        [$supplier, $buyer, $relationship, $product, $creator] = $this->makeScenario(1000, approved: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->placeNetOrder($buyer, $supplier, $product, $creator, 1);
    }

    public function test_cash_order_does_not_touch_credit(): void
    {
        [$supplier, $buyer, $relationship, $product, $creator] = $this->makeScenario(1000);

        $order = app(OrderCreationService::class)->create(
            buyerCompanyId: $buyer->id,
            supplierCompanyId: $supplier->id,
            createdBy: $creator->id,
            items: $this->items($product, 5),
            paymentMethod: PaymentTerms::CASH,
        );

        $this->assertSame(PaymentTerms::CASH, $order->payment_method);
        $this->assertEqualsWithDelta(0.0, (float) $relationship->fresh()->current_balance, 0.001);
    }

    public function test_cancelling_a_net_term_order_releases_credit(): void
    {
        [$supplier, $buyer, $relationship, $product, $creator] = $this->makeScenario(1000);
        $order = $this->placeNetOrder($buyer, $supplier, $product, $creator, 5); // reserves 500
        $this->assertEqualsWithDelta(500.0, (float) $relationship->fresh()->current_balance, 0.001);

        app(OrderStateTransitionService::class)->transition($order, OrderStatus::CANCELLED, $creator->id, 'changed mind');

        $this->assertEqualsWithDelta(0.0, (float) $relationship->fresh()->current_balance, 0.001);
    }

    public function test_paying_an_invoice_releases_credit(): void
    {
        [$supplier, $buyer, $relationship, $product, $creator] = $this->makeScenario(1000);
        $order = $this->placeNetOrder($buyer, $supplier, $product, $creator, 5); // reserves 500

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
        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/invoices/{$invoice->id}/pay", [])->assertOk();

        $this->assertEqualsWithDelta(0.0, (float) $relationship->fresh()->current_balance, 0.001);
    }
}
