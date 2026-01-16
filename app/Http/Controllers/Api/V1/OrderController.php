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
        description: 'Get a paginated list of orders. Filtered by user\'s company.',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['pending', 'accepted', 'prepared', 'shipped', 'delivered', 'cancelled']
                )
            ),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Orders retrieved successfully'),
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
        description: 'Create a new order. Only buyers can create orders.',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['supplier_company_id', 'items'],
                properties: [
                    new OA\Property(property: 'supplier_company_id', type: 'integer', example: 1),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 10),
                                new OA\Property(property: 'unit_price', type: 'number', format: 'float', example: 25.50),
                                new OA\Property(property: 'notes', type: 'string', nullable: true),
                            ]
                        )
                    ),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(property: 'tax_rate', type: 'number', format: 'float', default: 0, example: 15),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only buyers can create orders'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isBuyer()) {
            return $this->error('Only buyers can create orders', null, 403);
        }

        $order = $this->orderCreationService->create(
            buyerCompanyId: $user->company_id,
            supplierCompanyId: $request->supplier_company_id,
            createdBy: $user->id,
            items: $request->items,
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
        description: 'Get details of a specific order',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
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
        description: 'Transition order to a new status. Valid transitions are enforced.',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
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
                        example: 'accepted'
                    ),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Order status updated successfully'),
            new OA\Response(response: 400, description: 'Invalid status transition'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
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
        description: 'Cancel an order. Requires cancellation reason.',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Out of stock'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Order cancelled successfully'),
            new OA\Response(response: 400, description: 'Cannot cancel order in current status'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
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
        }
    }
}