<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\OrderStatus;
use App\Domain\Orders\OrderCreationService;
use App\Domain\Orders\OrderStateTransitionService;
use App\Domain\Pricing\PricingService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Orders\AssignDriverRequest;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Requests\Orders\CreateOrderRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Orders', description: 'Order management endpoints')]
class OrderController extends BaseApiController
{
    public function __construct(
        private OrderCreationService $orderCreationService,
        private OrderStateTransitionService $orderStateTransitionService,
        private PricingService $pricingService
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
            new OA\Parameter(name: 'from_date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Filter orders created from this date (YYYY-MM-DD)'),
            new OA\Parameter(name: 'to_date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), description: 'Filter orders created up to this date (YYYY-MM-DD)'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
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
                                new OA\Property(property: 'currentPage', type: 'integer', example: 1),
                                new OA\Property(property: 'total', type: 'integer', example: 15),
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
            $query = Order::with([
                'buyerCompany.region.city',
                'supplierCompany.categories',
                'supplierCompany.manufacturers',
                'driver',
                'salesman',
                'invoice',
                'items',
                'items.product.category',
                'items.product.manufacturer',
                'items.product.supplierCompany',
                'items.appliedOffer',
            ]);

            if ($user->isAdmin()) {
                // Admin sees all orders
            } elseif ($user->isBuyer() || ($user->isSalesman() && $user->isIndependentSalesman())) {
                $query->where('buyer_company_id', $user->company_id);
            } elseif ($user->isSalesman() && $user->isSupplierSalesman()) {
                $query->where(function ($q) use ($user) {
                    $q->where('supplier_company_id', $user->company_id)
                        ->orWhere('placed_by_salesman_id', $user->id);
                });
            } elseif ($user->isSupplier()) {
                $query->where('supplier_company_id', $user->company_id);
            } elseif ($user->isDriver()) {
                $query->where('driver_id', $user->id);
            } else {
                return $this->error('Unauthorized', null, 403);
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

            // Filter by date range
            if ($request->has('from_date')) {
                $fromDate = Carbon::parse($request->from_date)->startOfDay();
                $query->where('created_at', '>=', $fromDate);
            }

            if ($request->has('to_date')) {
                $toDate = Carbon::parse($request->to_date)->endOfDay();
                $query->where('created_at', '<=', $toDate);
            }

            [$orders, $pagination] = $this->paginateOrAll($query->latest(), $request);

            $additional = [
                'success' => true,
                'message' => 'Orders retrieved successfully',
            ];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return OrderResource::collection($orders)
                ->additional($additional)
                ->response();
        } catch (\Exception $e) {
            Log::error('Order list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to retrieve orders. Please try again later.', null, 500);
        }
    }

    /**
     * Create a new order.
     */
    #[OA\Post(
        path: '/orders',
        summary: 'Create order',
        description: 'Create a new order for a **single supplier**. Only buyers and salesmen can create orders. If unit_price is not provided, PricingService resolves the correct price based on buyer type, supplier classification, quantity (wholesale eligibility), and custom price lists. **Important:** This endpoint creates ONE order for ONE supplier. If you need to order from multiple suppliers, use the cart checkout endpoint (`POST /cart/checkout`) which automatically splits the cart into multiple orders (one per supplier).',
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
                                new OA\Property(property: 'unit_price', type: 'number', format: 'float', nullable: true, description: 'Optional - PricingService resolves the correct tier price (custom, wholesale, or retail) if not provided', example: 25.50),
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
                                ),
                            ]
                        ),
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
        $user = $this->currentUser();

        if (! $user->canBuy()) {
            return $this->error('Only buyers and salesmen can create orders', null, 403);
        }

        $buyerCompany = $user->company;
        if ($buyerCompany === null) {
            return $this->error('Your account is not linked to a company.', null, 422);
        }

        $supplierCompany = \App\Models\Company::find((int) $request->supplier_company_id);
        if ($supplierCompany && ! $this->pricingService->canBuyerSeeSupplier($supplierCompany, $buyerCompany)) {
            return $this->error('This supplier is not available to your account', null, 403);
        }

        $items = [];
        foreach ($request->items as $item) {
            $product = \App\Models\Product::findOrFail((int) $item['product_id']);

            if ($item['unit_price'] ?? null) {
                $unitPrice = $item['unit_price'];
                $pricingType = $item['pricing_type'] ?? null;
            } else {
                $priceResult = $this->pricingService->resolve($product, $buyerCompany, $item['quantity']);
                $unitPrice = $priceResult->unitPrice;
                $pricingType = $priceResult->pricingType;
            }

            $items[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'pricing_type' => $pricingType,
                'notes' => $item['notes'] ?? null,
            ];
        }

        $order = $this->orderCreationService->create(
            buyerCompanyId: $buyerCompany->id,
            supplierCompanyId: $request->supplier_company_id,
            createdBy: $user->id,
            items: $items,
            notes: $request->notes,
            taxRate: $request->tax_rate ?? 0,
            paymentMethod: $request->payment_method,
        );

        // Salesman attribution: always the authenticated salesman (never a client-supplied
        // id — that was a spoofing vector). On-behalf ordering uses the dedicated
        // /salesmen/{id}/place-order endpoint. Payment terms + credit reservation are
        // handled inside OrderCreationService (transactional, credit-limit enforced).
        if ($user->isSalesman()) {
            $order->salesman()->associate($user);
            $order->save();
        }

        return $this->success(
            new OrderResource($order->load([
                'buyerCompany.region.city',
                'supplierCompany.categories',
                'supplierCompany.manufacturers',
                'driver',
                'invoice',
                'items.product.category',
                'items.product.manufacturer',
                'items.product.supplierCompany',
                'items.appliedOffer',
            ])),
            'Order created successfully',
            201
        );
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
                                ),
                            ]
                        ),
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

        $order->load([
            'buyerCompany.region.city',
            'supplierCompany.categories',
            'supplierCompany.manufacturers',
            'creator',
            'driver',
            'salesman',
            'invoice',
            'items.product.category',
            'items.product.manufacturer',
            'items.product.supplierCompany',
            'items.appliedOffer',
            'statusLogs.user',
        ]);

        return $this->success(new OrderResource($order));
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
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Invalid status transition - cannot transition from current status to requested status'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only the supplier of the order may advance its status (accept/prepare/ship/deliver). Buyers and salesmen can only cancel.'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('updateStatus', [$order, $request->status]);

        $order = $this->orderStateTransitionService->transition(
            order: $order,
            newStatus: $request->status,
            userId: (int) auth()->id(),
            notes: $request->notes
        );

        return $this->success(
            new OrderResource($order->load([
                'buyerCompany.region.city',
                'supplierCompany.categories',
                'supplierCompany.manufacturers',
                'driver',
                'invoice',
                'items.product.category',
                'items.product.manufacturer',
                'items.product.supplierCompany',
                'items.appliedOffer',
            ])),
            'Order status updated successfully'
        );
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
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Cannot cancel order in current status - order may already be delivered or cancelled'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Buyers can cancel their own orders, suppliers can cancel orders placed with them'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $order->cancellation_reason = $request->reason;
        $order->save();

        $order = $this->orderStateTransitionService->transition(
            order: $order,
            newStatus: OrderStatus::CANCELLED,
            userId: (int) auth()->id(),
            notes: $request->reason
        );

        return $this->success(
            new OrderResource($order->load([
                'buyerCompany.region.city',
                'supplierCompany.categories',
                'supplierCompany.manufacturers',
                'driver',
                'invoice',
                'items.product.category',
                'items.product.manufacturer',
                'items.product.supplierCompany',
                'items.appliedOffer',
            ])),
            'Order cancelled successfully'
        );
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
    public function assignDriver(AssignDriverRequest $request, Order $order): JsonResponse
    {
        try {
            $user = $this->currentUser();

            // Only suppliers can assign drivers to their orders
            if (! $user->isSupplier() && ! $user->isAdmin()) {
                return $this->error('Only suppliers can assign drivers', null, 403);
            }

            // Verify order belongs to supplier's company
            if ($order->supplier_company_id !== $user->company_id && ! $user->isAdmin()) {
                return $this->error('You can only assign drivers to your own orders', null, 403);
            }

            $driver = \App\Models\User::findOrFail((int) $request->driver_id);

            // Verify driver belongs to supplier's company and is actually a driver
            if (! $driver->isDriver()) {
                return $this->error('User is not a driver', null, 400);
            }

            if ($driver->company_id !== $user->company_id && ! $user->isAdmin()) {
                return $this->error('Driver must belong to your company', null, 403);
            }

            // Check if driver serves the pharmacy's region (optional warning, not blocking)
            $buyerCompany = $order->buyerCompany;
            if ($buyerCompany && $buyerCompany->region_id) {
                if (! $driver->servesRegion($buyerCompany->region_id)) {
                    // Log warning but don't block assignment
                    Log::warning('Driver assigned to order outside their region', [
                        'driver_id' => $driver->id,
                        'order_id' => $order->id,
                        'pharmacy_region_id' => $buyerCompany->region_id,
                    ]);
                }
            }

            $order->update(['driver_id' => $driver->id]);

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

            return $this->success(
                new OrderResource($order),
                'Driver assigned successfully'
            );
        } catch (\Exception $e) {
            Log::error('Assign driver error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to assign driver. Please try again later.', null, 500);
        }
    }
}
