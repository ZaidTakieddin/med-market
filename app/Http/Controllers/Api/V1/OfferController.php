<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\OfferType;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Offers\CreateOfferRequest;
use App\Http\Requests\Offers\UpdateOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Offers', description: 'Product offers and promotions management endpoints')]
class OfferController extends BaseApiController
{
    /**
     * List offers.
     */
    #[OA\Get(
        path: '/offers',
        summary: 'List offers',
        description: 'Get a paginated list of offers. Suppliers see only their offers, buyers see all active offers.',
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
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Offer::with('supplierCompany:id,name,type');

            // Suppliers see only their offers
            if ($user->isSupplier()) {
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
        } catch (\Throwable $e) {
            Log::error('Offer list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->error('Failed to retrieve offers: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create a new offer.
     */
    #[OA\Post(
        path: '/offers',
        summary: 'Create offer',
        description: 'Create a new product offer or promotion (suppliers only)',
        tags: ['Offers'],
        security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'offer_type', 'start_date', 'product_ids'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Buy 2 Get 1 Free'),
                    new OA\Property(property: 'description', type: 'string', example: 'Special promotion'),
                    new OA\Property(property: 'offer_type', type: 'string', enum: ['buy_x_get_y', 'percentage_discount', 'fixed_discount', 'bundle']),
                    new OA\Property(property: 'discount_value', type: 'number', format: 'float'),
                    new OA\Property(property: 'discount_type', type: 'string', enum: ['percentage', 'fixed']),
                    new OA\Property(property: 'quantity_required', type: 'integer'),
                    new OA\Property(property: 'quantity_free', type: 'integer'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date-time', nullable: true, description: 'Optional. When omitted, offer has no time limit (active indefinitely after start_date)'),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true, description: 'Whether the offer is active. Defaults to true.'),
                    new OA\Property(property: 'product_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Offer created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function store(CreateOfferRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isSupplier()) {
                return $this->error('Only suppliers can create offers', null, 403);
            }

            $offer = Offer::create([
                'supplier_company_id' => $user->company_id,
                'name' => $request->name,
                'description' => $request->description,
                'offer_type' => $request->offer_type,
                'discount_value' => $request->discount_value,
                'quantity_required' => $request->quantity_required,
                'quantity_free' => $request->quantity_free,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->is_active ?? true,
            ]);

            // Attach products
            if ($request->has('product_ids') && !empty($request->product_ids)) {
                $offer->products()->attach($request->product_ids);
            }

            $offer->load(['supplierCompany', 'products']);

            return $this->success(
                new OfferResource($offer),
                'Offer created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Create offer error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create offer: ' . $e->getMessage(), null, 500);
        }
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
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Offer not found'),
        ]
    )]
    public function show(Offer $offer): JsonResponse
    {
        try {
            $offer->load(['supplierCompany', 'products']);

            return $this->success(new OfferResource($offer));
        } catch (\Exception $e) {
            Log::error('Get offer error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve offer: ' . $e->getMessage(), null, 500);
        }
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
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Offer updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Offer not found'),
        ]
    )]
    public function update(UpdateOfferRequest $request, Offer $offer): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($offer->supplier_company_id !== $user->company_id && !$user->isAdmin()) {
                return $this->error('You can only update your own offers', null, 403);
            }

            $offer->update($request->validated());

            // Update products if provided
            if ($request->has('product_ids')) {
                $offer->products()->sync($request->product_ids);
            }

            $offer->load(['supplierCompany', 'products']);

            return $this->success(
                new OfferResource($offer),
                'Offer updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Update offer error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update offer: ' . $e->getMessage(), null, 500);
        }
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
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Offer not found'),
        ]
    )]
    public function destroy(Offer $offer): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($offer->supplier_company_id !== $user->company_id && !$user->isAdmin()) {
                return $this->error('You can only delete your own offers', null, 403);
            }

            $offer->delete();

            return $this->success(null, 'Offer deleted successfully');
        } catch (\Exception $e) {
            Log::error('Delete offer error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete offer: ' . $e->getMessage(), null, 500);
        }
    }
}

