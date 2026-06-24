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
        return $user->isSupplier() || $user->isAdmin();
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

    /**
     * Determine if the user can add or adjust stock for the product (owning supplier only).
     */
    public function mutateStock(User $user, Product $product): bool
    {
        return $user->isSupplier()
            && $user->company_id === $product->supplier_company_id;
    }

    /**
     * Determine if the user can view the product's stock movement history.
     */
    public function viewStockMovements(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier()
            && $user->company_id === $product->supplier_company_id;
    }
}
