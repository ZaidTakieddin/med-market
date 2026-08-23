<?php

namespace App\Domain\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Create a product for the given supplier. Stock is only settable at creation;
     * later stock changes must go through StockMovementService (rule #4).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $supplierCompanyId, array $data): Product
    {
        return DB::transaction(function () use ($supplierCompanyId, $data): Product {
            /** @var Product */
            return Product::create([
                'supplier_company_id' => $supplierCompanyId,
                ...$data,
            ]);
        });
    }

    /**
     * Update a product's catalog attributes (never its stock).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        unset($data['stock_quantity']);

        $product->update($data);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
