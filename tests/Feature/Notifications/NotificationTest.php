<?php

namespace Tests\Feature\Notifications;

use App\Constants\OrderStatus;
use App\Domain\Orders\OrderStateTransitionService;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_transition_creates_database_notifications_for_both_sides(): void
    {
        [$buyerUser, $supplierUser, $order, $transitioner] = $this->makeScenario();

        app(OrderStateTransitionService::class)->transition(
            order: $order,
            newStatus: OrderStatus::ACCEPTED,
            userId: $transitioner->id,
        );

        $buyerNotification = $buyerUser->unreadNotifications()->first();
        $this->assertNotNull($buyerNotification);
        $this->assertSame('order_status', $buyerNotification->data['kind']);
        $this->assertSame(OrderStatus::ACCEPTED, $buyerNotification->data['new_status']);
        $this->assertNotNull($supplierUser->unreadNotifications()->first());
    }

    public function test_notifications_endpoint_lists_own_only_with_unread_count(): void
    {
        [$buyerUser] = $this->makeScenario(withTransition: true);

        Sanctum::actingAs($buyerUser);

        $response = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertGreaterThan(0, $response->json('unreadCount'));
        $this->assertSame('order_status', $response->json('data.0.kind'));

        // Another user sees none of them.
        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_mark_read_flows(): void
    {
        [$buyerUser] = $this->makeScenario(withTransition: true);

        Sanctum::actingAs($buyerUser);

        $id = $buyerUser->unreadNotifications()->first()->id;

        $this->postJson("/api/v1/notifications/{$id}/mark-read")->assertOk();

        $this->getJson('/api/v1/notifications/unread-count')->assertOk()
            ->assertJsonPath('data.unreadCount', 0);

        $this->postJson('/api/v1/notifications/mark-all-read')->assertOk();
    }

    public function test_cannot_read_someone_elses_notification(): void
    {
        [$buyerUser] = $this->makeScenario(withTransition: true);
        $foreignId = $buyerUser->unreadNotifications()->first()->id;

        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->postJson("/api/v1/notifications/{$foreignId}/mark-read")->assertNotFound();
    }

    /**
     * @return array{0: User, 1: User, 2: Order, 3: User}
     */
    private function makeScenario(bool $withTransition = false): array
    {
        $supplier = Company::factory()->supplier()->create();
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();
        $buyer = Company::factory()->pharmacy()->create();
        $buyerUser = User::factory()->buyer()->forCompany($buyer)->create();

        $order = Order::factory()->pending()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'created_by' => $buyerUser->id,
        ]);

        if ($withTransition) {
            // QUEUE_CONNECTION=sync runs the dispatched notification job inline.
            app(OrderStateTransitionService::class)->transition(
                order: $order,
                newStatus: OrderStatus::ACCEPTED,
                userId: $supplierUser->id,
            );
        }

        return [$buyerUser, $supplierUser, $order, $supplierUser];
    }
}
