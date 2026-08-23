<?php

namespace App\Domain\Cart;

use App\Domain\Cart\Services\CartOfferService;
use App\Domain\Pricing\PricingService;
use App\Exceptions\DomainException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        private PricingService $pricingService,
        private CartOfferService $cartOfferService
    ) {}

    /**
     * Add a product to the user's cart at server-resolved tier pricing with the
     * best applicable offer. Visibility and availability rules are enforced here
     * so every caller (cart endpoint, wishlist move-to-cart) behaves identically.
     *
     * @throws DomainException When the product/company is unavailable to the buyer
     */
    public function addItem(User $user, Product $product, int $quantity, ?string $notes = null): Cart
    {
        if ($quantity < 1) {
            throw new DomainException('Quantity must be at least 1.');
        }

        if (! $product->is_active) {
            throw new DomainException('Product is not available.');
        }

        $buyerCompany = $user->company;
        if ($buyerCompany === null) {
            throw new DomainException('User has no company assigned.');
        }

        if (! $this->pricingService->canBuyerSeeSupplier($product->supplierCompany, $buyerCompany)) {
            throw new DomainException('This product is not available for your account type.');
        }

        return DB::transaction(function () use ($user, $product, $buyerCompany, $quantity, $notes): Cart {
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['company_id' => $user->company_id]
            );

            $product->load('offers');
            $currentCartItems = $cart->items()->with('product')->get();
            $existingItem = $cart->items()->where('product_id', $product->id)->first();

            $effectiveQuantity = $existingItem !== null ? $existingItem->quantity + $quantity : $quantity;
            $priceResult = $this->pricingService->resolve($product, $buyerCompany, $effectiveQuantity);
            $offerResult = $this->cartOfferService->findBestOffer($product, $effectiveQuantity, $currentCartItems, $priceResult->unitPrice);

            if ($existingItem !== null) {
                $existingItem->quantity = $effectiveQuantity;

                if ($offerResult !== null) {
                    $existingItem->unit_price = $offerResult['unit_price'];
                    $existingItem->total_price = $offerResult['total_price'];
                    $existingItem->applied_offer_id = $offerResult['offer']->id;
                } else {
                    $existingItem->unit_price = $priceResult->unitPrice;
                    $existingItem->total_price = $effectiveQuantity * $priceResult->unitPrice;
                    $existingItem->applied_offer_id = null;
                }

                $existingItem->pricing_type = $priceResult->pricingType;
                if ($notes !== null) {
                    $existingItem->notes = $notes;
                }
                $existingItem->save();
            } elseif ($offerResult !== null) {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $offerResult['unit_price'],
                    'total_price' => $offerResult['total_price'],
                    'pricing_type' => $priceResult->pricingType,
                    'applied_offer_id' => $offerResult['offer']->id,
                    'notes' => $notes,
                ]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $priceResult->unitPrice,
                    'total_price' => $quantity * $priceResult->unitPrice,
                    'pricing_type' => $priceResult->pricingType,
                    'applied_offer_id' => null,
                    'notes' => $notes,
                ]);
            }

            $cart->calculateTotals();

            return $cart;
        });
    }
}
