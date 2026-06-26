<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\StockMovementService;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): StockMovementService
    {
        return new StockMovementService;
    }

    public function test_record_out_deducts_stock_and_records_movement(): void
    {
        $product = Product::factory()->withStock(100)->create();

        $movement = $this->service()->recordOut($product, 30, reason: 'test');

        $this->assertSame(70, $product->fresh()->stock_quantity);
        $this->assertSame('out', $movement->type);
        $this->assertSame(-30, $movement->quantity);
        $this->assertSame(100, $movement->quantity_before);
        $this->assertSame(70, $movement->quantity_after);
    }

    public function test_record_out_beyond_available_throws_and_leaves_stock_untouched(): void
    {
        $product = Product::factory()->withStock(10)->create();

        try {
            $this->service()->recordOut($product, 20);
            $this->fail('Expected DomainException for overselling.');
        } catch (\App\Exceptions\DomainException) {
            // expected
        }

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_record_in_adds_stock(): void
    {
        $product = Product::factory()->withStock(50)->create();

        $this->service()->recordIn($product, 25);

        $this->assertSame(75, $product->fresh()->stock_quantity);
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->expectException(\App\Exceptions\DomainException::class);
        $this->service()->recordMovement($product, 'in', 0);
    }
}
