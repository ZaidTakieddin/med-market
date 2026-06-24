<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only admin can view all users
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Admin can view anyone, users can view themselves or users from same company
        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $model->id
            || ($user->company_id && $user->company_id === $model->company_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admin can create users
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Admin can update anyone, users can update themselves
        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Only admin can delete users
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can list drivers (suppliers and admins).
     */
    public function viewDrivers(User $user): bool
    {
        return $user->isAdmin() || $user->isSupplier();
    }

    /**
     * Determine whether the user can create a driver (suppliers and admins).
     */
    public function createDriver(User $user): bool
    {
        return $user->isAdmin() || $user->isSupplier();
    }

    /**
     * Determine whether the user can view a specific driver: admins, or the supplier
     * that owns the driver's company.
     */
    public function viewDriver(User $user, User $driver): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $driver->company_id;
    }

    /**
     * Determine whether the user can manage a driver (update/delete, regions, holidays,
     * region plan): admins, or the owning supplier. Same scope as viewing.
     */
    public function manageDriver(User $user, User $driver): bool
    {
        return $this->viewDriver($user, $driver);
    }

    /**
     * Determine whether the user can view a salesman: admins, the salesman themselves,
     * or the supplier whose company the salesman belongs to.
     */
    public function accessSalesman(User $user, User $salesman): bool
    {
        if ($user->isAdmin() || $user->id === $salesman->id) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $salesman->company_id;
    }

    /**
     * Determine whether the user can manage a salesman (assign/remove regions and
     * clients): admins, or the supplier whose company the salesman belongs to.
     */
    public function manageSalesman(User $user, User $salesman): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $user->company_id === $salesman->company_id;
    }
}
