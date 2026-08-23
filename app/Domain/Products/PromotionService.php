<?php

namespace App\Domain\Products;

use App\Models\Offer;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    /**
     * Create a promotional offer for the given supplier.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id?: int, id?: int, quantity?: int}>|null  $products  Per-product quantities (bundle offers)
     * @param  list<int>|null  $productIds  Flat product list using the offer-level quantity_required
     */
    public function create(int $supplierCompanyId, array $data, ?array $products = null, ?array $productIds = null): Offer
    {
        return DB::transaction(function () use ($supplierCompanyId, $data, $products, $productIds): Offer {
            $offer = Offer::create([
                'supplier_company_id' => $supplierCompanyId,
                ...$data,
            ]);

            $this->syncProducts($offer, $products, $productIds);

            return $offer;
        });
    }

    /**
     * Update an offer's attributes and (when provided) its product pivot.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id?: int, id?: int, quantity?: int}>|null  $products
     * @param  list<int>|null  $productIds
     */
    public function update(Offer $offer, array $data, ?array $products = null, ?array $productIds = null): Offer
    {
        DB::transaction(function () use ($offer, $data, $products, $productIds): void {
            $offer->update($data);

            if ($products !== null || $productIds !== null) {
                $this->syncProducts($offer, $products, $productIds);
            }
        });

        return $offer->refresh();
    }

    public function delete(Offer $offer): void
    {
        $offer->delete();
    }

    /**
     * Sync offer products with optional per-product quantity (for bundle offers).
     *
     * @param  list<array{product_id?: int, id?: int, quantity?: int}>|null  $products
     * @param  list<int>|null  $productIds
     */
    private function syncProducts(Offer $offer, ?array $products, ?array $productIds): void
    {
        if (! empty($products)) {
            $syncData = [];
            foreach ($products as $item) {
                $productId = $item['product_id'] ?? $item['id'] ?? null;
                $quantity = (int) ($item['quantity'] ?? 1);
                if ($productId) {
                    $syncData[$productId] = ['quantity_required' => $quantity];
                }
            }
            $offer->products()->sync($syncData);
        } elseif (! empty($productIds)) {
            $quantityRequired = $offer->quantity_required ?? 1;
            $syncData = array_fill_keys($productIds, ['quantity_required' => $quantityRequired]);
            $offer->products()->sync($syncData);
        }
    }
}
