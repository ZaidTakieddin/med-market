<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class SyncSupplierCategoriesAndManufacturersSeeder extends Seeder
{
    /**
     * Sync categories and manufacturers for all companies.
     * - Suppliers: from their products
     * - Buyers: from products in their orders
     */
    public function run(): void
    {
        // Sync suppliers from their products
        $suppliers = Company::where('type', 'supplier')->get();
        foreach ($suppliers as $supplier) {
            $categoryIds = Product::where('supplier_company_id', $supplier->id)
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->filter()
                ->values()
                ->toArray();

            $manufacturerIds = Product::where('supplier_company_id', $supplier->id)
                ->whereNotNull('manufacturer_id')
                ->distinct()
                ->pluck('manufacturer_id')
                ->filter()
                ->values()
                ->toArray();

            $supplier->categories()->sync($categoryIds);
            $supplier->manufacturers()->sync($manufacturerIds);
        }

        // Sync buyers from products in their orders
        $buyers = Company::where('type', 'buyer')->get();
        foreach ($buyers as $buyer) {
            $productIds = OrderItem::whereHas('order', fn ($q) => $q->where('buyer_company_id', $buyer->id))
                ->distinct()
                ->pluck('product_id')
                ->filter()
                ->values()
                ->toArray();

            if (empty($productIds)) {
                continue;
            }

            $categoryIds = Product::whereIn('id', $productIds)
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->filter()
                ->values()
                ->toArray();

            $manufacturerIds = Product::whereIn('id', $productIds)
                ->whereNotNull('manufacturer_id')
                ->distinct()
                ->pluck('manufacturer_id')
                ->filter()
                ->values()
                ->toArray();

            $buyer->categories()->sync($categoryIds);
            $buyer->manufacturers()->sync($manufacturerIds);
        }

        $this->command->info('تم مزامنة الفئات والشركات المصنعة لجميع الموردين والصيدليات.');
    }
}
