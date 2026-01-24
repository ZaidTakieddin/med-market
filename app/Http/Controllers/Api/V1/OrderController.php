<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\OrderStatus;
use App\Domain\Orders\OrderCreationService;
use App\Domain\Orders\OrderStateTransitionService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Orders\CreateOrderRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Orders', description: 'Order management endpoints')]
class OrderController extends BaseApiController
{
    public function __construct(
        private OrderCreationService $orderCreationService,
        private OrderStateTransitionService $orderStateTransitionService
    ) {}

    /**
     * List orders.
     */
    #[OA\Get(
        path: '/orders',
        summary: 'List orders',
        description: 'Get a paginated list of orders filtered by user\'s company. **Multi-supplier context:** Buyers see all their orders (potentially from multiple suppliers - one order per supplier). Suppliers see all orders placed with them (from multiple buyers). Each order represents a single supplier-buyer transaction. When a cart contains items from multiple suppliers and is checked out, multiple orders are created automatically (one per supplier).',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['pending', 'accepted', 'prepared', 'shipped', 'delivered', 'cancelled']
                ),
                description: 'Filter orders by status'
            ),
            new OA\Parameter(name: 'supplier_company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by supplier company ID (for buyers)'),
            new OA\Parameter(name: 'buyer_company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by buyer company ID (for suppliers)'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Orders retrieved successfully. Each order includes buyerCompany, supplierCompany, items, and items.product relationships. Orders are grouped by supplier - if a buyer purchased from 3 suppliers, they will see 3 separate orders.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        description: 'Order object with buyerCompany, supplierCompany, items.product relationships'
                                    )
                                ),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'total', type: 'integer', example: 15),
                            ]
                        )
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
            $query = Order::with([
                'buyerCompany',
                'supplierCompany',
                'items',
                'items.product'
            ]);

            // Filter by company
            if ($user->isBuyer()) {
                $query->where('buyer_company_id', $user->company_id);
            } elseif ($user->isSupplier()) {
                $query->where('supplier_company_id', $user->company_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by supplier (for buyers)
            if ($request->has('supplier_company_id')) {
                $query->where('supplier_company_id', $request->supplier_company_id);
            }

            // Filter by buyer (for suppliers)
            if ($request->has('buyer_company_id')) {
                $query->where('buyer_company_id', $request->buyer_company_id);
            }

            $orders = $query->latest()->paginate($request->get('per_page', 15));

            // Prevent circular references by hiding unnecessary relationships
            $orders->getCollection()->each(function ($order) {
                if ($order->relationLoaded('buyerCompany') && $order->buyerCompany) {
                    $order->buyerCompany->makeHidden(['users', 'products', 'buyerOrders', 'supplierOrders']);
                }
                if ($order->relationLoaded('supplierCompany') && $order->supplierCompany) {
                    $order->supplierCompany->makeHidden(['users', 'products', 'buyerOrders', 'supplierOrders']);
                }
                if ($order->relationLoaded('items')) {
                    $order->items->each(function ($item) {
                        if ($item->relationLoaded('product') && $item->product) {
                            $item->product->makeHidden(['orderItems', 'stockMovements', 'supplierCompany']);
                        }
                    });
                }
            });

            return $this->paginated($orders, 'Orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Order list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve orders: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create a new order.
     */
    #[OA\Post(
        path: '/orders',
        summary: 'Create order',
        description: 'Create a new order for a **single supplier**. Only buyers can create orders. If unit_price is not provided, it will use the product\'s pharmacist_price with any applicable discounts applied (getFinalPharmacistPrice method). **Important:** This endpoint creates ONE order for ONE supplier. If you need to order from multiple suppliers, use the cart checkout endpoint (`POST /cart/checkout`) which automatically splits the cart into multiple orders (one per supplier).',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['supplier_company_id', 'items'],
                properties: [
                    new OA\Property(property: 'supplier_company_id', type: 'integer', example: 1, description: 'Supplier company ID - all items must belong to this supplier'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        description: 'Array of order items. All products must belong to the specified supplier_company_id',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 1, description: 'Product ID (must belong to supplier_company_id)'),
                                new OA\Property(property: 'quantity', type: 'integer', example: 10, description: 'Quantity ordered'),
                                new OA\Property(property: 'unit_price', type: 'number', format: 'float', nullable: true, description: 'Optional - will use product pharmacist_price with discounts applied (getFinalPharmacistPrice) if not provided', example: 25.50),
                                new OA\Property(property: 'notes', type: 'string', nullable: true, description: 'Item-specific notes'),
                            ]
                        )
                    ),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, description: 'Order-level notes'),
                    new OA\Property(property: 'tax_rate', type: 'number', format: 'float', default: 0, example: 15, description: 'Tax rate as percentage (e.g., 15 for 15%)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Order created successfully. Returns a single order object. For multi-supplier orders, use cart checkout which returns an array of orders.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'order',
                                    type: 'object',
                                    description: 'Single order object with buyerCompany, supplierCompany, and items.product relationships'
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only buyers can create orders'),
            new OA\Response(response: 422, description: 'Validation error - products must belong to the specified supplier'),
        ]
    )]
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isBuyer()) {
            return $this->error('Only buyers can create orders', null, 403);
        }

        // Prepare items with pharmacist_price from products
        $items = [];
        foreach ($request->items as $item) {
            $product = \App\Models\Product::findOrFail($item['product_id']);
            
            // Use provided unit_price or calculate from product's pharmacist_price
            $unitPrice = $item['unit_price'] ?? $product->getFinalPharmacistPrice();
            
            $items[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'notes' => $item['notes'] ?? null,
            ];
        }

        $order = $this->orderCreationService->create(
            buyerCompanyId: $user->company_id,
            supplierCompanyId: $request->supplier_company_id,
            createdBy: $user->id,
            items: $items,
            notes: $request->notes,
            taxRate: $request->tax_rate ?? 0
        );

        return $this->success([
            'order' => $order->load(['buyerCompany', 'supplierCompany', 'items.product']),
        ], 'Order created successfully', 201);
    }

    /**
     * Get a specific order.
     */
    #[OA\Get(
        path: '/orders/{id}',
        summary: 'Get order',
        description: 'Get details of a specific order. **Multi-supplier context:** Each order represents a transaction between ONE buyer and ONE supplier. If a buyer checked out a cart with items from multiple suppliers, they will have multiple separate orders (one per supplier). This endpoint returns details for a single order, including buyerCompany, supplierCompany, creator, items.product, statusLogs.user, and driver (if assigned).',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Order ID'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order retrieved successfully. Includes all relationships and driver information if assigned. The order represents a single supplier-buyer transaction.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'order',
                                    type: 'object',
                                    description: 'Order object with buyerCompany (buyer info), supplierCompany (supplier info), creator, items.product, statusLogs.user, and driver relationships'
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - User can only view orders from their own company'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['buyerCompany', 'supplierCompany', 'creator', 'items.product', 'statusLogs.user']);

        return $this->success([
            'order' => $order,
        ]);
    }

    /**
     * Update order status.
     */
    #[OA\Post(
        path: '/orders/{id}/status',
        summary: 'Update order status',
        description: 'Transition order to a new status. Valid transitions are enforced. **Multi-supplier context:** Each order is managed independently. If a buyer has multiple orders from different suppliers, each order\'s status must be updated separately. Suppliers can update status for orders placed with them, buyers can view status updates.',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Order ID'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        enum: ['pending', 'accepted', 'prepared', 'shipped', 'delivered', 'cancelled'],
                        example: 'accepted',
                        description: 'New order status. Must be a valid transition from current status.'
                    ),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, description: 'Optional notes about the status change'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order status updated successfully. Returns updated order with buyerCompany, supplierCompany, and items.product relationships.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'order',
                                    type: 'object',
                                    description: 'Updated order object'
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid status transition - cannot transition from current status to requested status'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Suppliers can only update their own orders'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        try {
            $order = $this->orderStateTransitionService->transition(
                order: $order,
                newStatus: $request->status,
                userId: auth()->id(),
                notes: $request->notes
            );

            return $this->success([
                'order' => $order->load(['buyerCompany', 'supplierCompany', 'items.product']),
            ], 'Order status updated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }

    /**
     * Cancel an order.
     */
    #[OA\Post(
        path: '/orders/{id}/cancel',
        summary: 'Cancel order',
        description: 'Cancel an order. Requires cancellation reason. **Multi-supplier context:** Each order can be cancelled independently. If a buyer has multiple orders from different suppliers and wants to cancel all, each order must be cancelled separately. Stock is automatically reverted when an order is cancelled.',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Order ID'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Out of stock', description: 'Reason for cancellation (max 500 characters)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order cancelled successfully. Stock has been automatically reverted. Returns updated order with cancellation_reason.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'order',
                                    type: 'object',
                                    description: 'Cancelled order object with status set to cancelled and cancellation_reason populated'
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Cannot cancel order in current status - order may already be delivered or cancelled'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Buyers can cancel their own orders, suppliers can cancel orders placed with them'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $order->cancellation_reason = $request->reason;
            $order->save();

            $order = $this->orderStateTransitionService->transition(
                order: $order,
                newStatus: OrderStatus::CANCELLED,
                userId: auth()->id(),
                notes: $request->reason
            );

            return $this->success([
                'order' => $order->load(['buyerCompany', 'supplierCompany', 'items.product']),
            ], 'Order cancelled successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            Log::error('Cancel order error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to cancel order: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Assign driver to order.
     */
    #[OA\Post(
        path: '/orders/{id}/assign-driver',
        summary: 'Assign driver to order',
        description: 'Assign a driver to an order. Only suppliers can assign drivers to their own orders. The driver must belong to the supplier\'s company.',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Order ID'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['driver_id'],
                properties: [
                    new OA\Property(property: 'driver_id', type: 'integer', example: 5, description: 'Driver user ID'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Driver assigned successfully. Order includes driver relationship.'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only suppliers can assign drivers to their own orders'),
            new OA\Response(response: 404, description: 'Order or driver not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function assignDriver(Request $request, Order $order): JsonResponse
    {
        try {
            $user = auth()->user();

            // Only suppliers can assign drivers to their orders
            if (!$user->isSupplier() && !$user->isAdmin()) {
                return $this->error('Only suppliers can assign drivers', null, 403);
            }

            // Verify order belongs to supplier's company
            if ($order->supplier_company_id !== $user->company_id && !$user->isAdmin()) {
                return $this->error('You can only assign drivers to your own orders', null, 403);
            }

            $request->validate([
                'driver_id' => ['required', 'integer', 'exists:users,id'],
            ]);

            $driver = \App\Models\User::findOrFail($request->driver_id);

            // Verify driver belongs to supplier's company and is actually a driver
            if ($driver->role !== 'driver') {
                return $this->error('User is not a driver', null, 400);
            }

            if ($driver->company_id !== $user->company_id && !$user->isAdmin()) {
                return $this->error('Driver must belong to your company', null, 403);
            }

            $order->update(['driver_id' => $driver->id]);

            $order->load(['buyerCompany', 'supplierCompany', 'driver', 'items.product']);

            return $this->success([
                'order' => $order,
            ], 'Driver assigned successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Assign driver error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to assign driver: ' . $e->getMessage(), null, 500);
        }
    }
}