<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cart\WishlistService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CartResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Wishlist', description: 'Per-user product wishlist. Items can be moved to the cart at current server-resolved prices.')]
class WishlistController extends BaseApiController
{
    public function __construct(
        private WishlistService $wishlistService
    ) {}

    /**
     * List my wishlist.
     */
    #[OA\Get(
        path: '/wishlist',
        summary: 'List my wishlist',
        description: "The authenticated user's wishlisted products, newest first (visibility-scoped products only).",
        tags: ['Wishlist'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Wishlist retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(): JsonResponse
    {
        $products = $this->wishlistService->items($this->currentUser());

        return $this->success(
            ProductResource::collection($products),
            'Wishlist retrieved successfully'
        );
    }

    /**
     * Add a product to my wishlist.
     */
    #[OA\Post(
        path: '/wishlist/items',
        summary: 'Add product to wishlist',
        tags: ['Wishlist'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['product_id'], properties: [
            new OA\Property(property: 'product_id', type: 'integer'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Product added (idempotent)'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function addItem(Request $request): JsonResponse
    {
        $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);

        $this->wishlistService->add($this->currentUser(), (int) $request->product_id);

        return $this->success(null, 'Product added to wishlist');
    }

    /**
     * Remove a product from my wishlist.
     */
    #[OA\Delete(
        path: '/wishlist/items/{productId}',
        summary: 'Remove product from wishlist',
        tags: ['Wishlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Product removed'),
        ]
    )]
    public function removeItem(Product $product): JsonResponse
    {
        $this->wishlistService->remove($this->currentUser(), (int) $product->id);

        return $this->success(null, 'Product removed from wishlist');
    }

    /**
     * Move a wishlisted product to the cart.
     */
    #[OA\Post(
        path: '/wishlist/items/{productId}/move-to-cart',
        summary: 'Move wishlist item to cart',
        description: 'Adds the product to the cart at current server-resolved pricing (with best offer), then removes it from the wishlist.',
        tags: ['Wishlist'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'productId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'quantity', type: 'integer', default: 1, minimum: 1),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Cart returned with the item added; the wishlist entry is removed'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function moveToCart(Request $request, Product $product): JsonResponse
    {
        $request->validate(['quantity' => ['nullable', 'integer', 'min:1']]);

        $cart = $this->wishlistService->moveToCart(
            user: $this->currentUser(),
            product: $product,
            quantity: max(1, $request->integer('quantity', 1)),
        );

        $cart->load([
            'items.product.supplierCompany',
            'items.product.category',
            'items.product.manufacturer',
            'items.product.offers',
            'items.appliedOffer',
            'user',
            'company',
        ]);

        return $this->success(new CartResource($cart), 'Product moved to cart');
    }
}
