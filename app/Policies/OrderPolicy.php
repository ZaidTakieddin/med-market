<?php

namespace App\Policies;

use App\Constants\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine if the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view orders
    }

    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        // Users can view orders if:
        // 1. They are admin
        // 2. They belong to the buyer company
        // 3. They belong to the supplier company
        if ($user->isAdmin()) {
            return true;
        }

        return $user->company_id === $order->buyer_company_id 
            || $user->company_id === $order->supplier_company_id;
    }

    /**
     * Determine if the user can create orders.
     */
    public function create(User $user): bool
    {
        // Only buyers (pharmacists) can create orders
        return $user->isBuyer();
    }

    /**
     * Determine if the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        // Only admin or users from buyer/supplier company can update
        if ($user->isAdmin()) {
            return true;
        }

        return $user->company_id === $order->buyer_company_id 
            || $user->company_id === $order->supplier_company_id;
    }

    /**
     * Determine if the user can accept the order.
     */
    public function accept(User $user, Order $order): bool
    {
        // Only supplier users can accept orders
        if (!$user->isSupplier()) {
            return false;
        }

        // Must belong to the supplier company
        return $user->company_id === $order->supplier_company_id
            && $order->status === 'pending';
    }

    /**
     * Determine if the user can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        // Admin can always cancel
        if ($user->isAdmin()) {
            return true;
        }

        // Buyer can cancel if order is pending or accepted
        if ($user->isBuyer() && $user->company_id === $order->buyer_company_id) {
            return in_array($order->status, ['pending', 'accepted'], true);
        }

        // Supplier can cancel if order is pending, accepted, or prepared
        if ($user->isSupplier() && $user->company_id === $order->supplier_company_id) {
            return in_array($order->status, ['pending', 'accepted', 'prepared'], true);
        }

        return false;
    }

    /**
     * Determine if the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        // Only admin can delete (soft delete)
        return $user->isAdmin();
    }
}

