<?php

namespace Tests\Feature\Deliveries;

use App\Constants\OrderStatus;
use App\Domain\Orders\OrderStateTransitionService;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealTimeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_pings_and_supplier_reads_location(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $driver = User::factory()->driver()->forCompany($supplier)->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();

        Sanctum::actingAs($driver);
        $this->postJson('/api/v1/drivers/location', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ])->assertOk();

        Sanctum::actingAs($supplierUser);

        $response = $this->getJson("/api/v1/drivers/{$driver->id}/location")->assertOk();

        $this->assertEqualsWithDelta(33.5138, $response->json('data.latitude'), 0.0001);
        $this->assertNotNull($response->json('data.recordedAt'));
    }

    public function test_only_drivers_can_ping(): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->postJson('/api/v1/drivers/location', [
            'latitude' => 10,
            'longitude' => 10,
        ])->assertForbidden();
    }

    public function test_driver_without_pings_returns_null_location(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $driver = User::factory()->driver()->forCompany($supplier)->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/v1/drivers/{$driver->id}/location")->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_delivery_proof_upload_and_listing(): void
    {
        Storage::fake(config('media.disk'));

        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $order = Order::factory()->shipped()->create([
            'supplier_company_id' => $supplier->id,
            'buyer_company_id' => $buyer->id,
        ]);

        // Supplier uploads a handover photo.
        Sanctum::actingAs(User::factory()->supplier()->forCompany($supplier)->create());
        $upload = $this->postJson("/api/v1/orders/{$order->id}/delivery-proof", [
            'image' => UploadedFile::fake()->image('proof.jpg'),
            'type' => 'photo',
            'notes' => 'Left at reception',
        ])->assertCreated();
        $this->assertSame('photo', $upload->json('data.type'));

        // Buyer lists proofs.
        Sanctum::actingAs(User::factory()->buyer()->forCompany($buyer)->create());
        $list = $this->getJson("/api/v1/orders/{$order->id}/delivery-proof")->assertOk();
        $this->assertCount(1, $list->json('data.proofs'));
        $this->assertSame('Left at reception', $list->json('data.proofs.0.notes'));
    }

    public function test_outsider_cannot_view_proofs(): void
    {
        $order = Order::factory()->pending()->create();

        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->getJson("/api/v1/orders/{$order->id}/delivery-proof")->assertForbidden();
    }

    public function test_eta_is_estimated_when_order_ships(): void
    {
        $supplier = Company::factory()->supplier()->create([
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ]);
        $buyer = Company::factory()->pharmacy()->create([
            'latitude' => 33.4,
            'longitude' => 36.1,
        ]);
        $creator = User::factory()->buyer()->forCompany($buyer)->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();

        $order = Order::factory()->prepared()->create([
            'supplier_company_id' => $supplier->id,
            'buyer_company_id' => $buyer->id,
            'created_by' => $creator->id,
        ]);

        app(OrderStateTransitionService::class)->transition(
            order: $order,
            newStatus: OrderStatus::SHIPPED,
            userId: $supplierUser->id,
        );

        $eta = $order->fresh()->estimated_delivery_at;
        $this->assertNotNull($eta);
        // ETA must land after shipping (travel + buffer), regardless of tick timing.
        $this->assertGreaterThan(
            $order->fresh()->shipped_at->getTimestamp(),
            $eta->getTimestamp(),
        );
    }
}
