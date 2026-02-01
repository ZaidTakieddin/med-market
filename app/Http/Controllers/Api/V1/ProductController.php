<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Products\CreateProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
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
            description: 'Get a paginated list of products. Suppliers see only their products, buyers see all active products. Products include category, dual pricing (pharmacist_price, customer_price), and discount information.',
            tags: ['Products'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'supplier_company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by supplier company ID'),
                new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by product category ID'),
                new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
                new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search in name, SKU, description, or category'),
                new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
                new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Products retrieved successfully. Each product includes category, pharmacist_price, customer_price, discount, and discount_type fields.'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Product::with(['supplierCompany', 'category', 'manufacturer']);

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

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
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
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('manufacturer', function ($manufacturerQuery) use ($search) {
                            $manufacturerQuery->where('name', 'ilike', "%{$search}%");
                        });
                });
            }

            $products = $query->latest()->paginate($request->get('per_page', 15));

            return ProductResource::collection($products->items())
                ->additional([
                        'success' => true,
                        'message' => 'Products retrieved successfully',
                        'pagination' => [
                            'current_page' => $products->currentPage(),
                            'per_page' => $products->perPage(),
                            'total' => $products->total(),
                            'last_page' => $products->lastPage(),
                            'from' => $products->firstItem(),
                            'to' => $products->lastItem(),
                        ],
                    ])
                ->response();
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
                    required: ['name', 'sku'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Paracetamol 500mg'),
                        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Pain reliever and fever reducer'),
                        new OA\Property(property: 'category_id', type: 'integer', nullable: true, description: 'Category ID (must exist in categories table)', example: 1),
                        new OA\Property(property: 'manufacturer_id', type: 'integer', nullable: true, description: 'Manufacturer ID (must exist in manufacturers table)', example: 1),
                        new OA\Property(property: 'sku', type: 'string', example: 'PAR-500-001'),
                        new OA\Property(property: 'barcode', type: 'string', nullable: true, example: '1234567890123'),
                        new OA\Property(property: 'unit', type: 'string', default: 'piece', example: 'box', enum: ['piece', 'box', 'bottle', 'pack', 'carton']),
                        new OA\Property(property: 'price', type: 'number', format: 'float', nullable: true, description: 'Legacy field - kept for backward compatibility. Use pharmacist_price instead.', example: 25.50),
                        new OA\Property(property: 'pharmacist_price', type: 'number', format: 'float', description: 'Selling price for pharmacists/buyers. Required if price is not provided.', example: 25.50),
                        new OA\Property(property: 'customer_price', type: 'number', format: 'float', nullable: true, description: 'Selling price for end customers', example: 30.00),
                        new OA\Property(property: 'discount', type: 'number', format: 'float', nullable: true, description: 'Discount amount (percentage or fixed based on discount_type)', example: 10.00),
                        new OA\Property(property: 'discount_type', type: 'string', nullable: true, enum: ['percentage', 'fixed'], description: 'Type of discount: percentage or fixed amount', example: 'percentage'),
                        new OA\Property(property: 'stock_quantity', type: 'integer', default: 0, example: 100),
                        new OA\Property(property: 'min_stock_level', type: 'integer', default: 0, example: 10),
                        new OA\Property(property: 'is_active', type: 'boolean', default: true),
                        new OA\Property(property: 'metadata', type: 'object', nullable: true, description: 'Additional product information as JSON object'),
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

        // Handle pricing: use pharmacist_price if provided, otherwise fall back to price
        $pharmacistPrice = $request->pharmacist_price ?? $request->price;
        $price = $request->price ?? $pharmacistPrice; // Legacy field

        $product = Product::create([
            'supplier_company_id' => $user->company_id,
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'manufacturer_id' => $request->manufacturer_id,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'unit' => $request->unit ?? 'piece',
            'price' => $price,
            'pharmacist_price' => $pharmacistPrice,
            'customer_price' => $request->customer_price,
            'discount' => $request->discount,
            'discount_type' => $request->discount_type,
            'stock_quantity' => $request->stock_quantity ?? 0,
            'min_stock_level' => $request->min_stock_level ?? 0,
            'is_active' => $request->is_active ?? true,
            'metadata' => $request->metadata,
        ]);

        return $this->success(
            new ProductResource($product->load(['supplierCompany', 'category', 'manufacturer'])),
            'Product created successfully',
            201
        );
    }

    /**
     * Get a specific product.
     */
    #[OA\Get(
            path: '/products/{id}',
            summary: 'Get product',
            description: 'Get details of a specific product including category, dual pricing (pharmacist_price, customer_price), discount, and discount_type',
            tags: ['Products'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Product retrieved successfully. Includes category, pharmacist_price, customer_price, discount, and discount_type fields.',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(
                                property: 'data',
                                type: 'object',
                                properties: [
                                    new OA\Property(
                                        property: 'product',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'Paracetamol 500mg'),
                                            new OA\Property(property: 'description', type: 'string', nullable: true),
                                            new OA\Property(property: 'category', type: 'object', nullable: true, description: 'Category object with id and name'),
                                            new OA\Property(property: 'manufacturer', type: 'object', nullable: true, description: 'Manufacturer object with id and name'),
                                            new OA\Property(property: 'sku', type: 'string', example: 'PAR-500-001'),
                                            new OA\Property(property: 'pharmacist_price', type: 'number', format: 'float', example: 25.50),
                                            new OA\Property(property: 'customer_price', type: 'number', format: 'float', nullable: true, example: 30.00),
                                            new OA\Property(property: 'discount', type: 'number', format: 'float', nullable: true, example: 10.00),
                                            new OA\Property(property: 'discount_type', type: 'string', nullable: true, example: 'percentage', enum: ['percentage', 'fixed']),
                                            new OA\Property(property: 'stock_quantity', type: 'integer', example: 100),
                                            new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                        ]
                                    )
                                ]
                            )
                        ]
                    )
                ),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Product not found'),
            ]
        )]
    public function show(Product $product): JsonResponse
    {
        $product->load('supplierCompany');

        return $this->success(new ProductResource($product));
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
                        new OA\Property(property: 'name', type: 'string', example: 'Paracetamol 500mg'),
                        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Pain reliever and fever reducer'),
                        new OA\Property(property: 'category_id', type: 'integer', nullable: true, description: 'Category ID (must exist in categories table)', example: 1),
                        new OA\Property(property: 'manufacturer_id', type: 'integer', nullable: true, description: 'Manufacturer ID (must exist in manufacturers table)', example: 1),
                        new OA\Property(property: 'sku', type: 'string', example: 'PAR-500-001'),
                        new OA\Property(property: 'barcode', type: 'string', nullable: true, example: '1234567890123'),
                        new OA\Property(property: 'unit', type: 'string', example: 'box', enum: ['piece', 'box', 'bottle', 'pack', 'carton']),
                        new OA\Property(property: 'price', type: 'number', format: 'float', nullable: true, description: 'Legacy field - kept for backward compatibility'),
                        new OA\Property(property: 'pharmacist_price', type: 'number', format: 'float', nullable: true, description: 'Selling price for pharmacists/buyers', example: 25.50),
                        new OA\Property(property: 'customer_price', type: 'number', format: 'float', nullable: true, description: 'Selling price for end customers', example: 30.00),
                        new OA\Property(property: 'discount', type: 'number', format: 'float', nullable: true, description: 'Discount amount (percentage or fixed based on discount_type)', example: 10.00),
                        new OA\Property(property: 'discount_type', type: 'string', nullable: true, enum: ['percentage', 'fixed'], description: 'Type of discount: percentage or fixed amount', example: 'percentage'),
                        new OA\Property(property: 'stock_quantity', type: 'integer', nullable: true, example: 100),
                        new OA\Property(property: 'min_stock_level', type: 'integer', nullable: true, example: 10),
                        new OA\Property(property: 'is_active', type: 'boolean', nullable: true, example: true),
                        new OA\Property(property: 'metadata', type: 'object', nullable: true, description: 'Additional product information as JSON object'),
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
        $product->load(['supplierCompany', 'category', 'manufacturer']);

        return $this->success(
            new ProductResource($product),
            'Product updated successfully'
        );
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