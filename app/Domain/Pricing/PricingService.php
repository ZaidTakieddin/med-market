<?php

namespace App\Domain\Pricing;

use App\Constants\BuyerClassification;
use App\Constants\PricingType;
use App\Constants\SupplierClassification;
use App\Models\Company;
use App\Models\PriceList;
use App\Models\Product;

class PricingService
{
    /**
     * Resolve the correct price for a product given buyer context and quantity.
     *
     * Resolution order:
     * 1. Custom price list (highest priority)
     * 2. Wholesale tier (if eligible + qty met)
     * 3. Retail fallback (pharmacist_price)
     * 4. Product-level discount applied on top
     */
    public function resolve(Product $product, Company $buyerCompany, int $quantity = 1): PriceResult
    {
        $supplierCompany = $product->supplierCompany ?? Company::find($product->supplier_company_id);
        $wholesaleEligible = $this->isWholesaleEligible($supplierCompany, $buyerCompany);
        $customResult = $this->checkCustomPriceList($product, $supplierCompany, $buyerCompany, $quantity);

        return $this->buildPriceResult($product, $quantity, $wholesaleEligible, $customResult);
    }

    /**
     * Resolve prices for multiple products at once (batch).
     * Optimizes by preloading price lists.
     *
     * @param  array<array{product: Product, quantity: int}>  $items
     * @return array<int, PriceResult> Keyed by product ID
     */
    public function resolveMany(array $items, Company $buyerCompany): array
    {
        $productIds = array_map(fn ($item) => $item['product']->id, $items);
        $supplierIds = array_unique(array_map(fn ($item) => $item['product']->supplier_company_id, $items));

        $customPrices = PriceList::currentlyValid()
            ->where('buyer_company_id', $buyerCompany->id)
            ->whereIn('product_id', $productIds)
            ->whereIn('supplier_company_id', $supplierIds)
            ->orderBy('min_qty', 'desc')
            ->get()
            ->groupBy('product_id');

        $results = [];

        foreach ($items as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];
            $supplierCompany = $product->supplierCompany ?? Company::find($product->supplier_company_id);
            $wholesaleEligible = $this->isWholesaleEligible($supplierCompany, $buyerCompany);
            $customResult = $this->findBestCustomPrice($customPrices->get($product->id), $quantity);

            $results[$product->id] = $this->buildPriceResult($product, $quantity, $wholesaleEligible, $customResult);
        }

        return $results;
    }

    /**
     * Build the PriceResult for a product given precomputed buyer context. Resolution
     * order: custom price list -> wholesale tier (NO product discount) -> retail
     * (pharmacist price with product discount). Shared by resolve() and resolveMany()
     * so single-item and batch pricing are always identical.
     *
     * @param  array{price: float, note: string|null}|null  $customResult
     */
    private function buildPriceResult(Product $product, int $quantity, bool $wholesaleEligible, ?array $customResult): PriceResult
    {
        $minQtyMet = $product->qualifiesForWholesale($quantity);

        if ($customResult !== null) {
            return new PriceResult(
                unitPrice: $customResult['price'],
                originalPrice: $customResult['price'],
                pricingType: PricingType::CUSTOM,
                discountAmount: 0,
                wholesaleEligible: $wholesaleEligible,
                minQtyMet: $minQtyMet,
                customPriceNote: $customResult['note'],
            );
        }

        if ($wholesaleEligible && $minQtyMet && $product->getEffectiveWholesalePrice() !== null) {
            $wholesalePrice = $product->getEffectiveWholesalePrice();

            return new PriceResult(
                unitPrice: $wholesalePrice,
                originalPrice: $wholesalePrice,
                pricingType: PricingType::WHOLESALE,
                discountAmount: 0,
                wholesaleEligible: true,
                minQtyMet: true,
            );
        }

        $originalPrice = $product->getEffectivePharmacistPrice();
        $finalPrice = $this->applyProductDiscount($product, $originalPrice);

        return new PriceResult(
            unitPrice: $finalPrice,
            originalPrice: $originalPrice,
            pricingType: PricingType::RETAIL,
            discountAmount: $originalPrice - $finalPrice,
            wholesaleEligible: $wholesaleEligible,
            minQtyMet: $minQtyMet,
        );
    }

    /**
     * Check if the buyer is eligible for wholesale pricing from this supplier.
     */
    public function isWholesaleEligible(Company $supplierCompany, Company $buyerCompany): bool
    {
        $supplierCanWholesale = in_array(
            $supplierCompany->supplier_classification,
            SupplierClassification::wholesaleCapable(),
            true
        );

        $buyerIsEligible = in_array(
            $buyerCompany->buyer_classification,
            BuyerClassification::wholesaleEligible(),
            true
        );

        return $supplierCanWholesale && $buyerIsEligible;
    }

    /**
     * Check if a buyer can see this supplier's catalog. Pharmacy buyers reach only
     * retail + hybrid suppliers (wholesale-only hidden); salesman buyers reach only
     * wholesale + hybrid suppliers (pure-retail hidden). Other buyers are unrestricted.
     */
    public function canBuyerSeeSupplier(Company $supplierCompany, Company $buyerCompany): bool
    {
        $allowed = match ($buyerCompany->buyer_classification) {
            BuyerClassification::PHARMACY => SupplierClassification::retailCapable(),
            BuyerClassification::SALESMAN => SupplierClassification::wholesaleCapable(),
            default => null,
        };

        if ($allowed === null) {
            return true;
        }

        return in_array($supplierCompany->supplier_classification, $allowed, true);
    }

    /**
     * Get a pricing summary for a product visible to a specific buyer.
     * Useful for product listing pages to show the applicable price.
     */
    public function getDisplayPrice(Product $product, Company $buyerCompany, int $quantity = 1): array
    {
        $result = $this->resolve($product, $buyerCompany, $quantity);

        $display = [
            'unit_price' => round($result->unitPrice, 2),
            'pricing_type' => $result->pricingType,
        ];

        if ($result->hasDiscount()) {
            $display['original_price'] = round($result->originalPrice, 2);
            $display['discount_amount'] = round($result->discountAmount, 2);
        }

        if ($result->wholesaleEligible && $product->getEffectiveWholesalePrice() !== null) {
            $display['wholesale_price'] = round($product->getEffectiveWholesalePrice(), 2);
            $display['wholesale_min_qty'] = $product->wholesale_min_qty;
            $display['wholesale_qty_met'] = $result->minQtyMet;
        }

        if ($result->isCustom()) {
            $display['custom_price_note'] = $result->customPriceNote;
        }

        return $display;
    }

    /**
     * Check custom price list for a specific buyer-supplier-product combination.
     */
    private function checkCustomPriceList(Product $product, Company $supplierCompany, Company $buyerCompany, int $quantity): ?array
    {
        $entries = PriceList::currentlyValid()
            ->where('supplier_company_id', $supplierCompany->id)
            ->where('buyer_company_id', $buyerCompany->id)
            ->where('product_id', $product->id)
            ->orderBy('min_qty', 'desc')
            ->get();

        return $this->findBestCustomPrice($entries, $quantity);
    }

    /**
     * Find the best matching custom price from a collection of price list entries.
     * Picks the entry with the highest min_qty that the given quantity satisfies.
     */
    private function findBestCustomPrice($entries, int $quantity): ?array
    {
        if ($entries === null || $entries->isEmpty()) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry->min_qty === null || $quantity >= $entry->min_qty) {
                return [
                    'price' => (float) $entry->custom_price,
                    'note' => $entry->notes,
                ];
            }
        }

        return null;
    }

    /**
     * Apply product-level discount (percentage or fixed) to a base price.
     */
    private function applyProductDiscount(Product $product, float $price): float
    {
        if (! $product->discount || ! $product->discount_type) {
            return $price;
        }

        if ($product->discount_type === 'percentage') {
            return max(0, $price * (1 - (min($product->discount, 100) / 100)));
        }

        return max(0, $price - $product->discount);
    }
}
