<?php

namespace Tests\Feature;

use App\Constants\CompanyType;
use App\Constants\UserRole;
use App\Models\BuyerSupplierRelationship;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Manufacturer;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Region;
use App\Models\SalesmanClient;
use App\Models\StockMovement;
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

    public function test_reference_and_relational_factories_persist(): void
    {
        $this->assertNotNull(Category::factory()->create()->id);
        $this->assertNotNull(Manufacturer::factory()->create()->id);
        $this->assertNotNull(City::factory()->create()->id);

        $region = Region::factory()->create();
        $this->assertNotNull($region->city_id);

        $this->assertNotNull(Offer::factory()->create()->id);
        $this->assertNotNull(Cart::factory()->create()->id);
        $this->assertNotNull(CartItem::factory()->create()->id);
        $this->assertNotNull(Invoice::factory()->create()->id);
        $this->assertNotNull(StockMovement::factory()->create()->id);
        $this->assertNotNull(SalesmanClient::factory()->create()->id);
        $this->assertNotNull(PriceList::factory()->create()->id);
        $this->assertNotNull(OfferProduct::factory()->create()->id);
        $this->assertNotNull(OrderStatusLog::factory()->create()->id);
        $this->assertNotNull(BuyerSupplierRelationship::factory()->create()->id);
    }
}
