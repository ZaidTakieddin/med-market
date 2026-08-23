<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventory\StockMovementService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Stock\AddStockRequest;
use App\Http\Requests\Stock\AdjustStockRequest;
use App\Http\Requests\Stock\BulkStockRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
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
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function movements(Request $request, Product $product): JsonResponse
    {
        $this->authorize('viewStockMovements', $product);

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
                    new OA\Property(property: 'batch_number', type: 'string', nullable: true, example: 'BATCH-2026-A', description: 'Recorded on the movement and set as the product\'s current batch'),
                    new OA\Property(property: 'expiry_date', type: 'string', format: 'date', nullable: true, description: 'Recorded on the movement and set as the product\'s expiry date'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Stock added successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function add(AddStockRequest $request, Product $product): JsonResponse
    {
        $this->authorize('mutateStock', $product);

        $user = $this->currentUser();

        $movement = $this->stockMovementService->recordIn(
            product: $product,
            quantity: $request->quantity,
            userId: $user->id,
            reason: $request->reason,
            batchNumber: $request->batch_number,
            expiryDate: $request->expiry_date
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
                    new OA\Property(property: 'batch_number', type: 'string', nullable: true),
                    new OA\Property(property: 'expiry_date', type: 'string', format: 'date', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Stock adjusted successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function adjust(AdjustStockRequest $request, Product $product): JsonResponse
    {
        $this->authorize('mutateStock', $product);

        $user = $this->currentUser();

        $movement = $this->stockMovementService->recordMovement(
            product: $product,
            type: $request->type,
            quantity: $request->quantity,
            userId: $user->id,
            reason: $request->reason,
            referenceType: 'manual',
            referenceId: (string) $user->id,
            batchNumber: $request->batch_number,
            expiryDate: $request->expiry_date
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
    }

    /**
     * Apply bulk stock operations.
     */
    #[OA\Post(
        path: '/products/stock/bulk',
        summary: 'Bulk stock operations',
        description: 'Apply up to 200 stock operations in one call (in / out / adjustment / return per row). Each row is processed independently through StockMovementService — authorization and stock rules are enforced per product; a failed row does not roll back the others. Rows may carry `batch_number`/`expiry_date`, which also update the product\'s current batch. **Route note:** registered before the products resource so `{product}` cannot capture it.',
        tags: ['Stock Management'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['operations'],
                properties: [
                    new OA\Property(property: 'operations', type: 'array', items: new OA\Items(
                        type: 'object',
                        required: ['product_id', 'type', 'quantity'],
                        properties: [
                            new OA\Property(property: 'product_id', type: 'integer', example: 1),
                            new OA\Property(property: 'type', type: 'string', enum: ['in', 'out', 'adjustment', 'return']),
                            new OA\Property(property: 'quantity', type: 'integer', example: 25, description: 'Signed for adjustment/return'),
                            new OA\Property(property: 'reason', type: 'string', nullable: true),
                            new OA\Property(property: 'batch_number', type: 'string', nullable: true),
                            new OA\Property(property: 'expiry_date', type: 'string', format: 'date', nullable: true),
                        ]
                    )),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Bulk operations processed. Returns one result per row with success/failure details.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function bulk(BulkStockRequest $request): JsonResponse
    {
        $user = $this->currentUser();

        $results = [];
        foreach ($request->operations as $index => $operation) {
            /** @var Product|null $product */
            $product = Product::find((int) $operation['product_id']);

            $row = [
                'index' => $index,
                'product_id' => (int) $operation['product_id'],
                'success' => false,
            ];

            if ($product === null || ! $user->can('mutateStock', $product)) {
                $row['error'] = 'Product not found or not owned by your company.';

                $results[] = $row;

                continue;
            }

            try {
                // Normalize sign by movement type: 'in' adds, 'out' subtracts,
                // adjustment/return keep their explicit sign.
                $quantity = (int) $operation['quantity'];
                if ($operation['type'] === \App\Constants\StockMovementType::IN) {
                    $quantity = abs($quantity);
                } elseif ($operation['type'] === \App\Constants\StockMovementType::OUT) {
                    $quantity = -abs($quantity);
                }

                $movement = $this->stockMovementService->recordMovement(
                    product: $product,
                    type: (string) $operation['type'],
                    quantity: $quantity,
                    userId: $user->id,
                    reason: $operation['reason'] ?? null,
                    referenceType: 'manual',
                    referenceId: (string) $user->id,
                    batchNumber: $operation['batch_number'] ?? null,
                    expiryDate: $operation['expiry_date'] ?? null
                );

                $row['success'] = true;
                $row['new_stock_quantity'] = $product->stock_quantity;
                $row['movement_id'] = $movement->id;
            } catch (\App\Exceptions\DomainException $e) {
                $row['error'] = $e->getMessage();
            }

            $results[] = $row;
        }

        $failed = count(array_filter($results, fn (array $row): bool => ! $row['success']));

        return $this->success([
            'results' => $results,
            'total' => count($results),
            'succeeded' => count($results) - $failed,
            'failed' => $failed,
        ], $failed === 0 ? 'Bulk stock operations applied successfully' : 'Bulk stock operations completed with failures');
    }
}
