<?php

namespace Tests\Unit\Orders;

use App\Constants\OrderStatus;
use App\Models\Order;
use Tests\TestCase;

/**
 * Locks the single source of truth for order transitions (App\Constants\OrderStatus)
 * after the OrderState duplicate was removed. Key rule: a shipped order cannot be
 * cancelled.
 */
class OrderStatusTransitionMatrixTest extends TestCase
{
    public function test_valid_transitions_matrix(): void
    {
        $this->assertSame([OrderStatus::ACCEPTED, OrderStatus::CANCELLED], OrderStatus::getValidTransitions(OrderStatus::PENDING));
        $this->assertSame([OrderStatus::PREPARED, OrderStatus::CANCELLED], OrderStatus::getValidTransitions(OrderStatus::ACCEPTED));
        $this->assertSame([OrderStatus::SHIPPED, OrderStatus::CANCELLED], OrderStatus::getValidTransitions(OrderStatus::PREPARED));
        $this->assertSame([OrderStatus::DELIVERED], OrderStatus::getValidTransitions(OrderStatus::SHIPPED));
        $this->assertSame([], OrderStatus::getValidTransitions(OrderStatus::DELIVERED));
        $this->assertSame([], OrderStatus::getValidTransitions(OrderStatus::CANCELLED));
    }

    public function test_shipped_order_cannot_be_cancelled(): void
    {
        $this->assertFalse(OrderStatus::canTransition(OrderStatus::SHIPPED, OrderStatus::CANCELLED));
        $this->assertTrue(OrderStatus::canTransition(OrderStatus::SHIPPED, OrderStatus::DELIVERED));
    }

    public function test_order_model_delegates_to_order_status(): void
    {
        $shipped = new Order(['status' => OrderStatus::SHIPPED]);
        $this->assertFalse($shipped->canTransitionTo(OrderStatus::CANCELLED));
        $this->assertTrue($shipped->canTransitionTo(OrderStatus::DELIVERED));
        $this->assertFalse($shipped->isFinalState());

        $delivered = new Order(['status' => OrderStatus::DELIVERED]);
        $this->assertTrue($delivered->isFinalState());
    }
}
