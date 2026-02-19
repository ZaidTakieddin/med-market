<?php

namespace App\Domain\Cart\Services;

use App\Constants\DiscountType;
use App\Constants\OfferType;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Collection;

class CartOfferService
{
    /**
     * Get all available offers for a product based on cart quantity.
     * 
     * @param Product $product
     * @param int $quantity
     * @param Collection|null $cartItems Optional: other cart items for bundle offers
     * @return array Returns array of ['offer' => Offer, 'unit_price' => float, 'total_price' => float, 'savings' => float]
     */
    public function getAvailableOffers(Product $product, int $quantity, ?Collection $cartItems = null): array
    {
        // Get active offers for this product with pivot data
        $offers = $product->offers()
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->withPivot(['quantity_required', 'quantity_free', 'discount_override', 'discount_type_override'])
            ->get();

        if ($offers->isEmpty()) {
            return [];
        }

        $basePrice = $product->getFinalPharmacistPrice();
        $baseTotal = $quantity * $basePrice;
        $availableOffers = [];

        foreach ($offers as $offer) {
            // Check if quantity requirement is met
            $quantityRequired = $offer->pivot->quantity_required ?? $offer->quantity_required;
            
            if ($quantity < $quantityRequired) {
                continue; // Quantity requirement not met
            }

            $result = $this->calculateOfferPrice($offer, $product, $quantity, $cartItems);
            
            if ($result) {
                $savings = $baseTotal - $result['total_price'];
                $availableOffers[] = [
                    'offer' => $offer,
                    'unit_price' => $result['unit_price'],
                    'total_price' => $result['total_price'],
                    'savings' => round($savings, 2),
                    'savings_percentage' => $baseTotal > 0 ? round(($savings / $baseTotal) * 100, 2) : 0,
                ];
            }
        }

        // Sort by total price (lowest first)
        usort($availableOffers, function ($a, $b) {
            return $a['total_price'] <=> $b['total_price'];
        });

        return $availableOffers;
    }

    /**
     * Find and apply the best offer for a product based on cart quantity.
     * 
     * @param Product $product
     * @param int $quantity
     * @param Collection|null $cartItems Optional: other cart items for bundle offers
     * @return array|null Returns ['offer' => Offer, 'unit_price' => float, 'total_price' => float] or null
     */
    public function findBestOffer(Product $product, int $quantity, ?Collection $cartItems = null): ?array
    {
        // Get active offers for this product with pivot data
        $offers = $product->offers()
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->withPivot(['quantity_required', 'quantity_free', 'discount_override', 'discount_type_override'])
            ->get();

        if ($offers->isEmpty()) {
            return null;
        }

        $bestOffer = null;
        $bestPrice = $product->getFinalPharmacistPrice();
        $bestTotal = $quantity * $bestPrice;

        foreach ($offers as $offer) {
            // Check if quantity requirement is met
            $quantityRequired = $offer->pivot->quantity_required ?? $offer->quantity_required;
            
            if ($quantity < $quantityRequired) {
                continue; // Quantity requirement not met
            }

            $result = $this->calculateOfferPrice($offer, $product, $quantity, $cartItems);
            
            if ($result && $result['total_price'] < $bestTotal) {
                $bestOffer = $offer;
                $bestPrice = $result['unit_price'];
                $bestTotal = $result['total_price'];
            }
        }

        if ($bestOffer) {
            return [
                'offer' => $bestOffer,
                'unit_price' => $bestPrice,
                'total_price' => $bestTotal,
            ];
        }

        return null;
    }

    /**
     * Calculate the price after applying an offer.
     * 
     * @param Offer $offer
     * @param Product $product
     * @param int $quantity
     * @param Collection|null $cartItems
     * @return array|null Returns ['unit_price' => float, 'total_price' => float] or null
     */
    protected function calculateOfferPrice(Offer $offer, Product $product, int $quantity, ?Collection $cartItems = null): ?array
    {
        $basePrice = $product->getFinalPharmacistPrice();
        $quantityRequired = $offer->pivot->quantity_required ?? $offer->quantity_required;

        // Check if quantity requirement is met
        if ($quantity < $quantityRequired) {
            return null;
        }

        switch ($offer->offer_type) {
            case OfferType::BUY_X_GET_Y:
                return $this->calculateBuyXGetY($offer, $basePrice, $quantity, $quantityRequired);
            
            case OfferType::PERCENTAGE_DISCOUNT:
                return $this->calculatePercentageDiscount($offer, $basePrice, $quantity, $quantityRequired);
            
            case OfferType::FIXED_DISCOUNT:
                return $this->calculateFixedDiscount($offer, $basePrice, $quantity, $quantityRequired);
            
            case OfferType::BUNDLE:
                return $this->calculateBundle($offer, $product, $quantity, $cartItems, $quantityRequired);
            
            default:
                return null;
        }
    }

    /**
     * Calculate price for Buy X Get Y offer.
     */
    protected function calculateBuyXGetY(Offer $offer, float $basePrice, int $quantity, int $quantityRequired): array
    {
        $quantityFree = $offer->pivot->quantity_free ?? $offer->quantity_free ?? 0;
        
        // Calculate how many times the offer applies
        $offerMultiplier = floor($quantity / $quantityRequired);
        $freeItems = $offerMultiplier * $quantityFree;
        $paidItems = $quantity - $freeItems;
        
        $totalPrice = $paidItems * $basePrice;
        $unitPrice = $quantity > 0 ? $totalPrice / $quantity : $basePrice;
        
        return [
            'unit_price' => round($unitPrice, 2),
            'total_price' => round($totalPrice, 2),
        ];
    }

    /**
     * Calculate price for Percentage Discount offer.
     */
    protected function calculatePercentageDiscount(Offer $offer, float $basePrice, int $quantity, int $quantityRequired): ?array
    {
        // Use pivot discount_override if available, otherwise use offer discount_value
        $discountValue = $offer->pivot->discount_override ?? $offer->discount_value;
        $discountType = $offer->pivot->discount_type_override ?? $offer->discount_type;
        
        if (!$discountValue || !$discountType) {
            return null;
        }

        // Calculate how many items qualify for discount
        $qualifiedQuantity = floor($quantity / $quantityRequired) * $quantityRequired;
        $regularQuantity = $quantity - $qualifiedQuantity;
        
        $discountedPrice = $basePrice;
        if ($discountType === DiscountType::PERCENTAGE) {
            $discountedPrice = $basePrice * (1 - ($discountValue / 100));
        } elseif ($discountType === DiscountType::FIXED) {
            $discountedPrice = max(0, $basePrice - $discountValue);
        }
        
        $totalPrice = ($qualifiedQuantity * $discountedPrice) + ($regularQuantity * $basePrice);
        $unitPrice = $quantity > 0 ? $totalPrice / $quantity : $basePrice;
        
        return [
            'unit_price' => round($unitPrice, 2),
            'total_price' => round($totalPrice, 2),
        ];
    }

    /**
     * Calculate price for Fixed Discount offer.
     */
    protected function calculateFixedDiscount(Offer $offer, float $basePrice, int $quantity, int $quantityRequired): ?array
    {
        // Use pivot discount_override if available, otherwise use offer discount_value
        $discountValue = $offer->pivot->discount_override ?? $offer->discount_value;
        
        if (!$discountValue) {
            return null;
        }

        // Calculate how many items qualify for discount
        $qualifiedQuantity = floor($quantity / $quantityRequired) * $quantityRequired;
        $regularQuantity = $quantity - $qualifiedQuantity;
        
        $discountedPrice = max(0, $basePrice - $discountValue);
        $totalPrice = ($qualifiedQuantity * $discountedPrice) + ($regularQuantity * $basePrice);
        $unitPrice = $quantity > 0 ? $totalPrice / $quantity : $basePrice;
        
        return [
            'unit_price' => round($unitPrice, 2),
            'total_price' => round($totalPrice, 2),
        ];
    }

    /**
     * Calculate price for Bundle offer.
     * Bundle offers require multiple products, so we check if all required products are in cart.
     */
    protected function calculateBundle(Offer $offer, Product $product, int $quantity, ?Collection $cartItems, int $quantityRequired): ?array
    {
        if (!$cartItems) {
            return null;
        }

        // Get all products in the bundle offer
        $bundleProducts = $offer->products;
        
        // Check if all bundle products are in cart with required quantities
        $allProductsInCart = true;
        foreach ($bundleProducts as $bundleProduct) {
            $bundleQuantityRequired = $bundleProduct->pivot->quantity_required ?? $quantityRequired;
            
            if ($bundleProduct->id === $product->id) {
                // This is the current product, check if quantity meets requirement
                if ($quantity < $bundleQuantityRequired) {
                    $allProductsInCart = false;
                    break;
                }
            } else {
                // Check if other bundle products are in cart
                $cartItem = $cartItems->firstWhere('product_id', $bundleProduct->id);
                
                if (!$cartItem || $cartItem->quantity < $bundleQuantityRequired) {
                    $allProductsInCart = false;
                    break;
                }
            }
        }

        if (!$allProductsInCart) {
            return null;
        }

        // Apply bundle discount
        $basePrice = $product->getFinalPharmacistPrice();
        $discountValue = $offer->pivot->discount_override ?? $offer->discount_value;
        $discountType = $offer->pivot->discount_type_override ?? $offer->discount_type;
        
        if (!$discountValue || !$discountType) {
            return null;
        }

        $discountedPrice = $basePrice;
        if ($discountType === DiscountType::PERCENTAGE) {
            $discountedPrice = $basePrice * (1 - ($discountValue / 100));
        } elseif ($discountType === DiscountType::FIXED) {
            $discountedPrice = max(0, $basePrice - $discountValue);
        }
        
        $totalPrice = $quantity * $discountedPrice;
        $unitPrice = $discountedPrice;
        
        return [
            'unit_price' => round($unitPrice, 2),
            'total_price' => round($totalPrice, 2),
        ];
    }

    /**
     * Apply a specific offer to a product.
     * 
     * @param Offer $offer
     * @param Product $product
     * @param int $quantity
     * @param Collection|null $cartItems Optional: other cart items for bundle offers
     * @return array|null Returns ['unit_price' => float, 'total_price' => float] or null if offer cannot be applied
     */
    public function applyOffer(Offer $offer, Product $product, int $quantity, ?Collection $cartItems = null): ?array
    {
        // Verify offer is for this product and load pivot data
        $productOffer = $product->offers()
            ->where('offers.id', $offer->id)
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->withPivot(['quantity_required', 'quantity_free', 'discount_override', 'discount_type_override'])
            ->first();

        if (!$productOffer) {
            return null;
        }

        // Reload offer with pivot data
        $offer->setRelation('pivot', $productOffer->pivot);

        // Check quantity requirement
        $quantityRequired = $offer->pivot->quantity_required ?? $offer->quantity_required;
        if ($quantity < $quantityRequired) {
            return null;
        }

        return $this->calculateOfferPrice($offer, $product, $quantity, $cartItems);
    }
}
