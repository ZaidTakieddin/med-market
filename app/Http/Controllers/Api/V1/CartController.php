<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Orders\OrderCreationService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Cart', description: 'Shopping cart management endpoints')]
class CartController extends BaseApiController
{
    public function __construct(
        private OrderCreationService $orderCreationService
    ) {}

    /**
     * Get current user's cart.
     */
    #[OA\Get(
        path: '/cart',
        summary: 'Get cart',
        description: 'Get the current user\'s shopping cart with all items',
        tags: ['Cart'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Cart retrieved successfully'),
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

            $cart->load(['items.product.supplierCompany']);

            // Prevent circular references
            $cart->items->each(function ($item) {
                if ($item->product && $item->product->supplierCompany) {
                    $item->product->supplierCompany->makeHidden(['users', 'products', 'buyerOrders', 'supplierOrders']);
                }
                $item->product->makeHidden(['orderItems', 'stockMovements']);
            });

            return $this->success([
                'cart' => $cart,
            ], 'Cart retrieved successfully');
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
        description: 'Add a product to the shopping cart. The unit_price is automatically calculated using the product\'s pharmacist_price with any applicable discounts applied.',
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
            new OA\Response(response: 200, description: 'Item added to cart successfully. Price uses product pharmacist_price with discounts applied.'),
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

            // Check if item already exists in cart
            $existingItem = $cart->items()->where('product_id', $product->id)->first();

            if ($existingItem) {
                // Update quantity
                $existingItem->quantity += $request->quantity;
                $existingItem->unit_price = $product->getFinalPharmacistPrice();
                $existingItem->calculateTotal();
                $existingItem->notes = $request->notes ?? $existingItem->notes;
                $existingItem->save();
            } else {
                // Create new cart item
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'unit_price' => $product->getFinalPharmacistPrice(),
                    'total_price' => $request->quantity * $product->getFinalPharmacistPrice(),
                    'notes' => $request->notes,
                ]);
            }

            // Recalculate cart totals
            $cart->calculateTotals();

            $cart->load(['items.product.supplierCompany']);

            return $this->success([
                'cart' => $cart,
            ], 'Item added to cart successfully');
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
        description: 'Update quantity or notes of a cart item',
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
                    new OA\Property(property: 'notes', type: 'string', example: 'Updated notes'),
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
                $cartItem->quantity = $request->quantity;
                $cartItem->calculateTotal();
            }

            if ($request->has('notes')) {
                $cartItem->notes = $request->notes;
            }

            $cartItem->save();

            // Recalculate cart totals
            $cartItem->cart->calculateTotals();

            return $this->success([
                'cart_item' => $cartItem->load('product'),
            ], 'Cart item updated successfully');
        } catch (\Exception $e) {
            Log::error('Update cart item error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update cart item: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove item from cart.
     */
    #[OA\Delete(
        path: '/cart/items/{id}',
        summary: 'Remove cart item',
        description: 'Remove an item from the shopping cart',
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
            $cartItem->delete();

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
        description: 'Convert cart items to orders. If cart contains items from multiple suppliers, multiple orders will be created automatically (one per supplier). All orders are created in a single atomic transaction - either all succeed or all fail. Cart is cleared after successful checkout.',
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

                    $createdOrders[] = $order->load(['buyerCompany', 'supplierCompany', 'items.product']);
                }

                // Clear cart after successful checkout
                $cart->items()->delete();
                $cart->update([
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                ]);

                DB::commit();

                return $this->success([
                    'orders' => $createdOrders,
                    'orders_count' => count($createdOrders),
                ], count($createdOrders) > 1 
                    ? 'Cart checked out. Multiple orders created for different suppliers.' 
                    : 'Order created successfully');
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
}

