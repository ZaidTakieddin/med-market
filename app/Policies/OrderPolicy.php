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

    public function accept(User $user, Order $order): bool
    {
        if (!$user->isSupplier()) {
            return false;
        }

        return $user->company_id === $order->supplier_company_id
            && $order->status === OrderStatus::PENDING;
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

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}

