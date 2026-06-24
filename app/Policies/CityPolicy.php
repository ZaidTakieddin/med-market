<?php

namespace App\Policies;

use App\Models\City;
use App\Models\User;

class CityPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, City $city): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, City $city): bool
    {
        return $user->isAdmin();
    }
}
