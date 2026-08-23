<?php

namespace Tests\Feature\Broadcasting;

use App\Events\DriverLocationUpdated;
use App\Events\OrderStatusChanged;
use App\Events\StockLevelChanged;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests run on the "null" broadcaster; switch to reverb so the real
        // Pusher-style auth flow runs, then re-register channel closures on it.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        Event::fake([
            OrderStatusChanged::class,
            StockLevelChanged::class,
            DriverLocationUpdated::class,
        ]);
    }

    public function test_order_transition_broadcasts_to_both_company_channels(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $order = Order::factory()->pending()->create([
            'supplier_company_id' => $supplier->id,
            'buyer_company_id' => $buyer->id,
        ]);

        app(\App\Domain\Orders\OrderStateTransitionService::class)->transition(
            order: $order,
            newStatus: \App\Constants\OrderStatus::ACCEPTED,
            userId: User::factory()->supplier()->forCompany($supplier)->create()->id,
        );

        Event::assertDispatched(
            OrderStatusChanged::class,
            fn (OrderStatusChanged $event): bool => collect($event->broadcastOn())
                ->map(fn ($channel): string => $channel->name)
                ->contains("private-supplier.{$supplier->id}")
                && collect($event->broadcastOn())
                    ->map(fn ($channel): string => $channel->name)
                    ->contains("private-buyer.{$buyer->id}"),
        );
    }

    public function test_stock_movement_broadcasts_level_change(): void
    {
        $product = Product::factory()->withStock(50)->create();

        app(\App\Domain\Inventory\StockMovementService::class)->recordIn($product, 10);

        Event::assertDispatched(StockLevelChanged::class);
    }

    public function test_driver_ping_broadcasts_location(): void
    {
        $driver = User::factory()->driver()->create();

        app(\App\Domain\Deliveries\DriverLocationService::class)->ping($driver, 33.51, 36.27);

        Event::assertDispatched(DriverLocationUpdated::class);
    }

    public function test_channel_authorization_scopes_companies(): void
    {
        $supplier = Company::factory()->supplier()->create();
        $member = User::factory()->supplier()->forCompany($supplier)->create();
        $outsider = User::factory()->supplier()->create(); // other company
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($member);
        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-supplier.{$supplier->id}",
            'socket_id' => '1234.5678',
        ])->assertOk();

        Sanctum::actingAs($outsider);
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-supplier.{$supplier->id}",
            'socket_id' => '1234.5678',
        ]);
        $response->assertForbidden();

        $this->postJson('/broadcasting/auth', ['channel_name' => "private-supplier.{$supplier->id}", 'socket_id' => '1.1'])->dump();

        // Admins may join any supplier feed.
        Sanctum::actingAs($admin);
        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-supplier.{$supplier->id}",
            'socket_id' => '1234.5678',
        ])->assertOk();
    }
}
