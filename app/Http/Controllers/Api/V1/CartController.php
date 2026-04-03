<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cart\Services\CartOfferService;
use App\Domain\Orders\OrderCreationService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Cart',
    description: 'Shopping cart management. **Offer system**: Each cart item can have one offer applied. The system auto-selects the best offer (lowest total price) per product. Offer types: buy_x_get_y, percentage_discount, fixed_discount, bundle_percentage, bundle_fixed. Bundle offers require ALL products in the offer to be in cart with required quantities. Cart items include baseUnitPrice/baseTotalPrice (before offer) for displaying price changes.'
)]
class CartController extends BaseApiController
{
    public function __construct(
        private OrderCreationService $orderCreationService,
        private CartOfferService $cartOfferService
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
            $user = auth()->user();
            
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
                'company'
            ]);

            return $this->success(
                new CartResource($cart),
                'Cart retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Cart retrieval error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve cart: ' . $e->getMessage(), null, 500);
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
            $user = auth()->user();
            
            if (!$user->isBuyer()) {
                return $this->error('Only buyers can add items to cart', null, 403);
            }

            $product = Product::findOrFail($request->product_id);

            if (!$product->is_active) {
                return $this->error('Product is not available', null, 400);
            }

            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['company_id' => $user->company_id]
            );

            // Load product offers for offer checking
            $product->load('offers');
            
            // Get current cart items for bundle offer checking
            $currentCartItems = $cart->items()->with('product')->get();
            
            // Check if item already exists in cart
            $existingItem = $cart->items()->where('product_id', $product->id)->first();

            if ($existingItem) {
                // Update quantity
                $newQuantity = $existingItem->quantity + $request->quantity;
                
                // Check for offers with new quantity
                $offerResult = $this->cartOfferService->findBestOffer($product, $newQuantity, $currentCartItems);
                
                if ($offerResult) {
                    $existingItem->quantity = $newQuantity;
                    $existingItem->unit_price = $offerResult['unit_price'];
                    $existingItem->total_price = $offerResult['total_price'];
                    $existingItem->applied_offer_id = $offerResult['offer']->id;
                } else {
                    $existingItem->quantity = $newQuantity;
                    $existingItem->unit_price = $product->getFinalPharmacistPrice();
                    $existingItem->calculateTotal();
                    $existingItem->applied_offer_id = null;
                }
                
                $existingItem->notes = $request->notes ?? $existingItem->notes;
                $existingItem->save();
            } else {
                // Check for offers
                $offerResult = $this->cartOfferService->findBestOffer($product, $request->quantity, $currentCartItems);
                
                if ($offerResult) {
                    // Create new cart item with offer applied
                    $cartItem = CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $product->id,
                        'quantity' => $request->quantity,
                        'unit_price' => $offerResult['unit_price'],
                        'total_price' => $offerResult['total_price'],
                        'applied_offer_id' => $offerResult['offer']->id,
                        'notes' => $request->notes,
                    ]);
                } else {
                    // Create new cart item without offer
                    $cartItem = CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $product->id,
                        'quantity' => $request->quantity,
                        'unit_price' => $product->getFinalPharmacistPrice(),
                        'total_price' => $request->quantity * $product->getFinalPharmacistPrice(),
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
                'company'
            ]);

            return $this->success(
                new CartResource($cart),
                'Item added to cart successfully'
            );
        } catch (\Exception $e) {
            Log::error('Add cart item error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to add item to cart: ' . $e->getMessage(), null, 500);
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
            $user = auth()->user();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            if ($cartItem->cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            if ($request->has('quantity')) {
                $newQuantity = $request->quantity;
                $product = $cartItem->product;
                $product->load('offers');
                
                // Get all cart items for bundle offer checking
                $cartItems = $cartItem->cart->items()->with('product')->get();
                
                // Check for offers with new quantity
                $offerResult = $this->cartOfferService->findBestOffer($product, $newQuantity, $cartItems);
                
                $cartItem->quantity = $newQuantity;
                
                if ($offerResult) {
                    $cartItem->unit_price = $offerResult['unit_price'];
                    $cartItem->total_price = $offerResult['total_price'];
                    $cartItem->applied_offer_id = $offerResult['offer']->id;
                } else {
                    $cartItem->unit_price = $product->getFinalPharmacistPrice();
                    $cartItem->calculateTotal();
                    $cartItem->applied_offer_id = null;
                }
            }

            if ($request->has('notes')) {
                $cartItem->notes = $request->notes;
            }

            $cartItem->save();

            // Recalculate cart totals
            $cartItem->cart->calculateTotals();
            $cartItem->load(['product', 'appliedOffer']);

            return $this->success(
                \App\Http\Resources\CartItemResource::make($cartItem),
                'Cart item updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Update cart item error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update cart item: ' . $e->getMessage(), null, 500);
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
            $user = auth()->user();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            if ($cartItem->cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            $product = $cartItem->product;
            $product->load('offers');
            
            // Get all cart items for bundle offer checking
            $cartItems = $cartItem->cart->items()->with('product')->get();
            
            // Get all available offers
            $availableOffers = $this->cartOfferService->getAvailableOffers($product, $cartItem->quantity, $cartItems);
            
            $basePrice = $product->getFinalPharmacistPrice();
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
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve available offers: ' . $e->getMessage(), null, 500);
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
    public function applyOffer(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            if ($cartItem->cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            $request->validate([
                'offer_id' => ['required', 'integer', 'exists:offers,id'],
            ]);

            $product = $cartItem->product;
            $offer = \App\Models\Offer::findOrFail($request->offer_id);
            
            // Verify offer is for this product
            $productOffers = $product->offers()->where('offers.id', $offer->id)->first();
            if (!$productOffers) {
                return $this->error('This offer is not available for this product', null, 400);
            }

            // Get all cart items for bundle offer checking
            $cartItems = $cartItem->cart->items()->with('product')->get();
            
            // Apply the specific offer
            $offerResult = $this->cartOfferService->applyOffer($offer, $product, $cartItem->quantity, $cartItems);
            
            if (!$offerResult) {
                return $this->error('Offer cannot be applied. Quantity requirement may not be met or offer is not valid for this cart.', null, 400);
            }

            $cartItem->unit_price = $offerResult['unit_price'];
            $cartItem->total_price = $offerResult['total_price'];
            $cartItem->applied_offer_id = $offer->id;
            $cartItem->save();

            // Recalculate cart totals
            $cartItem->cart->calculateTotals();
            $cartItem->load(['product.offers', 'appliedOffer']);

            return $this->success(
                \App\Http\Resources\CartItemResource::make($cartItem),
                'Offer applied successfully'
            );
        } catch (\Exception $e) {
            Log::error('Apply offer error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to apply offer: ' . $e->getMessage(), null, 500);
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
            $user = auth()->user();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            if ($cartItem->cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            $product = $cartItem->product;
            $product->load('offers');
            
            // Get all cart items for bundle offer checking
            $cartItems = $cartItem->cart->items()->with('product')->get();
            
            // Find best offer automatically
            $offerResult = $this->cartOfferService->findBestOffer($product, $cartItem->quantity, $cartItems);
            
            if ($offerResult) {
                $cartItem->unit_price = $offerResult['unit_price'];
                $cartItem->total_price = $offerResult['total_price'];
                $cartItem->applied_offer_id = $offerResult['offer']->id;
            } else {
                // No offers available, revert to base price
                $cartItem->unit_price = $product->getFinalPharmacistPrice();
                $cartItem->calculateTotal();
                $cartItem->applied_offer_id = null;
            }
            
            $cartItem->save();

            // Recalculate cart totals
            $cartItem->cart->calculateTotals();
            $cartItem->load(['product.offers', 'appliedOffer']);

            return $this->success(
                \App\Http\Resources\CartItemResource::make($cartItem),
                'Offer removed successfully. Reverted to best available offer.'
            );
        } catch (\Exception $e) {
            Log::error('Remove offer error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to remove offer: ' . $e->getMessage(), null, 500);
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
            $user = auth()->user();
            $cartItem = CartItem::findOrFail($id);

            // Verify cart belongs to user
            if ($cartItem->cart->user_id !== $user->id) {
                return $this->error('Unauthorized', null, 403);
            }

            $cart = $cartItem->cart;
            $product = $cartItem->product;
            $cartItem->delete();

            // Re-evaluate offers for remaining items (important for bundle offers)
            $remainingItems = $cart->items()->with('product.offers')->get();
            foreach ($remainingItems as $item) {
                $itemProduct = $item->product;
                $itemProduct->load('offers');
                
                // Check for offers with current quantity
                $offerResult = $this->cartOfferService->findBestOffer($itemProduct, $item->quantity, $remainingItems);
                
                if ($offerResult) {
                    $item->unit_price = $offerResult['unit_price'];
                    $item->total_price = $offerResult['total_price'];
                    $item->applied_offer_id = $offerResult['offer']->id;
                } else {
                    $item->unit_price = $itemProduct->getFinalPharmacistPrice();
                    $item->calculateTotal();
                    $item->applied_offer_id = null;
                }
                $item->save();
            }

            // Recalculate cart totals
            $cart->calculateTotals();

            return $this->success(null, 'Item removed from cart successfully');
        } catch (\Exception $e) {
            Log::error('Remove cart item error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to remove item from cart: ' . $e->getMessage(), null, 500);
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
            $user = auth()->user();
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
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to clear cart: ' . $e->getMessage(), null, 500);
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
                                new OA\Property(property: 'orders_count', type: 'integer', example: 2, description: 'Number of orders created')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only buyers can checkout'),
            new OA\Response(response: 400, description: 'Cart is empty'),
        ]
    )]
    public function checkout(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user->isBuyer()) {
                return $this->error('Only buyers can checkout', null, 403);
            }

            $cart = Cart::where('user_id', $user->id)->with('items.product')->first();

            if (!$cart || $cart->items->isEmpty()) {
                return $this->error('Cart is empty', null, 400);
            }

            // Group items by supplier
            $itemsBySupplier = $cart->itemsBySupplier();
            $createdOrders = [];

            DB::beginTransaction();
            try {
                foreach ($itemsBySupplier as $supplierId => $items) {
                    $orderItems = [];
                    foreach ($items as $cartItem) {
                        $orderItems[] = [
                            'product_id' => $cartItem->product_id,
                            'quantity' => $cartItem->quantity,
                            'unit_price' => $cartItem->unit_price,
                            'notes' => $cartItem->notes,
                            'applied_offer_id' => $cartItem->applied_offer_id,
                        ];
                    }

                    $order = $this->orderCreationService->create(
                        buyerCompanyId: $user->company_id,
                        supplierCompanyId: $supplierId,
                        createdBy: $user->id,
                        items: $orderItems,
                        notes: $request->notes ?? null,
                        taxRate: $request->tax_rate ?? 0
                    );

                    $createdOrders[] = $order->load([
                        'buyerCompany.region.city',
                        'supplierCompany.categories',
                        'supplierCompany.manufacturers',
                        'driver',
                        'invoice',
                        'items.product.category',
                        'items.product.manufacturer',
                        'items.product.supplierCompany',
                        'items.appliedOffer',
                    ]);
                }

                // Clear cart after successful checkout
                $cart->items()->delete();
                $cart->update([
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                ]);

                DB::commit();

                return OrderResource::collection($createdOrders)
                    ->additional([
                        'success' => true,
                        'message' => count($createdOrders) > 1 
                            ? 'Cart checked out. Multiple orders created for different suppliers.' 
                            : 'Order created successfully',
                        'orders_count' => count($createdOrders),
                    ])
                    ->response();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Cart checkout error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to checkout cart: ' . $e->getMessage(), null, 500);
        }
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
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only buyers can checkout'),
            new OA\Response(response: 400, description: 'Cart is empty or no items found for supplier'),
            new OA\Response(response: 404, description: 'Supplier not found'),
        ]
    )]
    public function checkoutSupplier(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user->isBuyer()) {
                return $this->error('Only buyers can checkout', null, 403);
            }

            $request->validate([
                'supplier_id' => ['required', 'integer', 'exists:companies,id'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ]);

            // Verify supplier is actually a supplier company
            $supplier = \App\Models\Company::findOrFail($request->supplier_id);
            if (!$supplier->isSupplier()) {
                return $this->error('The specified company is not a supplier', null, 400);
            }

            $cart = Cart::where('user_id', $user->id)->with('items.product')->first();

            if (!$cart || $cart->items->isEmpty()) {
                return $this->error('Cart is empty', null, 400);
            }

            // Filter items for the specified supplier
            $supplierItems = $cart->items->filter(function ($item) use ($request) {
                return $item->product->supplier_company_id == $request->supplier_id;
            });

            if ($supplierItems->isEmpty()) {
                return $this->error('No items found for the specified supplier in your cart', null, 400);
            }

            DB::beginTransaction();
            try {
                // Prepare order items
                $orderItems = [];
                foreach ($supplierItems as $cartItem) {
                    $orderItems[] = [
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $cartItem->unit_price,
                        'notes' => $cartItem->notes,
                        'applied_offer_id' => $cartItem->applied_offer_id,
                    ];
                }

                // Create order
                $order = $this->orderCreationService->create(
                    buyerCompanyId: $user->company_id,
                    supplierCompanyId: $request->supplier_id,
                    createdBy: $user->id,
                    items: $orderItems,
                    notes: $request->notes ?? null,
                    taxRate: $request->tax_rate ?? 0
                );

                $order->load([
                    'buyerCompany.region.city',
                    'supplierCompany.categories',
                    'supplierCompany.manufacturers',
                    'driver',
                    'invoice',
                    'items.product.category',
                    'items.product.manufacturer',
                    'items.product.supplierCompany',
                    'items.appliedOffer',
                ]);

                // Remove only the checked out items from cart
                $cart->items()->whereIn('id', $supplierItems->pluck('id'))->delete();

                // Refresh cart to reload items relationship
                $cart->refresh();
                $cart->load('items');

                // Recalculate cart totals
                $cart->calculateTotals();

                DB::commit();

                $remainingItemsCount = $cart->items()->count();

                return (new OrderResource($order))
                    ->additional([
                        'success' => true,
                        'message' => 'Order created successfully',
                        'items_checked_out' => $supplierItems->count(),
                        'items_remaining' => $remainingItemsCount,
                    ])
                    ->response();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Cart supplier checkout error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to checkout supplier items: ' . $e->getMessage(), null, 500);
        }
    }
}

