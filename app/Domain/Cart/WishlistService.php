<?php

namespace App\Domain\Cart;

use App\Exceptions\DomainException;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Collection;

class WishlistService
{
    public function __construct(
        private CartService $cartService
    ) {}

    /**
     * The user's wishlisted products, newest first.
     *
     * @return Collection<int, Product>
     */
    public function items(User $user): Collection
    {
        return $user->wishlistItems()
            ->with(['product.supplierCompany', 'product.category', 'product.manufacturer', 'product.images'])
            ->latest()
            ->get()
            ->pluck('product')
            ->filter();
    }

    /**
     * Add a product to the user's wishlist (idempotent).
     */
    public function add(User $user, int $productId): void
    {
        WishlistItem::query()->firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);
    }

    /**
     * Remove a product from the wishlist.
     */
    public function remove(User $user, int $productId): int
    {
        return WishlistItem::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();
    }

    /**
     * Move a wishlisted product into the cart at server-resolved pricing, then drop
     * it from the wishlist.
     *
     * @throws DomainException When the item is not wishlisted or cannot be added
     */
    public function moveToCart(User $user, Product $product, int $quantity = 1): \App\Models\Cart
    {
        $exists = WishlistItem::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if (! $exists) {
            throw new DomainException('This product is not in your wishlist.');
        }

        $cart = $this->cartService->addItem($user, $product, $quantity);

        $this->remove($user, (int) $product->id);

        return $cart;
    }
}
