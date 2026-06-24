<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function create(User $user): bool
    {
        return $user->isSupplier();
    }

    public function update(User $user, Offer $offer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $offer->supplier_company_id;
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $this->update($user, $offer);
    }
}
