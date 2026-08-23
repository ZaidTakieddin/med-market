<?php

namespace Tests\Feature\Inventory;

use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_stock_with_batch_syncs_product_and_movement(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $product = Product::factory()->withStock(10)->create(['supplier_company_id' => $supplier->id]);

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/products/{$product->id}/stock/add", [
            'quantity' => 50,
            'reason' => 'New shipment',
            'batch_number' => 'BATCH-2026-A',
            'expiry_date' => '2027-06-30',
        ])->assertOk();

        $product->refresh();
        $this->assertSame('BATCH-2026-A', $product->batch_number);
        $this->assertSame('2027-06-30', $product->expiry_date?->toDateString());
        $this->assertSame(60, $product->stock_quantity);

        $movement = StockMovement::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('BATCH-2026-A', $movement->batch_number);
        $this->assertSame('2027-06-30', $movement->expiry_date?->toDateString());
    }

    public function test_product_index_filters_by_expiry_and_low_stock(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();

        $expiringSoon = Product::factory()->withStock(50)->create([
            'supplier_company_id' => $supplier->id,
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);
        $expired = Product::factory()->withStock(50)->create([
            'supplier_company_id' => $supplier->id,
            'expiry_date' => now()->subDay()->toDateString(),
        ]);
        $lowStock = Product::factory()->create([
            'supplier_company_id' => $supplier->id,
            'stock_quantity' => 2,
            'min_stock_level' => 5,
        ]);

        Sanctum::actingAs($supplierUser);

        $expiring = collect($this->getJson('/api/v1/products?expiring_before='.now()->addDays(10)->toDateString())
            ->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$expiringSoon->id, $expired->id], $expiring->all());

        $expiredIds = collect($this->getJson('/api/v1/products?expired=true')->json('data'))->pluck('id');
        $this->assertSame([$expired->id], $expiredIds->all());

        $lowStockIds = collect($this->getJson('/api/v1/products?low_stock=true')->json('data'))->pluck('id');
        $this->assertSame([$lowStock->id], $lowStockIds->all());
    }

    public function test_bulk_stock_operations_report_per_row_results(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $inStock = Product::factory()->withStock(100)->create(['supplier_company_id' => $supplier->id]);
        $scarce = Product::factory()->withStock(5)->create(['supplier_company_id' => $supplier->id]);

        Sanctum::actingAs($supplierUser);

        $response = $this->postJson('/api/v1/products/stock/bulk', [
            'operations' => [
                ['product_id' => $inStock->id, 'type' => 'in', 'quantity' => 25, 'batch_number' => 'LOT-9'],
                ['product_id' => $scarce->id, 'type' => 'out', 'quantity' => 50], // insufficient
            ],
        ])->assertOk();

        $this->assertSame(2, $response->json('data.total'));
        $this->assertSame(1, $response->json('data.succeeded'));
        $this->assertSame(1, $response->json('data.failed'));

        $results = collect($response->json('data.results'));
        $first = $results->firstWhere('product_id', $inStock->id);
        $second = $results->firstWhere('product_id', $scarce->id);

        $this->assertTrue($first['success']);
        $this->assertSame(125, $first['new_stock_quantity']);
        $this->assertFalse($second['success']);
        $this->assertNotNull($second['error']);

        // The successful row was applied atomically; the failed one changed nothing.
        $this->assertSame(125, $inStock->fresh()->stock_quantity);
        $this->assertSame(5, $scarce->fresh()->stock_quantity);
        $this->assertSame('LOT-9', $inStock->fresh()->batch_number);
    }

    public function test_bulk_rejects_products_outside_own_company(): void
    {
        $supplierUser = User::factory()->supplier()->create();
        $foreignProduct = Product::factory()->withStock(10)->create(); // other supplier

        Sanctum::actingAs($supplierUser);

        $response = $this->postJson('/api/v1/products/stock/bulk', [
            'operations' => [
                ['product_id' => $foreignProduct->id, 'type' => 'out', 'quantity' => 1],
            ],
        ])->assertOk();

        $result = $response->json('data.results.0');
        $this->assertFalse($result['success']);
        $this->assertSame(1, $response->json('data.failed'));
        $this->assertSame(10, $foreignProduct->fresh()->stock_quantity);
    }

    public function test_bulk_validates_operation_payload(): void
    {
        $supplierUser = User::factory()->supplier()->create();
        Sanctum::actingAs($supplierUser);

        $this->postJson('/api/v1/products/stock/bulk', [
            'operations' => [
                ['product_id' => 999999, 'type' => 'sideways', 'quantity' => 0],
            ],
        ])->assertStatus(422);
    }
}
