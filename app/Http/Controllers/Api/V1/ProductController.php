<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Products\CreateProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Products', description: 'Product management endpoints')]
class ProductController extends BaseApiController
{
    /**
     * List products.
     */
    #[OA\Get(
        path: '/products',
        summary: 'List products',
        description: 'Get a paginated list of products. Suppliers see only their products, buyers see all active products.',
        tags: ['Products'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'supplier_company_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Products retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Product::with('supplierCompany');

            // Suppliers see only their products
            if ($user->isSupplier()) {
                $query->where('supplier_company_id', $user->company_id);
            } else {
                // Buyers see all active products
                $query->where('is_active', true);
            }

            // Filter by supplier
            if ($request->has('supplier_company_id')) {
                $query->where('supplier_company_id', $request->supplier_company_id);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('sku', 'ilike', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $products = $query->latest()->paginate($request->get('per_page', 15));

            // Prevent circular references by hiding unnecessary relationships
            $products->getCollection()->each(function ($product) {
                if ($product->relationLoaded('supplierCompany') && $product->supplierCompany) {
                    $product->supplierCompany->makeHidden(['users', 'products', 'buyerOrders', 'supplierOrders']);
                }
                $product->makeHidden(['orderItems', 'stockMovements']);
            });

            return $this->paginated($products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Product list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve products: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create a new product.
     */
    #[OA\Post(
        path: '/products',
        summary: 'Create product',
        description: 'Create a new product. Only suppliers can create products.',
        tags: ['Products'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'sku', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Paracetamol 500mg'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'sku', type: 'string', example: 'PAR-500-001'),
                    new OA\Property(property: 'barcode', type: 'string', nullable: true),
                    new OA\Property(property: 'unit', type: 'string', default: 'piece', example: 'box'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 25.50),
                    new OA\Property(property: 'stock_quantity', type: 'integer', default: 0, example: 100),
                    new OA\Property(property: 'min_stock_level', type: 'integer', default: 0, example: 10),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true),
                    new OA\Property(property: 'metadata', type: 'object', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only suppliers can create products'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(CreateProductRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isSupplier()) {
            return $this->error('Only suppliers can create products', null, 403);
        }

        $product = Product::create([
            'supplier_company_id' => $user->company_id,
            'name' => $request->name,
            'description' => $request->description,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'unit' => $request->unit ?? 'piece',
            'price' => $request->price,
            'stock_quantity' => $request->stock_quantity ?? 0,
            'min_stock_level' => $request->min_stock_level ?? 0,
            'is_active' => $request->is_active ?? true,
            'metadata' => $request->metadata,
        ]);

        return $this->success([
            'product' => $product->load('supplierCompany'),
        ], 'Product created successfully', 201);
    }

    /**
     * Get a specific product.
     */
    #[OA\Get(
        path: '/products/{id}',
        summary: 'Get product',
        description: 'Get details of a specific product',
        tags: ['Products'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function show(Product $product): JsonResponse
    {
        $product->load('supplierCompany');

        return $this->success([
            'product' => $product,
        ]);
    }

    /**
     * Update a product.
     */
    #[OA\Put(
        path: '/products/{id}',
        summary: 'Update product',
        description: 'Update product details. Only suppliers from the same company can update.',
        tags: ['Products'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'barcode', type: 'string', nullable: true),
                    new OA\Property(property: 'unit', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float'),
                    new OA\Property(property: 'min_stock_level', type: 'integer'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'metadata', type: 'object', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Product updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return $this->success([
            'product' => $product->load('supplierCompany'),
        ], 'Product updated successfully');
    }

    /**
     * Delete a product.
     */
    #[OA\Delete(
        path: '/products/{id}',
        summary: 'Delete product',
        description: 'Soft delete a product. Only suppliers from the same company can delete.',
        tags: ['Products'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return $this->success(null, 'Product deleted successfully');
    }
}