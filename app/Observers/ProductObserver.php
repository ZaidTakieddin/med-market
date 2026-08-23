<?php

namespace App\Observers;

use App\Domain\Companies\SupplierTaxonomySyncService;
use App\Models\Product;

class ProductObserver
{
    public function __construct(private SupplierTaxonomySyncService $taxonomySync) {}

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

        // Wishlist price-drop alerts.
        if ($product->wasChanged('pharmacist_price')) {
            $this->notifyPriceDrop($product);
        }
    }

    /**
     * Notify users who wishlisted this product when its price drops.
     */
    protected function notifyPriceDrop(Product $product): void
    {
        $oldPrice = (float) $product->getOriginal('pharmacist_price');
        $newPrice = (float) $product->pharmacist_price;

        if (! ($newPrice > 0 && $newPrice < $oldPrice)) {
            return;
        }

        $watchers = \App\Models\WishlistItem::query()
            ->where('product_id', $product->id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($watchers as $user) {
            $user->notify(new \App\Notifications\PriceDropNotification($product, $oldPrice, $newPrice));
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
        if (! $product->supplier_company_id) {
            return;
        }

        $this->syncSupplierFromProducts($product->supplier_company_id);
    }

    /**
     * Sync supplier categories and manufacturers based on all their products.
     */
    protected function syncSupplierFromProducts(int $supplierCompanyId): void
    {
        $this->taxonomySync->syncForCompany($supplierCompanyId);
    }
}
