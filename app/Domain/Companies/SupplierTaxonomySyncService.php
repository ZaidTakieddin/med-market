<?php

namespace App\Domain\Companies;

use App\Models\Company;
use App\Models\Product;

class SupplierTaxonomySyncService
{
    /**
     * Sync a supplier's categories and manufacturers to match the distinct set
     * derived from their active products.
     *
     * Safe to call once after a bulk product import (or per-product from the
     * ProductObserver) — it recomputes the full set from the products table.
     */
    public function syncForCompany(int $supplierCompanyId): void
    {
        $supplier = Company::find($supplierCompanyId);

        if (! $supplier || ! $supplier->isSupplier()) {
            return;
        }

        $categoryIds = Product::query()
            ->where('supplier_company_id', $supplierCompanyId)
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->filter()
            ->all();

        $manufacturerIds = Product::query()
            ->where('supplier_company_id', $supplierCompanyId)
            ->where('is_active', true)
            ->whereNotNull('manufacturer_id')
            ->distinct()
            ->pluck('manufacturer_id')
            ->filter()
            ->all();

        $supplier->categories()->sync($categoryIds);
        $supplier->manufacturers()->sync($manufacturerIds);
    }
}
