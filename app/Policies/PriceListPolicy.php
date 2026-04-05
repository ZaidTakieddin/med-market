<?php

namespace App\Policies;

use App\Models\PriceList;
use App\Models\User;

class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PriceList $priceList): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->company_id === $priceList->supplier_company_id
            || $user->company_id === $priceList->buyer_company_id;
    }

    /**
     * Only suppliers and admins can create price lists.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSupplier();
    }

    /**
     * Only the owning supplier or admin can update.
     */
    public function update(User $user, PriceList $priceList): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $priceList->supplier_company_id;
    }

    /**
     * Only the owning supplier or admin can delete.
     */
    public function delete(User $user, PriceList $priceList): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $priceList->supplier_company_id;
    }
}
