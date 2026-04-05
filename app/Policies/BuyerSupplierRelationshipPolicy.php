<?php

namespace App\Policies;

use App\Models\BuyerSupplierRelationship;
use App\Models\User;

class BuyerSupplierRelationshipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BuyerSupplierRelationship $relationship): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->company_id === $relationship->buyer_company_id
            || $user->company_id === $relationship->supplier_company_id;
    }

    /**
     * Only suppliers and admins can create relationships.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSupplier();
    }

    /**
     * Only the supplier side or admin can update terms/credit.
     */
    public function update(User $user, BuyerSupplierRelationship $relationship): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $relationship->supplier_company_id;
    }

    /**
     * Only admin or supplier side can delete.
     */
    public function delete(User $user, BuyerSupplierRelationship $relationship): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $relationship->supplier_company_id;
    }

    /**
     * Only the supplier side or admin can approve/reject.
     */
    public function approve(User $user, BuyerSupplierRelationship $relationship): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $relationship->supplier_company_id;
    }

    /**
     * Only the supplier side or admin can adjust credit balance.
     */
    public function adjustCredit(User $user, BuyerSupplierRelationship $relationship): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $relationship->supplier_company_id;
    }
}
