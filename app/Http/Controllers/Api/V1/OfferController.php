<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Products\PromotionService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Offers\CreateOfferRequest;
use App\Http\Requests\Offers\UpdateOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Offers',
    description: 'Product offers and promotions. **Offer types**: buy_x_get_y (buy X get Y free), percentage_discount, fixed_discount, bundle_percentage (bundle with % off), bundle_fixed (bundle with fixed off). All offers require quantity_required. Bundle offers require multiple products. Applied in cart via CartOfferService (best offer auto-selected per product).'
)]
class OfferController extends BaseApiController
{
    public function __construct(
        private PromotionService $promotionService
    ) {}

    /**
     * List offers.
     */
    #[OA\Get(
        path: '/offers',
        summary: 'List offers',
        description: 'Get a paginated list of offers. Suppliers see only their offers, buyers see all active offers. Filter by offer_type, supplier_company_id, is_active.',
        tags: ['Offers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'supplier_company_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(
                name: 'offer_type',
                in: 'query',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['buy_x_get_y', 'percentage_discount', 'fixed_discount', 'bundle_percentage', 'bundle_fixed']
                )
            ),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Offers retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $query = Offer::with('supplierCompany:id,name,type');

        if ($user->isSupplier() || ($user->isSalesman() && $user->isSupplierSalesman())) {
            $query->where('supplier_company_id', $user->company_id);
        } else {
            // Buyers see only active offers (end_date null = no time limit)
            $query->where('is_active', true)
                ->where('start_date', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                });
        }

        // Filter by supplier
        if ($request->has('supplier_company_id')) {
            $query->where('supplier_company_id', $request->supplier_company_id);
        }

        // Filter by offer type
        if ($request->has('offer_type')) {
            $query->where('offer_type', $request->offer_type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        [$offers, $pagination] = $this->paginateOrAll(
            $query->with('products')->latest(),
            $request
        );

        $additional = [
            'success' => true,
            'message' => 'Offers retrieved successfully',
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return OfferResource::collection($offers)
            ->additional($additional)
            ->response();
    }

    /**
     * Create a new offer.
     */
    #[OA\Post(
        path: '/offers',
        summary: 'Create offer',
        description: 'Create a new product offer or promotion (suppliers only). Request body accepts snake_case or camelCase (e.g. offer_type or offerType) matching OfferResource.',
        tags: ['Offers'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'offer_type', 'start_date'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Buy 2 Get 1 Free'),
                    new OA\Property(property: 'description', type: 'string', example: 'Special promotion'),
                    new OA\Property(property: 'offer_type', type: 'string', enum: ['buy_x_get_y', 'percentage_discount', 'fixed_discount', 'bundle_percentage', 'bundle_fixed'], description: 'Bundle types require products array with quantity per product. Alias: offerType.'),
                    new OA\Property(property: 'offerType', type: 'string', enum: ['buy_x_get_y', 'percentage_discount', 'fixed_discount', 'bundle_percentage', 'bundle_fixed'], description: 'Same as offer_type (camelCase).'),
                    new OA\Property(property: 'discount_value', type: 'number', format: 'float'),
                    new OA\Property(property: 'discount_type', type: 'string', enum: ['percentage', 'fixed']),
                    new OA\Property(property: 'quantity_required', type: 'integer'),
                    new OA\Property(property: 'quantity_free', type: 'integer'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date-time', nullable: true, description: 'Optional. When omitted, offer has no time limit (active indefinitely after start_date)'),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true, description: 'Whether the offer is active. Defaults to true.'),
                    new OA\Property(property: 'product_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'Simple format. Uses offer quantity_required for all.'),
                    new OA\Property(property: 'products', type: 'array', description: 'Bundle format: [{ product_id, quantity }]. Required for bundle_percentage/bundle_fixed.',
                        items: new OA\Items(type: 'object', properties: [
                            new OA\Property(property: 'product_id', type: 'integer'),
                            new OA\Property(property: 'quantity', type: 'integer', description: 'Required quantity for this product'),
                        ])),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Offer created successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(CreateOfferRequest $request): JsonResponse
    {
        $this->authorize('create', Offer::class);

        $user = $this->currentUser();

        // Offer + product pivot sync happen transactionally in the domain service.
        $offer = $this->promotionService->create(
            supplierCompanyId: (int) $user->company_id,
            data: [
                'name' => $request->name,
                'description' => $request->description,
                'offer_type' => $request->offer_type,
                'discount_value' => $request->discount_value,
                'quantity_required' => $request->quantity_required,
                'quantity_free' => $request->quantity_free,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->is_active ?? true,
            ],
            products: $request->input('products'),
            productIds: $request->input('product_ids'),
        );

        $offer->load(['supplierCompany', 'products']);

        return $this->success(
            new OfferResource($offer),
            'Offer created successfully',
            201
        );
    }

    /**
     * Get a specific offer.
     */
    #[OA\Get(
        path: '/offers/{id}',
        summary: 'Get offer',
        description: 'Get details of a specific offer',
        tags: ['Offers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Offer retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(Offer $offer): JsonResponse
    {
        $offer->load(['supplierCompany', 'products']);

        return $this->success(new OfferResource($offer));
    }

    /**
     * Update an offer.
     */
    #[OA\Put(
        path: '/offers/{id}',
        summary: 'Update offer',
        description: 'Update an existing offer (suppliers can only update their own offers)',
        tags: ['Offers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                description: 'All fields are optional. Only include fields to update.',
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Buy 2 Get 1 Free'),
                    new OA\Property(property: 'description', type: 'string', example: 'Special promotion'),
                    new OA\Property(property: 'offer_type', type: 'string', enum: ['buy_x_get_y', 'percentage_discount', 'fixed_discount', 'bundle_percentage', 'bundle_fixed']),
                    new OA\Property(property: 'discount_value', type: 'number', format: 'float'),
                    new OA\Property(property: 'quantity_required', type: 'integer'),
                    new OA\Property(property: 'quantity_free', type: 'integer'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date-time', nullable: true, description: 'Optional. When null, offer has no time limit (active indefinitely after start_date)'),
                    new OA\Property(property: 'is_active', type: 'boolean', description: 'Whether the offer is active'),
                    new OA\Property(property: 'product_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'products', type: 'array', description: 'For bundles: [{ product_id, quantity }] per product',
                        items: new OA\Items(type: 'object', properties: [
                            new OA\Property(property: 'product_id', type: 'integer'),
                            new OA\Property(property: 'quantity', type: 'integer'),
                        ])),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Offer updated successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function update(UpdateOfferRequest $request, Offer $offer): JsonResponse
    {
        $this->authorize('update', $offer);

        // Update products only when provided (nulls keep the current pivot).
        $offer = $this->promotionService->update(
            offer: $offer,
            data: $request->validated(),
            products: $request->has('products') ? $request->input('products') : null,
            productIds: $request->has('product_ids') ? $request->input('product_ids') : null,
        );

        $offer->load(['supplierCompany', 'products']);

        return $this->success(
            new OfferResource($offer),
            'Offer updated successfully'
        );
    }

    /**
     * Delete an offer.
     */
    #[OA\Delete(
        path: '/offers/{id}',
        summary: 'Delete offer',
        description: 'Delete an offer (soft delete, suppliers can only delete their own offers)',
        tags: ['Offers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Offer deleted successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(Offer $offer): JsonResponse
    {
        $this->authorize('delete', $offer);

        $this->promotionService->delete($offer);

        return $this->success(null, 'Offer deleted successfully');
    }
}
