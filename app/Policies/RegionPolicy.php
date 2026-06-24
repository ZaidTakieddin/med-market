<?php

namespace App\Policies;

use App\Models\Region;
use App\Models\User;

class RegionPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Region $region): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Region $region): bool
    {
        return $user->isAdmin();
    }
}
