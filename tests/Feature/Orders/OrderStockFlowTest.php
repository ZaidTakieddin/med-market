<?php

namespace Tests\Feature\Orders;

use App\Constants\OrderStatus;
use App\Domain\Orders\OrderStateTransitionService;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderStockFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(); // GenerateInvoiceJob writes the invoice file on accept
    }

    /**
     * @return array{0: Order, 1: Product}
     */
    private function pendingOrderWithItem(int $stock, int $quantity): array
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $product = Product::factory()->withStock($stock)->create(['supplier_company_id' => $supplier->id]);

        $order = Order::factory()->pending()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        return [$order, $product];
    }

    public function test_accepting_an_order_deducts_stock(): void
    {
        [$order, $product] = $this->pendingOrderWithItem(100, 10);

        app(OrderStateTransitionService::class)->transition($order, OrderStatus::ACCEPTED);

        $this->assertSame(90, $product->fresh()->stock_quantity);
    }

    public function test_accepted_to_prepared_does_not_double_deduct(): void
    {
        [$order, $product] = $this->pendingOrderWithItem(100, 10);
        $service = app(OrderStateTransitionService::class);

        $order = $service->transition($order, OrderStatus::ACCEPTED);
        $service->transition($order, OrderStatus::PREPARED);

        $this->assertSame(90, $product->fresh()->stock_quantity);
    }

    public function test_cancelling_an_accepted_order_reverts_stock(): void
    {
        [$order, $product] = $this->pendingOrderWithItem(100, 10);
        $service = app(OrderStateTransitionService::class);

        $order = $service->transition($order, OrderStatus::ACCEPTED);
        $this->assertSame(90, $product->fresh()->stock_quantity);

        $service->transition($order, OrderStatus::CANCELLED);

        $this->assertSame(100, $product->fresh()->stock_quantity);
    }
}
