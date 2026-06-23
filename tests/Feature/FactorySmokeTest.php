<?php

namespace Tests\Feature;

use App\Constants\CompanyType;
use App\Constants\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_factory_states_persist(): void
    {
        $this->assertSame(CompanyType::SUPPLIER, Company::factory()->supplier()->create()->type);
        $this->assertSame(CompanyType::BUYER, Company::factory()->pharmacy()->create()->type);
        $this->assertTrue(Company::factory()->wholesale()->create()->isWholesale());
        $this->assertTrue(Company::factory()->salesman()->create()->isSalesmanCompany());
    }

    public function test_user_factory_role_states_persist(): void
    {
        $this->assertSame(UserRole::ADMIN, User::factory()->admin()->create()->role);
        $this->assertSame(UserRole::SUPPLIER, User::factory()->supplier()->create()->role);
        $this->assertSame(UserRole::BUYER, User::factory()->buyer()->create()->role);
        $this->assertSame(UserRole::DRIVER, User::factory()->driver()->create()->role);
        $this->assertSame(UserRole::SALESMAN, User::factory()->salesman()->create()->role);

        $supplierUser = User::factory()->supplier()->create();
        $this->assertNotNull($supplierUser->company_id);
        $this->assertTrue($supplierUser->company->isSupplier());
    }

    public function test_product_factory_with_wholesale_persists(): void
    {
        $product = Product::factory()->wholesale(minQty: 15)->create();

        $this->assertNotNull($product->wholesale_price);
        $this->assertSame(15, $product->wholesale_min_qty);
        $this->assertTrue($product->qualifiesForWholesale(15));
    }

    public function test_order_with_items_persists(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $product = Product::factory()->create(['supplier_company_id' => $supplier->id]);

        $order = Order::factory()
            ->accepted()
            ->create([
                'buyer_company_id' => $buyer->id,
                'supplier_company_id' => $supplier->id,
            ]);

        OrderItem::factory()->count(3)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $this->assertDatabaseCount('order_items', 3);
        $this->assertSame(3, $order->items()->count());
        $this->assertNotNull($order->accepted_at);
    }
}
