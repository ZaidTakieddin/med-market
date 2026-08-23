<?php

namespace Tests\Feature\Inventory;

use App\Constants\StockMovementType;
use App\Domain\Inventory\ProductBatchService;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BatchFefoAndTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_start_batch_tracking_and_deductions_consume_fefo(): void
    {
        $product = Product::factory()->withStock(0)->create();

        // Receive two batches: the later-expiring one first (receipt order must not matter).
        app(ProductBatchService::class)->receive($product, 30, 'LOT-OLD', now()->addMonth()->toDateString());
        app(ProductBatchService::class)->receive($product, 50, 'LOT-NEW', now()->addYear()->toDateString());

        // Consume 40 → all of the earliest-expiring LOT-OLD plus 10 from LOT-NEW.
        $allocations = app(ProductBatchService::class)->consumeFefo($product, 40);

        $this->assertSame(2, $allocations->count());
        $this->assertSame('LOT-OLD', $allocations[0]['batch_number']);
        $this->assertSame(30, $allocations[0]['quantity']);
        $this->assertSame('LOT-NEW', $allocations[1]['batch_number']);
        $this->assertSame(10, $allocations[1]['quantity']);

        $remaining = $product->batches()->orderBy('batch_number')->pluck('quantity', 'batch_number');
        $this->assertSame(40, (int) $remaining['LOT-NEW']);
        $this->assertFalse($remaining->contains(30));
    }

    public function test_fefo_rejects_when_batches_cannot_cover_the_amount(): void
    {
        $product = Product::factory()->withStock(100)->create();
        app(ProductBatchService::class)->receive($product, 25, 'LOT-A', now()->addMonths(2)->toDateString());

        $this->expectException(\App\Exceptions\DomainException::class);
        app(ProductBatchService::class)->consumeFefo($product, 50);
    }

    public function test_untracked_products_keep_legacy_behaviour(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $product = Product::factory()->withStock(10)->create(['supplier_company_id' => $supplier->id]);

        Sanctum::actingAs($supplierUser);

        // Plain deduction without any batch history works as before.
        $this->postJson("/api/v1/products/{$product->id}/stock/adjust", [
            'type' => StockMovementType::ADJUSTMENT,
            'quantity' => -4,
            'reason' => 'Damage',
        ])->assertOk();

        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertNull($product->fresh()->batch_number);
    }

    public function test_order_deduction_draws_from_earliest_expiring_batch_first(): void
    {
        $product = Product::factory()->withStock(0)->create();
        $movements = app(\App\Domain\Inventory\StockMovementService::class);

        // Realistic receipts through the movement service (updates stock + ledger).
        $movements->recordMovement(
            product: $product,
            type: StockMovementType::IN,
            quantity: 20,
            batchNumber: 'LOT-SHORT',
            expiryDate: now()->addWeeks(2)->toDateString(),
        );
        $movements->recordMovement(
            product: $product,
            type: StockMovementType::IN,
            quantity: 20,
            batchNumber: 'LOT-LONG',
            expiryDate: now()->addYears(2)->toDateString(),
        );

        // Order-style deduction.
        $movements->recordMovement(
            product: $product,
            type: StockMovementType::OUT,
            quantity: -25,
        );

        $this->assertSame(15, $product->stock_quantity);

        $batches = $product->batches()->orderBy('batch_number')->pluck('quantity', 'batch_number');
        $this->assertSame(15, (int) $batches['LOT-LONG']);
        $this->assertArrayNotHasKey('LOT-SHORT', $batches->all()); // fully consumed + removed
    }

    public function test_admin_can_transfer_stock_between_supplier_products(): void
    {
        $fromSupplier = Company::factory()->supplier()->create();
        $toSupplier = Company::factory()->supplier()->create();
        $source = Product::factory()->withStock(60)->create([
            'supplier_company_id' => $fromSupplier->id,
            'batch_number' => 'B-XFER',
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ]);
        $destination = Product::factory()->withStock(5)->create(['supplier_company_id' => $toSupplier->id]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson("/api/v1/products/{$source->id}/transfer", [
            'to_product_id' => $destination->id,
            'quantity' => 15,
            'reason' => 'Rebalancing',
        ])->assertOk();

        $this->assertSame(45, $response->json('data.from_product.stock_quantity'));
        $this->assertSame(20, $response->json('data.to_product.stock_quantity'));

        // The destination inherits the source's current batch.
        $this->assertSame('B-XFER', $destination->fresh()->batch_number);

        // Cross-referenced movements exist on both sides.
        $out = \App\Models\StockMovement::query()
            ->where('product_id', $source->id)->where('reference_type', 'transfer')->firstOrFail();
        $in = \App\Models\StockMovement::query()
            ->where('product_id', $destination->id)->where('reference_type', 'transfer')->firstOrFail();
        $this->assertSame(-15, $out->quantity);
        $this->assertSame(15, $in->quantity);
        $this->assertSame((string) $destination->id, $out->reference_id);
        $this->assertSame((string) $source->id, $in->reference_id);
    }

    public function test_non_admin_cannot_transfer_between_companies(): void
    {
        $fromSupplier = Company::factory()->supplier()->create();
        $source = Product::factory()->withStock(60)->create(['supplier_company_id' => $fromSupplier->id]);
        $destination = Product::factory()->withStock(5)->create(); // other supplier

        Sanctum::actingAs(User::factory()->supplier()->forCompany($fromSupplier)->create());

        $this->postJson("/api/v1/products/{$source->id}/transfer", [
            'to_product_id' => $destination->id,
            'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_transfer_to_same_company_is_rejected(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $a = Product::factory()->withStock(10)->create(['supplier_company_id' => $supplier->id]);
        $b = Product::factory()->withStock(10)->create(['supplier_company_id' => $supplier->id]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/v1/products/{$a->id}/transfer", [
            'to_product_id' => $b->id,
            'quantity' => 5,
        ])->assertStatus(422);
    }

    public function test_batches_endpoint_lists_fefo_for_owner(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $product = Product::factory()->withStock(80)->create(['supplier_company_id' => $supplier->id]);
        app(ProductBatchService::class)->receive($product, 30, 'LOT-B', now()->addMonths(3)->toDateString());
        app(ProductBatchService::class)->receive($product, 50, 'LOT-A', now()->addMonths(9)->toDateString());

        Sanctum::actingAs($supplierUser);

        $response = $this->getJson("/api/v1/products/{$product->id}/batches")->assertOk();

        $this->assertTrue($response->json('data.isBatchTracked'));
        $numbers = collect($response->json('data.batches'))->pluck('batchNumber');
        $this->assertSame(['LOT-B', 'LOT-A'], $numbers->all()); // FEFO order
    }
}
