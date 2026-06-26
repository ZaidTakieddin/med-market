<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cart\Services\CartOfferService;
use App\Domain\Cart\Services\CheckoutService;
use App\Domain\Pricing\PricingService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\ApplyOfferRequest;
use App\Http\Requests\Cart\CheckoutSupplierRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Cart',
    description: 'Shopping cart management. **Offer system**: Each cart item can have one offer applied. The system auto-selects the best offer (lowest total price) per product. Offer types: buy_x_get_y, percentage_discount, fixed_discount, bundle_percentage, bundle_fixed. Bundle offers require ALL products in the offer to be in cart with required quantities. Cart items include baseUnitPrice/baseTotalPrice (before offer) for displaying price changes.'
)]
class CartController extends BaseApiController
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartOfferService $cartOfferService,
        private PricingService $pricingService
    ) {}

    /**
     * Get current user's cart.
     */
    #[OA\Get(
        path: '/cart',
        summary: 'Get cart',
        description: 'Get the current user\'s shopping cart with all items. Items are grouped by supplier to show how checkout will split orders. Each cart item includes baseUnitPrice/baseTotalPrice (before offer) and unitPrice/totalPrice (after offer when applied) for displaying price changes.',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cart retrieved successfully. Items include baseUnitPrice, baseTotalPrice (before offer), unitPrice, totalPrice (after offer), appliedOfferId.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    description: 'All cart items (flat array). Each has baseUnitPrice, baseTotalPrice, unitPrice, totalPrice, appliedOfferId.',
                                    items: new OA\Items(ref: '#/components/schemas/CartItem')
                                ),
                                new OA\Property(
                                    property: 'itemsBySupplier',
                                    type: 'array',
                                    description: 'Items grouped by supplier (matches checkout behavior)',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'supplier', type: 'object', description: 'Supplier company object'),
                                            new OA\Property(
                                                property: 'items',
                                                type: 'array',
                                                description: 'Cart items for this supplier',
                                                items: new OA\Items(ref: '#/components/schemas/CartItem')
                                            ),
                                            new OA\Property(property: 'subtotal', type: 'number', format: 'float', description: 'Subtotal for this supplier\'s items'),
                                            new OA\Property(property: 'itemCount', type: 'integer', description: 'Number of items from this supplier'),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'hasMultipleSuppliers', type: 'boolean', example: true),
                                new OA\Property(property: 'suppliersCount', type: 'integer', example: 2),
                                new OA\Property(property: 'subtotal', type: 'number', format: 'float'),
                                new OA\Property(property: 'totalAmount', type: 'number', format: 'float'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->currentUser();

            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['company_id' => $user->company_id]
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

            return $this->success(
                new CartResource($cart),
                'Cart retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Cart retrieval error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to retrieve cart. Please try again later.', null, 500);
        }
    }

    /**
     * Add item to cart.
     */
    #[OA\Post(
        path: '/cart/items',
        summary: 'Add item to cart',
        description: 'Add a product to the shopping cart. The system auto-selects the best offer (lowest total) for this product. If product already in cart, quantity is added and offers re-evaluated. Bundle offers require all bundle products in cart. Response includes baseUnitPrice/baseTotalPrice (before offer) for displaying price changes.',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_id', 'quantity'],
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 1, description: 'Product ID to add to cart'),
                    new OA\Property(property: 'quantity', type: 'integer', example: 2, description: 'Quantity to add'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Special instructions'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Item added. Returns full cart with baseUnitPrice, baseTotalPrice, unitPrice, totalPrice, appliedOfferId per item.'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only buyers can add items to cart'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 400, description: 'Product is not active'),
        ]
    )]
    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();

            if (! $user->canBuy()) {
                return $this->error('Only buyers and salesmen can add items to cart', null, 403);
            }

            if ($user->isSupplierSalesman()) {
                return $this->error('Supplier-associated salesmen must use the salesman place-order endpoint instead of the cart', null, 403);
            }

            $product = Product::findOrFail((int) $request->product_id);

            if (! $product->is_active) {
                return $this->error('Product is not available', null, 400);
            }

            $buyerCompany = $user->company;
            if (! $buyerCompany) {
                return $this->error('User has no company assigned', null, 400);
            }

            if (! $this->pricingService->canBuyerSeeSupplier($product->supplierCompany, $buyerCompany)) {
                return $this->error('This product is not available for your account type', null, 403);
            }

            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['company_id' => $user->company_id]
            );

            $product->load('offers');
            $currentCartItems = $cart->items()->with('product')->get();
            $existingItem = $cart->items()->where('product_id', $product->id)->first();

            if ($existingItem) {
                $newQuantity = $existingItem->quantity + $request->quantity;

                $priceResult = $this->pricingService->resolve($product, $buyerCompany, $newQuantity);
                $tierPrice = $priceResult->unitPrice;

                $offerResult = $this->cartOfferService->findBestOffer($product, $newQuantity, $currentCartItems, $tierPrice);

                if ($offerResult) {
                    $existingItem->quantity = $newQuantity;
                    $existingItem->unit_price = $offerResult['unit_price'];
                    $existingItem->total_price = $offerResult['total_price'];
                    $existingItem->applied_offer_id = $offerResult['offer']->id;
                } else {
                    $existingItem->quantity = $newQuantity;
                    $existingItem->unit_price = $tierPrice;
                    $existingItem->total_price = $newQuantity * $tierPrice;
                    $existingItem->applied_offer_id = null;
                }

                $existingItem->pricing_type = $priceResult->pricingType;
                $existingItem->notes = $request->notes ?? $existingItem->notes;
                $existingItem->save();
            } else {
                $priceResult = $this->pricingService->resolve($product, $buyerCompany, $request->quantity);
                $tierPrice = $priceResult->unitPrice;

                $offerResult = $this->cartOfferService->findBestOffer($product, $request->quantity, $currentCartItems, $tierPrice);

                if ($offerResult) {
                    $cartItem = CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $product->id,
                        'quantity' => $request->quantity,
                        'unit_price' => $offerResult['unit_price'],
                        'total_price' => $offerResult['total_price'],
                        'pricing_type' => $priceResult->pricingType,
                        'applied_offer_id' => $offerResult['offer']->id,
                        'notes' => $request->notes,
                    ]);
                } else {
                    $cartItem = CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $product->id,
                        'quantity' => $request->quantity,
                        'unit_price' => $tierPrice,
                        'total_price' => $request->quantity * $tierPrice,
                        'pricing_type' => $priceResult->pricingType,
                        'applied_offer_id' => null,
                        'notes' => $request->notes,
                    ]);
                }
            }

            // Recalculate cart totals
            $cart->calculateTotals();

            $cart->load([
                'items.product.supplierCompany',
                'items.product.category',
                'items.product.manufacturer',
                'items.product.offers',
                'items.appliedOffer',
                'user',
                'company',
            ]);

            return $this->success(
                new CartResource($cart),
                'Item added to cart successfully'
            );
        } catch (\Exception $e) {
            Log::error('Add cart item error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to add item to cart. Please try again later.', null, 500);
        }
    }

    /**
     * Update cart item.
     */
    #[OA\Put(
        path: '/cart/items/{id}',
        summary: 'Update cart item',
        description: 'Update quantity or notes. Offers are automatically re-evaluated when quantity changes. Best offer (lowest total) is selected. For bundle offers, eligibility depends on other cart items.',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', example: 3),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Updated notes'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cart item updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Cart item not found'),
        ]
    )]
    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            $cart = $cartItem->cart;
            if ($cart === null || $cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            if ($request->has('quantity')) {
                $newQuantity = $request->quantity;
                $product = $cartItem->product;
                $buyerCompany = $user->company;
                if ($product === null || $buyerCompany === null) {
                    return $this->error('This product is no longer available.', null, 422);
                }
                $product->load('offers');

                $cartItems = $cart->items()->with('product')->get();

                $priceResult = $this->pricingService->resolve($product, $buyerCompany, $newQuantity);
                $tierPrice = $priceResult->unitPrice;

                $offerResult = $this->cartOfferService->findBestOffer($product, $newQuantity, $cartItems, $tierPrice);

                $cartItem->quantity = $newQuantity;
                $cartItem->pricing_type = $priceResult->pricingType;

                if ($offerResult) {
                    $cartItem->unit_price = $offerResult['unit_price'];
                    $cartItem->total_price = $offerResult['total_price'];
                    $cartItem->applied_offer_id = $offerResult['offer']->id;
                } else {
                    $cartItem->unit_price = $tierPrice;
                    $cartItem->total_price = $newQuantity * $tierPrice;
                    $cartItem->applied_offer_id = null;
                }
            }

            if ($request->has('notes')) {
                $cartItem->notes = $request->notes;
            }

            $cartItem->save();

            // Recalculate cart totals
            $cart->calculateTotals();
            $cartItem->load(['product', 'appliedOffer']);

            return $this->success(
                \App\Http\Resources\CartItemResource::make($cartItem),
                'Cart item updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Update cart item error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to update cart item. Please try again later.', null, 500);
        }
    }

    /**
     * Get available offers for a cart item.
     */
    #[OA\Get(
        path: '/cart/items/{id}/offers',
        summary: 'Get available offers for cart item',
        description: 'Get all offers applicable to this cart item. Considers current quantity and other cart items (for bundle offers). Bundle offers require ALL products in the offer to be in cart with required quantities. Returns each offer with unitPrice, totalPrice, savings, savingsPercentage. Sorted by total price (best first).',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Available offers retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'cartItemId', type: 'integer', example: 1),
                                new OA\Property(property: 'productId', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 5),
                                new OA\Property(property: 'basePrice', type: 'number', format: 'float', example: 25.50),
                                new OA\Property(property: 'baseTotal', type: 'number', format: 'float', example: 127.50),
                                new OA\Property(property: 'currentOfferId', type: 'integer', nullable: true, example: 1),
                                new OA\Property(
                                    property: 'availableOffers',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'offer', type: 'object', description: 'Offer object (see Offer schema)'),
                                            new OA\Property(property: 'unitPrice', type: 'number', format: 'float', description: 'Unit price if this offer applied'),
                                            new OA\Property(property: 'totalPrice', type: 'number', format: 'float', description: 'Total price if this offer applied'),
                                            new OA\Property(property: 'savings', type: 'number', format: 'float', description: 'Amount saved vs base price'),
                                            new OA\Property(property: 'savingsPercentage', type: 'number', format: 'float', description: 'Savings as percentage'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Cart item not found'),
        ]
    )]
    public function getAvailableOffers(int $id): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            $cart = $cartItem->cart;
            if ($cart === null || $cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            $product = $cartItem->product;
            $buyerCompany = $user->company;
            if ($product === null || $buyerCompany === null) {
                return $this->error('This product is no longer available.', null, 422);
            }
            $product->load('offers');

            $cartItems = $cart->items()->with('product')->get();

            $priceResult = $this->pricingService->resolve($product, $buyerCompany, $cartItem->quantity);
            $basePrice = $priceResult->unitPrice;

            $availableOffers = $this->cartOfferService->getAvailableOffers($product, $cartItem->quantity, $cartItems, $basePrice);

            $baseTotal = $cartItem->quantity * $basePrice;

            // Format offers for response
            $formattedOffers = array_map(function ($offerData) {
                return [
                    'offer' => new \App\Http\Resources\OfferResource($offerData['offer']),
                    'unitPrice' => $offerData['unit_price'],
                    'totalPrice' => $offerData['total_price'],
                    'savings' => $offerData['savings'],
                    'savingsPercentage' => $offerData['savings_percentage'],
                ];
            }, $availableOffers);

            return $this->success([
                'cartItemId' => $cartItem->id,
                'productId' => $product->id,
                'quantity' => $cartItem->quantity,
                'basePrice' => (float) $basePrice,
                'baseTotal' => (float) $baseTotal,
                'currentOfferId' => $cartItem->applied_offer_id,
                'availableOffers' => $formattedOffers,
            ], 'Available offers retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Get available offers error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to retrieve available offers. Please try again later.', null, 500);
        }
    }

    /**
     * Apply a specific offer to a cart item.
     */
    #[OA\Post(
        path: '/cart/items/{id}/apply-offer',
        summary: 'Apply specific offer to cart item',
        description: 'Manually apply a specific offer. Overrides auto-selected best offer. Offer must be for this product and quantity requirement must be met. For bundle offers, all bundle products must be in cart. Returns cart item with baseUnitPrice, baseTotalPrice, unitPrice, totalPrice.',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['offer_id'],
                properties: [
                    new OA\Property(property: 'offer_id', type: 'integer', example: 1, description: 'ID of the offer to apply'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Offer applied successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Cart item or offer not found'),
            new OA\Response(response: 400, description: 'Offer cannot be applied (quantity requirement not met, etc.)'),
        ]
    )]
    public function applyOffer(ApplyOfferRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            $cart = $cartItem->cart;
            if ($cart === null || $cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            $product = $cartItem->product;
            $buyerCompany = $user->company;
            if ($product === null || $buyerCompany === null) {
                return $this->error('This product is no longer available.', null, 422);
            }
            $offer = \App\Models\Offer::findOrFail((int) $request->offer_id);

            $productOffers = $product->offers()->where('offers.id', $offer->id)->first();
            if (! $productOffers) {
                return $this->error('This offer is not available for this product', null, 400);
            }

            $cartItems = $cart->items()->with('product')->get();

            $priceResult = $this->pricingService->resolve($product, $buyerCompany, $cartItem->quantity);
            $offerResult = $this->cartOfferService->applyOffer($offer, $product, $cartItem->quantity, $cartItems, $priceResult->unitPrice);

            if (! $offerResult) {
                return $this->error('Offer cannot be applied. Quantity requirement may not be met or offer is not valid for this cart.', null, 400);
            }

            $cartItem->unit_price = $offerResult['unit_price'];
            $cartItem->total_price = $offerResult['total_price'];
            $cartItem->appliedOffer()->associate($offer);
            $cartItem->save();

            // Recalculate cart totals
            $cart->calculateTotals();
            $cartItem->load(['product.offers', 'appliedOffer']);

            return $this->success(
                \App\Http\Resources\CartItemResource::make($cartItem),
                'Offer applied successfully'
            );
        } catch (\Exception $e) {
            Log::error('Apply offer error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to apply offer. Please try again later.', null, 500);
        }
    }

    /**
     * Remove applied offer from cart item (revert to best offer or base price).
     */
    #[OA\Post(
        path: '/cart/items/{id}/remove-offer',
        summary: 'Remove applied offer from cart item',
        description: 'Remove manual offer and revert to auto-selected best offer (or base price if none). Recalculates best offer for this product based on current quantity and cart state.',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Offer removed successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Cart item not found'),
        ]
    )]
    public function removeOffer(int $id): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            $cart = $cartItem->cart;
            if ($cart === null || $cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            $product = $cartItem->product;
            $buyerCompany = $user->company;
            if ($product === null || $buyerCompany === null) {
                return $this->error('This product is no longer available.', null, 422);
            }
            $product->load('offers');

            $cartItems = $cart->items()->with('product')->get();

            $priceResult = $this->pricingService->resolve($product, $buyerCompany, $cartItem->quantity);
            $tierPrice = $priceResult->unitPrice;

            $offerResult = $this->cartOfferService->findBestOffer($product, $cartItem->quantity, $cartItems, $tierPrice);

            if ($offerResult) {
                $cartItem->unit_price = $offerResult['unit_price'];
                $cartItem->total_price = $offerResult['total_price'];
                $cartItem->applied_offer_id = $offerResult['offer']->id;
            } else {
                $cartItem->unit_price = $tierPrice;
                $cartItem->total_price = $cartItem->quantity * $tierPrice;
                $cartItem->applied_offer_id = null;
            }
            $cartItem->pricing_type = $priceResult->pricingType;

            $cartItem->save();

            // Recalculate cart totals
            $cart->calculateTotals();
            $cartItem->load(['product.offers', 'appliedOffer']);

            return $this->success(
                \App\Http\Resources\CartItemResource::make($cartItem),
                'Offer removed successfully. Reverted to best available offer.'
            );
        } catch (\Exception $e) {
            Log::error('Remove offer error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to remove offer. Please try again later.', null, 500);
        }
    }

    /**
     * Remove item from cart.
     */
    #[OA\Delete(
        path: '/cart/items/{id}',
        summary: 'Remove cart item',
        description: 'Remove item from cart. **Important**: Re-evaluates offers for ALL remaining items (bundle eligibility may change when a product is removed).',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Item removed from cart successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Cart item not found'),
        ]
    )]
    public function removeItem(int $id): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            $cart = $cartItem->cart;
            if ($cart === null || $cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            $buyerCompany = $user->company;
            if ($buyerCompany === null) {
                return $this->error('Your account is not linked to a company.', null, 422);
            }
            $cartItem->delete();

            $remainingItems = $cart->items()->with('product.offers')->get();
            foreach ($remainingItems as $item) {
                $itemProduct = $item->product;
                if ($itemProduct === null) {
                    continue;
                }
                $itemProduct->load('offers');

                $priceResult = $this->pricingService->resolve($itemProduct, $buyerCompany, $item->quantity);
                $tierPrice = $priceResult->unitPrice;

                $offerResult = $this->cartOfferService->findBestOffer($itemProduct, $item->quantity, $remainingItems, $tierPrice);

                if ($offerResult) {
                    $item->unit_price = $offerResult['unit_price'];
                    $item->total_price = $offerResult['total_price'];
                    $item->applied_offer_id = $offerResult['offer']->id;
                } else {
                    $item->unit_price = $tierPrice;
                    $item->total_price = $item->quantity * $tierPrice;
                    $item->applied_offer_id = null;
                }
                $item->pricing_type = $priceResult->pricingType;
                $item->save();
            }

            // Recalculate cart totals
            $cart->calculateTotals();

            return $this->success(null, 'Item removed from cart successfully');
        } catch (\Exception $e) {
            Log::error('Remove cart item error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to remove item from cart. Please try again later.', null, 500);
        }
    }

    /**
     * Clear entire cart.
     */
    #[OA\Delete(
        path: '/cart',
        summary: 'Clear cart',
        description: 'Remove all items from the shopping cart',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Cart cleared successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function clear(Request $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $cart = Cart::where('user_id', $user->id)->first();

            if ($cart) {
                $cart->items()->delete();
                $cart->update([
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                ]);
            }

            return $this->success(null, 'Cart cleared successfully');
        } catch (\Exception $e) {
            Log::error('Clear cart error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to clear cart. Please try again later.', null, 500);
        }
    }

    /**
     * Checkout cart (convert to orders).
     */
    #[OA\Post(
        path: '/cart/checkout',
        summary: 'Checkout cart',
        description: 'Convert cart to orders. Uses cart item unitPrice/totalPrice (offer-adjusted) for order creation. Multi-supplier cart splits into multiple orders (one per supplier). Atomic transaction. Cart cleared after success.',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Order notes'),
                    new OA\Property(property: 'tax_rate', type: 'number', format: 'float', default: 0, example: 15, description: 'Tax rate as percentage (e.g., 15 for 15%)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Orders created successfully. Returns array of orders if multiple suppliers, single order if one supplier.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Cart checked out. Multiple orders created for different suppliers.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'orders',
                                    type: 'array',
                                    items: new OA\Items(type: 'object', description: 'Order object with buyerCompany, supplierCompany, and items.product relationships')
                                ),
                                new OA\Property(property: 'orders_count', type: 'integer', example: 2, description: 'Number of orders created'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only buyers can checkout'),
            new OA\Response(response: 422, description: 'Cart is empty'),
        ]
    )]
    public function checkout(Request $request): JsonResponse
    {
        $user = $this->currentUser();

        if (! $user->canBuy()) {
            return $this->error('Only buyers and salesmen can checkout', null, 403);
        }

        $orders = $this->checkoutService->checkoutCart($user, $request->notes, (float) ($request->tax_rate ?? 0));

        return OrderResource::collection($orders)
            ->additional([
                'success' => true,
                'message' => count($orders) > 1
                    ? 'Cart checked out. Multiple orders created for different suppliers.'
                    : 'Order created successfully',
                'orders_count' => count($orders),
            ])
            ->response();
    }

    /**
     * Checkout cart items for a specific supplier.
     */
    #[OA\Post(
        path: '/cart/checkout-supplier',
        summary: 'Checkout cart items for specific supplier',
        description: 'Convert cart items from a specific supplier to an order. Only items from the specified supplier will be checked out. Other items remain in the cart. Cart totals are recalculated after checkout.',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['supplier_id'],
                properties: [
                    new OA\Property(property: 'supplier_id', type: 'integer', example: 1, description: 'ID of the supplier company'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Order notes'),
                    new OA\Property(property: 'tax_rate', type: 'number', format: 'float', default: 0, example: 15, description: 'Tax rate as percentage (e.g., 15 for 15%)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order created successfully for the specified supplier.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Order created successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'order', type: 'object', description: 'Order object with buyerCompany, supplierCompany, and items.product relationships'),
                                new OA\Property(property: 'items_checked_out', type: 'integer', example: 3, description: 'Number of cart items checked out'),
                                new OA\Property(property: 'items_remaining', type: 'integer', example: 2, description: 'Number of items remaining in cart'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only buyers can checkout'),
            new OA\Response(response: 422, description: 'Cart is empty or no items found for supplier'),
            new OA\Response(response: 404, description: 'Supplier not found'),
        ]
    )]
    public function checkoutSupplier(CheckoutSupplierRequest $request): JsonResponse
    {
        $user = $this->currentUser();

        if (! $user->canBuy()) {
            return $this->error('Only buyers and salesmen can checkout', null, 403);
        }

        $result = $this->checkoutService->checkoutSupplier(
            $user,
            (int) $request->supplier_id,
            $request->notes,
            (float) ($request->tax_rate ?? 0),
        );

        return (new OrderResource($result['order']))
            ->additional([
                'success' => true,
                'message' => 'Order created successfully',
                'items_checked_out' => $result['items_checked_out'],
                'items_remaining' => $result['items_remaining'],
            ])
            ->response();
    }
}
