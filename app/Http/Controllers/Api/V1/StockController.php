<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\StockMovementType;
use App\Domain\Inventory\StockMovementService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Stock\AdjustStockRequest;
use App\Http\Requests\Stock\AddStockRequest;
use App\Models\Product;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Stock Management', description: 'Stock and inventory management endpoints')]
class StockController extends BaseApiController
{
    public function __construct(
        private StockMovementService $stockMovementService
    ) {}

    /**
     * Get stock movements for a product.
     */
    #[OA\Get(
        path: '/products/{id}/stock/movements',
        summary: 'Get stock movement history',
        description: 'Get a paginated list of stock movements for a specific product',
        tags: ['Stock Management'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['in', 'out', 'adjustment', 'return'])),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Stock movements retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function movements(Request $request, Product $product): JsonResponse
    {
        $user = auth()->user();

        // Suppliers can only see movements for their products
        if ($user->isSupplier() && $product->supplier_company_id !== $user->company_id) {
            return $this->error('You can only view stock movements for your own products', null, 403);
        }

        $query = StockMovement::where('product_id', $product->id)
            ->with(['user', 'order']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

            [$movements, $pagination] = $this->paginateOrAll($query->latest(), $request);

            $additional = [
                'success' => true,
                'message' => 'Stock movements retrieved successfully',
            ];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return StockMovementResource::collection($movements)
                ->additional($additional)
                ->response();
    }

    /**
     * Add stock to a product.
     */
    #[OA\Post(
        path: '/products/{id}/stock/add',
        summary: 'Add stock',
        description: 'Add stock to a product. Only suppliers can add stock to their products.',
        tags: ['Stock Management'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['quantity'],
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', example: 100),
                    new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'New shipment received'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Stock added successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only suppliers can add stock'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function add(AddStockRequest $request, Product $product): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isSupplier()) {
            return $this->error('Only suppliers can add stock', null, 403);
        }

        if ($product->supplier_company_id !== $user->company_id) {
            return $this->error('You can only add stock to your own products', null, 403);
        }

        $movement = $this->stockMovementService->recordIn(
            product: $product,
            quantity: $request->quantity,
            userId: $user->id,
            reason: $request->reason
        );

        $product->refresh();

        return $this->success([
            'movement' => $movement->load('user'),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock_quantity' => $product->stock_quantity,
                'min_stock_level' => $product->min_stock_level,
                'is_low_stock' => $product->isLowStock(),
            ],
        ], 'Stock added successfully');
    }

    /**
     * Adjust stock (can be positive or negative).
     */
    #[OA\Post(
        path: '/products/{id}/stock/adjust',
        summary: 'Adjust stock',
        description: 'Adjust stock quantity (can be positive or negative). Only suppliers can adjust stock for their products.',
        tags: ['Stock Management'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['quantity', 'type'],
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', example: -10, description: 'Positive for increase, negative for decrease'),
                    new OA\Property(property: 'type', type: 'string', enum: ['adjustment', 'return'], example: 'adjustment'),
                    new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'Stock adjustment due to inventory count'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Stock adjusted successfully'),
            new OA\Response(response: 400, description: 'Insufficient stock'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only suppliers can adjust stock'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function adjust(AdjustStockRequest $request, Product $product): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isSupplier()) {
            return $this->error('Only suppliers can adjust stock', null, 403);
        }

        if ($product->supplier_company_id !== $user->company_id) {
            return $this->error('You can only adjust stock for your own products', null, 403);
        }

        try {
            $movement = $this->stockMovementService->recordMovement(
                product: $product,
                type: $request->type,
                quantity: $request->quantity,
                userId: $user->id,
                reason: $request->reason,
                referenceType: 'manual',
                referenceId: (string) $user->id
            );

            $product->refresh();

            return $this->success([
                'movement' => new StockMovementResource($movement->load(['product', 'user'])),
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => $product->stock_quantity,
                    'min_stock_level' => $product->min_stock_level,
                    'is_low_stock' => $product->isLowStock(),
                ],
            ], 'Stock adjusted successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }
}