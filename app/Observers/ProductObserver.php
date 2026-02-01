<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->syncSupplierCategoriesAndManufacturers($product);
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        // Sync if category_id or manufacturer_id changed
        if ($product->wasChanged(['category_id', 'manufacturer_id', 'supplier_company_id'])) {
            $this->syncSupplierCategoriesAndManufacturers($product);

            // Also sync the old supplier if supplier_company_id changed
            if ($product->wasChanged('supplier_company_id')) {
                $oldSupplierId = $product->getOriginal('supplier_company_id');
                if ($oldSupplierId) {
                    $this->syncSupplierFromProducts($oldSupplierId);
                }
            }
        }
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        // Sync supplier after product deletion to remove unused categories/manufacturers
        if ($product->supplier_company_id) {
            $this->syncSupplierFromProducts($product->supplier_company_id);
        }
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        $this->syncSupplierCategoriesAndManufacturers($product);
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        // Sync supplier after force deletion
        if ($product->supplier_company_id) {
            $this->syncSupplierFromProducts($product->supplier_company_id);
        }
    }

    /**
     * Sync supplier categories and manufacturers for a specific product.
     */
    protected function syncSupplierCategoriesAndManufacturers(Product $product): void
    {
        if (!$product->supplier_company_id) {
            return;
        }

        $this->syncSupplierFromProducts($product->supplier_company_id);
    }

    /**
     * Sync supplier categories and manufacturers based on all their products.
     */
    protected function syncSupplierFromProducts(int $supplierCompanyId): void
    {
        $supplier = \App\Models\Company::find($supplierCompanyId);

        if (!$supplier || !$supplier->isSupplier()) {
            return;
        }

        // Get unique category IDs from supplier's active products
        $categoryIds = \App\Models\Product::where('supplier_company_id', $supplierCompanyId)
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->filter()
            ->toArray();

        // Get unique manufacturer IDs from supplier's active products
        $manufacturerIds = \App\Models\Product::where('supplier_company_id', $supplierCompanyId)
            ->where('is_active', true)
            ->whereNotNull('manufacturer_id')
            ->distinct()
            ->pluck('manufacturer_id')
            ->filter()
            ->toArray();

        // Sync categories
        $supplier->categories()->sync($categoryIds);

        // Sync manufacturers
        $supplier->manufacturers()->sync($manufacturerIds);
    }
}
