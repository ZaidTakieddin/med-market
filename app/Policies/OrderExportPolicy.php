<?php

namespace App\Policies;

use App\Models\OrderExport;
use App\Models\User;

class OrderExportPolicy
{
    /**
     * Determine if the user can request order exports. Drivers do not place orders,
     * so they have nothing to export.
     */
    public function create(User $user): bool
    {
        return ! $user->isDriver();
    }

    /**
     * Determine if the user can list exports: admins see all, everyone else sees
     * their own company's exports (further scoped in the query).
     */
    public function viewAny(User $user): bool
    {
        return ! $user->isDriver();
    }

    /**
     * Determine if the user can view/download a specific export: the requesting
     * user, a member of the same company, or an admin.
     */
    public function view(User $user, OrderExport $orderExport): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDriver()) {
            return false;
        }

        return $user->id === $orderExport->user_id
            || $user->company_id === $orderExport->company_id;
    }
}
