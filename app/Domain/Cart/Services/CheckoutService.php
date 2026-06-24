<?php

namespace App\Domain\Cart\Services;

use App\Domain\Orders\OrderCreationService;
use App\Domain\Pricing\PricingService;
use App\Models\Cart;
use App\Models\Company;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a buyer's cart into orders. Prices are re-resolved at checkout (current
 * tier prices, not the price captured when the item was added), credit is enforced
 * by OrderCreationService, and the whole operation is transactional.
 */
class CheckoutService
{
    /**
     * Relationships eager-loaded on every created order for the API response.
     *
     * @var list<string>
     */
    private const ORDER_RELATIONS = [
        'buyerCompany.region.city',
        'supplierCompany.categories',
        'supplierCompany.manufacturers',
        'driver',
        'invoice',
        'items.product.category',
        'items.product.manufacturer',
        'items.product.supplierCompany',
        'items.appliedOffer',
    ];

    public function __construct(
        private OrderCreationService $orderCreationService,
        private CartOfferService $cartOfferService,
        private PricingService $pricingService,
    ) {}

    /**
     * Check out the whole cart, creating one order per supplier, then clear the cart.
     *
     * @return list<Order>
     *
     * @throws \InvalidArgumentException When the cart is empty, the buyer has no company, or a supplier is not reachable.
     */
    public function checkoutCart(User $user, ?string $notes = null, float $taxRate = 0.0): array
    {
        $buyerCompany = $this->buyerCompany($user);
        $cart = $this->cartFor($user);

        return DB::transaction(function () use ($user, $buyerCompany, $cart, $notes, $taxRate): array {
            $createdOrders = [];

            foreach ($cart->itemsBySupplier() as $supplierId => $items) {
                $this->assertSupplierVisible((int) $supplierId, $buyerCompany);

                $order = $this->orderCreationService->create(
                    buyerCompanyId: (int) $user->company_id,
                    supplierCompanyId: (int) $supplierId,
                    createdBy: (int) $user->id,
                    items: $this->resolveCheckoutItems($items, $buyerCompany),
                    notes: $notes,
                    taxRate: $taxRate,
                );

                $createdOrders[] = $order->load(self::ORDER_RELATIONS);
            }

            $cart->items()->delete();
            $cart->update(['subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0]);

            return $createdOrders;
        });
    }

    /**
     * Check out only the items from one supplier; the rest remain in the cart.
     *
     * @return array{order: Order, items_checked_out: int, items_remaining: int}
     *
     * @throws \InvalidArgumentException
     */
    public function checkoutSupplier(User $user, int $supplierId, ?string $notes = null, float $taxRate = 0.0): array
    {
        $buyerCompany = $this->buyerCompany($user);

        $supplier = Company::find($supplierId);
        if ($supplier === null || ! $supplier->isSupplier()) {
            throw new \InvalidArgumentException('The specified company is not a supplier.');
        }
        $this->assertSupplierVisible($supplierId, $buyerCompany);

        $cart = $this->cartFor($user);
        $supplierItems = $cart->items->filter(
            fn ($item) => $item->product instanceof Product && $item->product->supplier_company_id === $supplierId
        );

        if ($supplierItems->isEmpty()) {
            throw new \InvalidArgumentException('No items found for the specified supplier in your cart.');
        }

        return DB::transaction(function () use ($user, $buyerCompany, $cart, $supplierId, $supplierItems, $notes, $taxRate): array {
            $order = $this->orderCreationService->create(
                buyerCompanyId: (int) $user->company_id,
                supplierCompanyId: $supplierId,
                createdBy: (int) $user->id,
                items: $this->resolveCheckoutItems($supplierItems, $buyerCompany),
                notes: $notes,
                taxRate: $taxRate,
            );

            $checkedOut = $supplierItems->count();
            $cart->items()->whereIn('id', $supplierItems->pluck('id'))->delete();
            $cart->refresh();
            $cart->load('items');
            $cart->calculateTotals();

            return [
                'order' => $order->load(self::ORDER_RELATIONS),
                'items_checked_out' => $checkedOut,
                'items_remaining' => $cart->items()->count(),
            ];
        });
    }

    private function buyerCompany(User $user): Company
    {
        $company = $user->company;
        if ($company === null) {
            throw new \InvalidArgumentException('Your account is not linked to a company.');
        }

        return $company;
    }

    private function cartFor(User $user): Cart
    {
        $cart = Cart::where('user_id', $user->id)->with('items.product')->first();
        if ($cart === null || $cart->items->isEmpty()) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        return $cart;
    }

    private function assertSupplierVisible(int $supplierId, Company $buyerCompany): void
    {
        $supplierCompany = Company::find($supplierId);
        if ($supplierCompany !== null && ! $this->pricingService->canBuyerSeeSupplier($supplierCompany, $buyerCompany)) {
            throw new \InvalidArgumentException('A supplier in your cart is not available to your account.');
        }
    }

    /**
     * Re-resolve prices for cart items at checkout: fresh tier price (with any
     * still-valid offer re-applied), not the price captured when added to the cart.
     *
     * @param  iterable<int, \App\Models\CartItem>  $cartItems
     * @return array<int, array<string, mixed>>
     */
    private function resolveCheckoutItems(iterable $cartItems, Company $buyerCompany): array
    {
        $collection = collect($cartItems);

        // Batch-resolve base tier prices once (avoids a price-list query per item).
        $batch = [];
        foreach ($collection as $cartItem) {
            if ($cartItem->product instanceof Product) {
                $batch[] = ['product' => $cartItem->product, 'quantity' => $cartItem->quantity];
            }
        }
        $priceResults = $this->pricingService->resolveMany($batch, $buyerCompany);

        $orderItems = [];

        foreach ($collection as $cartItem) {
            $product = $cartItem->product;
            if (! $product instanceof Product) {
                continue;
            }

            $priceResult = $priceResults[$product->id]
                ?? $this->pricingService->resolve($product, $buyerCompany, $cartItem->quantity);
            $unitPrice = $priceResult->unitPrice;
            $pricingType = $priceResult->pricingType;
            $appliedOfferId = $cartItem->applied_offer_id;

            if ($appliedOfferId) {
                $offer = Offer::find($appliedOfferId);
                $offerResult = $offer
                    ? $this->cartOfferService->applyOffer($offer, $product, $cartItem->quantity, $collection, $unitPrice)
                    : null;

                if ($offerResult !== null) {
                    $unitPrice = $offerResult['unit_price'];
                } else {
                    $appliedOfferId = null;
                }
            }

            $orderItems[] = [
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
                'unit_price' => $unitPrice,
                'pricing_type' => $pricingType,
                'notes' => $cartItem->notes,
                'applied_offer_id' => $appliedOfferId,
            ];
        }

        return $orderItems;
    }
}
