<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Products\CreateProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Company;
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
        description: 'Get a paginated list of products. Suppliers see only their products, buyers see all active products. Products include category, manufacturer, dual pricing (pharmacist_price, customer_price), discount information, and active offers (if any).',
        tags: ['Products'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'supplier_company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by supplier company ID'),
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by product category ID'),
            new OA\Parameter(name: 'manufacturer_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by manufacturer ID'),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search in name, SKU, description, or category'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Products retrieved successfully. Each product includes category, manufacturer, pharmacist_price, customer_price, discount, discount_type, and offers (if product is included in any active offers) fields.'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Product::with(['supplierCompany', 'category', 'manufacturer']);

        $activeOffersEagerLoad = ['offers' => function ($q) {
            $q->where('is_active', true)
                ->where('start_date', '<=', now())
                ->where(function ($subQ) {
                    $subQ->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                });
        }];

        if ($user->isSupplier()) {
            $query->where('supplier_company_id', $user->company_id);
            $query->with($activeOffersEagerLoad);
        } else {
            $query->where('is_active', true);
            $query->with($activeOffersEagerLoad);

            // Hide products from suppliers the buyer cannot reach (pharmacy → retail +
            // hybrid; salesman → wholesale + hybrid).
            $allowedClassifications = Company::visibleSupplierClassificationsFor($user->company);
            if ($allowedClassifications !== null) {
                $query->whereHas('supplierCompany', function ($q) use ($allowedClassifications) {
                    $q->whereIn('supplier_classification', $allowedClassifications);
                });
            }
        }

        // Filter by supplier
        if ($request->has('supplier_company_id')) {
            $query->where('supplier_company_id', $request->supplier_company_id);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by manufacturer
        if ($request->has('manufacturer_id')) {
            $query->where('manufacturer_id', $request->manufacturer_id);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('sku', "%{$search}%")
                    ->orWhereLike('description', "%{$search}%")
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->whereLike('name', "%{$search}%");
                    })
                    ->orWhereHas('manufacturer', function ($manufacturerQuery) use ($search) {
                        $manufacturerQuery->whereLike('name', "%{$search}%");
                    });
            });
        }

        [$products, $pagination] = $this->paginateOrAll($query->latest(), $request);

        $additional = [
            'success' => true,
            'message' => 'Products retrieved successfully',
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return ProductResource::collection($products)
            ->additional($additional)
            ->response();
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
                required: ['name', 'sku', 'pharmacist_price'],
                properties: [
                    new OA\Property(property: 'supplier_company_id', type: 'integer', description: 'Required for admins. Supplier company ID. Ignored for suppliers (uses their company).', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Paracetamol 500mg'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Pain reliever and fever reducer'),
                    new OA\Property(property: 'category_id', type: 'integer', nullable: true, description: 'Category ID (must exist in categories table)', example: 1),
                    new OA\Property(property: 'manufacturer_id', type: 'integer', nullable: true, description: 'Manufacturer ID (must exist in manufacturers table)', example: 1),
                    new OA\Property(property: 'sku', type: 'string', example: 'PAR-500-001'),
                    new OA\Property(property: 'barcode', type: 'string', nullable: true, example: '1234567890123'),
                    new OA\Property(property: 'unit', type: 'string', default: 'piece', example: 'box', enum: ['piece', 'box', 'bottle', 'pack', 'carton']),
                    new OA\Property(property: 'pharmacist_price', type: 'number', format: 'float', description: 'Selling price for pharmacists/buyers. Discount applies to this price.', example: 25.50),
                    new OA\Property(property: 'customer_price', type: 'number', format: 'float', nullable: true, description: 'Selling price for end customers', example: 30.00),
                    new OA\Property(property: 'discount', type: 'number', format: 'float', nullable: true, description: 'Discount applied to pharmacist price (percentage or fixed based on discount_type)', example: 10.00),
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
        try {
            $this->authorize('create', Product::class);

            $user = auth()->user();
            $supplierCompanyId = $user->isSupplier()
                ? $user->company_id
                : $request->supplier_company_id;

            $pharmacistPrice = $request->pharmacist_price;
            $product = Product::create([
                'supplier_company_id' => $supplierCompanyId,
                'name' => $request->name,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'manufacturer_id' => $request->manufacturer_id,
                'sku' => $request->sku,
                'barcode' => $request->barcode,
                'unit' => $request->unit ?? 'piece',
                'price' => $pharmacistPrice,
                'wholesale_price' => $request->wholesale_price,
                'wholesale_min_qty' => $request->wholesale_min_qty,
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
                new ProductResource($product->load(['supplierCompany', 'category', 'manufacturer', 'offers'])),
                'Product created successfully',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Product create error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to create product. Please try again later.', null, 500);
        }
    }

    /**
     * Get a specific product.
     */
    #[OA\Get(
        path: '/products/{id}',
        summary: 'Get product',
        description: 'Get details of a specific product including category, manufacturer, dual pricing (pharmacist_price, customer_price), discount, discount_type, and active offers (if any)',
        tags: ['Products'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product retrieved successfully. Includes category, manufacturer, pharmacist_price, customer_price, discount, discount_type, and offers (if product is included in any active offers) fields.',
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
                                        new OA\Property(property: 'pharmacistPrice', type: 'number', format: 'float', example: 25.50),
                                        new OA\Property(property: 'customerPrice', type: 'number', format: 'float', nullable: true, example: 30.00),
                                        new OA\Property(property: 'discount', type: 'number', format: 'float', nullable: true, example: 10.00),
                                        new OA\Property(property: 'pharmacistPriceAfterDiscount', type: 'number', format: 'float', nullable: true, description: 'Pharmacist price after discount (only when discount exists)', example: 22.95),
                                        new OA\Property(property: 'discount_type', type: 'string', nullable: true, example: 'percentage', enum: ['percentage', 'fixed']),
                                        new OA\Property(property: 'stock_quantity', type: 'integer', example: 100),
                                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                        new OA\Property(
                                            property: 'offers',
                                            type: 'array',
                                            description: 'Array of active offers this product is included in (if any)',
                                            items: new OA\Items(
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'name', type: 'string', example: 'Buy 2 Get 1 Free'),
                                                    new OA\Property(property: 'description', type: 'string', nullable: true),
                                                    new OA\Property(property: 'offerType', type: 'string', example: 'buy_x_get_y'),
                                                    new OA\Property(property: 'discountValue', type: 'number', format: 'float', nullable: true),
                                                    new OA\Property(property: 'quantityRequired', type: 'integer', nullable: true),
                                                    new OA\Property(property: 'quantityFree', type: 'integer', nullable: true),
                                                    new OA\Property(property: 'startDate', type: 'string', format: 'date-time'),
                                                    new OA\Property(property: 'endDate', type: 'string', format: 'date-time'),
                                                    new OA\Property(property: 'isActive', type: 'boolean'),
                                                    new OA\Property(property: 'isCurrentlyActive', type: 'boolean'),
                                                    new OA\Property(
                                                        property: 'pivot',
                                                        type: 'object',
                                                        description: 'Product-specific offer configuration',
                                                        properties: [
                                                            new OA\Property(property: 'quantityRequired', type: 'integer', nullable: true),
                                                            new OA\Property(property: 'quantityFree', type: 'integer', nullable: true),
                                                            new OA\Property(property: 'discountOverride', type: 'number', format: 'float', nullable: true),
                                                        ]
                                                    ),
                                                ]
                                            )
                                        ),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function show(Product $product): JsonResponse
    {
        $product->load([
            'supplierCompany',
            'category',
            'manufacturer',
            'offers' => function ($q) {
                $q->where('is_active', true)
                    ->where('start_date', '<=', now())
                    ->where('end_date', '>=', now());
            },
        ]);

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
                    new OA\Property(property: 'pharmacist_price', type: 'number', format: 'float', nullable: true, description: 'Selling price for pharmacists/buyers (discount applies to this)', example: 25.50),
                    new OA\Property(property: 'customer_price', type: 'number', format: 'float', nullable: true, description: 'Selling price for end customers', example: 30.00),
                    new OA\Property(property: 'discount', type: 'number', format: 'float', nullable: true, description: 'Discount applied to pharmacist price', example: 10.00),
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
        $product->load(['supplierCompany', 'category', 'manufacturer', 'offers']);

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
