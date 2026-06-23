<?php

namespace Tests\Feature\Pricing;

use App\Constants\PricingType;
use App\Domain\Pricing\PricingService;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PricingService
    {
        return new PricingService;
    }

    /**
     * A product priced at 100 with a 10% product discount and a wholesale tier of 80.
     * Retail (with discount) => 90; wholesale (no discount) => 80.
     */
    private function discountedWholesaleProduct(Company $supplier): Product
    {
        return Product::factory()->create([
            'supplier_company_id' => $supplier->id,
            'price' => 100,
            'pharmacist_price' => 100,
            'wholesale_price' => 80,
            'wholesale_min_qty' => 10,
            'discount' => 10,
            'discount_type' => 'percentage',
        ]);
    }

    public function test_wholesale_price_ignores_product_discount(): void
    {
        $supplier = Company::factory()->wholesale()->create();
        $buyer = Company::factory()->salesman()->create();
        $product = $this->discountedWholesaleProduct($supplier);

        $result = $this->service()->resolve($product, $buyer, 10);

        $this->assertSame(PricingType::WHOLESALE, $result->pricingType);
        $this->assertEqualsWithDelta(80.0, $result->unitPrice, 0.001);
        $this->assertEqualsWithDelta(0.0, $result->discountAmount, 0.001);
    }

    public function test_resolve_and_resolve_many_agree_for_wholesale(): void
    {
        $supplier = Company::factory()->wholesale()->create();
        $buyer = Company::factory()->salesman()->create();
        $product = $this->discountedWholesaleProduct($supplier);

        $single = $this->service()->resolve($product, $buyer, 10);
        $batch = $this->service()->resolveMany([['product' => $product, 'quantity' => 10]], $buyer)[$product->id];

        $this->assertSame($single->pricingType, $batch->pricingType);
        $this->assertEqualsWithDelta($single->unitPrice, $batch->unitPrice, 0.001);
        $this->assertEqualsWithDelta(80.0, $batch->unitPrice, 0.001);
        $this->assertEqualsWithDelta(0.0, $batch->discountAmount, 0.001);
    }

    public function test_resolve_and_resolve_many_agree_for_retail(): void
    {
        $supplier = Company::factory()->retail()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $product = $this->discountedWholesaleProduct($supplier);

        $single = $this->service()->resolve($product, $buyer, 10);
        $batch = $this->service()->resolveMany([['product' => $product, 'quantity' => 10]], $buyer)[$product->id];

        $this->assertSame(PricingType::RETAIL, $single->pricingType);
        $this->assertSame(PricingType::RETAIL, $batch->pricingType);
        $this->assertEqualsWithDelta(90.0, $single->unitPrice, 0.001);
        $this->assertEqualsWithDelta($single->unitPrice, $batch->unitPrice, 0.001);
        $this->assertEqualsWithDelta(10.0, $batch->discountAmount, 0.001);
    }
}
