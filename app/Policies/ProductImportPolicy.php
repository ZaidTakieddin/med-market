<?php

namespace App\Policies;

use App\Models\ProductImport;
use App\Models\User;

class ProductImportPolicy
{
    /**
     * Determine if the user can list product imports.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupplier();
    }

    /**
     * Determine if the user can view a specific import (own company only).
     */
    public function view(User $user, ProductImport $productImport): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier()
            && $user->company_id === $productImport->supplier_company_id;
    }

    /**
     * Determine if the user can start a product import.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSupplier();
    }
}
