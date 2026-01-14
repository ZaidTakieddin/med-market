<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine if the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view products
    }

    /**
     * Determine if the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        return true; // All authenticated users can view products
    }

    /**
     * Determine if the user can create products.
     */
    public function create(User $user): bool
    {
        // Only suppliers can create products
        return $user->isSupplier();
    }

    /**
     * Determine if the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        // Only admin or supplier from the same company can update
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() 
            && $user->company_id === $product->supplier_company_id;
    }

    /**
     * Determine if the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        // Only admin or supplier from the same company can delete
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() 
            && $user->company_id === $product->supplier_company_id;
    }
}

