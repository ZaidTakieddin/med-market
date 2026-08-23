<?php

namespace App\Policies;

use App\Constants\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDriver()) {
            return $order->driver_id === $user->id;
        }

        return $user->company_id === $order->buyer_company_id
            || $user->company_id === $order->supplier_company_id;
    }

    public function create(User $user): bool
    {
        return $user->canBuy();
    }

    /**
     * Only suppliers and buyers (not drivers) can update orders.
     * Drivers use dedicated delivery endpoints only.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDriver()) {
            return false;
        }

        return $user->company_id === $order->buyer_company_id
            || $user->company_id === $order->supplier_company_id;
    }

    /**
     * Gate an order status transition by role. Forward fulfilment transitions
     * (accepted/prepared/shipped/delivered) are the supplier's responsibility on
     * their own orders; cancellation follows the dedicated cancel rules. Buyers
     * and salesmen never advance an order's status.
     */
    public function updateStatus(User $user, Order $order, string $targetStatus): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($targetStatus === OrderStatus::CANCELLED) {
            return $this->cancel($user, $order);
        }

        return $user->isSupplier() && $user->company_id === $order->supplier_company_id;
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->canBuy() && $user->company_id === $order->buyer_company_id) {
            return in_array($order->status, [OrderStatus::PENDING, OrderStatus::ACCEPTED], true);
        }

        if ($user->isSalesman() && $order->placed_by_salesman_id === $user->id) {
            return in_array($order->status, [OrderStatus::PENDING, OrderStatus::ACCEPTED], true);
        }

        if ($user->isSupplier() && $user->company_id === $order->supplier_company_id) {
            return in_array($order->status, [OrderStatus::PENDING, OrderStatus::ACCEPTED, OrderStatus::PREPARED], true);
        }

        return false;
    }

    /**
     * Only the supplying company (or an admin) may assign drivers to an order.
     * Whether the target user is a valid driver for that company is validated by
     * DriverAssignmentService (as input validation, not authorization).
     */
    public function assignDriver(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $order->supplier_company_id;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
