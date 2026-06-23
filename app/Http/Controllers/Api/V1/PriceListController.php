<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\PriceLists\CreatePriceListRequest;
use App\Http\Requests\PriceLists\UpdatePriceListRequest;
use App\Http\Resources\PriceListResource;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Price Lists',
    description: 'Custom per-buyer pricing management. Suppliers can set negotiated prices for specific buyers and products. Custom prices have the highest priority in the pricing resolution engine (above wholesale and retail tiers).'
)]
class PriceListController extends BaseApiController
{
    #[OA\Get(
        path: '/price-lists',
        summary: 'List price list entries',
        description: 'Get price list entries. Suppliers see entries they created. Buyers see entries targeting them. Admins see all. Supports filtering by buyer, product, and active status.',
        tags: ['Price Lists'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'buyer_company_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by buyer company'),
            new OA\Parameter(name: 'product_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by product'),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
            new OA\Parameter(name: 'currently_valid', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter only entries that are currently valid (active + within date range)'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Price list entries retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = PriceList::with(['supplierCompany', 'buyerCompany', 'product.category', 'product.manufacturer']);

            if ($user->isAdmin()) {
                // Admin sees all
            } elseif ($user->isSupplier() || ($user->isSalesman() && $user->isSupplierSalesman())) {
                $query->where('supplier_company_id', $user->company_id);
            } else {
                $query->where('buyer_company_id', $user->company_id);
            }

            if ($request->has('buyer_company_id')) {
                $query->where('buyer_company_id', $request->buyer_company_id);
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->boolean('currently_valid', false)) {
                $query->currentlyValid();
            }

            [$entries, $pagination] = $this->paginateOrAll($query->latest(), $request);

            $additional = [
                'success' => true,
                'message' => 'Price list entries retrieved successfully',
            ];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return PriceListResource::collection($entries)
                ->additional($additional)
                ->response();
        } catch (\Exception $e) {
            Log::error('Price list index error', ['message' => $e->getMessage()]);

            return $this->error('Failed to retrieve price list entries: '.$e->getMessage(), null, 500);
        }
    }

    #[OA\Post(
        path: '/price-lists',
        summary: 'Create price list entry',
        description: 'Create a custom price for a specific buyer and product. Only the product\'s supplier or admin can create. Duplicate entries (same buyer + product + min_qty) are prevented.',
        tags: ['Price Lists'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['buyer_company_id', 'product_id', 'custom_price'],
                properties: [
                    new OA\Property(property: 'buyer_company_id', type: 'integer', example: 3),
                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                    new OA\Property(property: 'custom_price', type: 'number', format: 'float', example: 22.50),
                    new OA\Property(property: 'min_qty', type: 'integer', nullable: true, example: 10),
                    new OA\Property(property: 'valid_from', type: 'string', format: 'date', nullable: true, example: '2026-01-01'),
                    new OA\Property(property: 'valid_to', type: 'string', format: 'date', nullable: true, example: '2026-12-31'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Special rate for VIP buyer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Price list entry created successfully'),
            new OA\Response(response: 400, description: 'Validation error or product does not belong to supplier'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 409, description: 'Duplicate entry'),
        ]
    )]
    public function store(CreatePriceListRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $this->authorize('create', PriceList::class);

            $product = Product::findOrFail($request->product_id);

            if (! $user->isAdmin() && $product->supplier_company_id !== $user->company_id) {
                return $this->error('You can only create price list entries for your own products', null, 403);
            }

            $exists = PriceList::where('supplier_company_id', $product->supplier_company_id)
                ->where('buyer_company_id', $request->buyer_company_id)
                ->where('product_id', $request->product_id)
                ->where('min_qty', $request->min_qty)
                ->exists();

            if ($exists) {
                return $this->error('A price list entry for this buyer, product, and quantity already exists', null, 409);
            }

            $data = $request->validated();
            $data['supplier_company_id'] = $product->supplier_company_id;
            $data['is_active'] = $request->boolean('is_active', true);

            $entry = PriceList::create($data);
            $entry->load(['supplierCompany', 'buyerCompany', 'product.category', 'product.manufacturer']);

            return $this->success(
                new PriceListResource($entry),
                'Price list entry created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Price list create error', ['message' => $e->getMessage()]);

            return $this->error('Failed to create price list entry: '.$e->getMessage(), null, 500);
        }
    }

    #[OA\Get(
        path: '/price-lists/{id}',
        summary: 'Get price list entry',
        description: 'Get a specific price list entry with related buyer, supplier, and product details.',
        tags: ['Price Lists'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Price list entry retrieved successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $entry = PriceList::with(['supplierCompany', 'buyerCompany', 'product.category', 'product.manufacturer'])->findOrFail($id);
            $this->authorize('view', $entry);

            return $this->success(
                new PriceListResource($entry),
                'Price list entry retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Price list show error', ['message' => $e->getMessage()]);

            return $this->error('Failed to retrieve price list entry: '.$e->getMessage(), null, 500);
        }
    }

    #[OA\Put(
        path: '/price-lists/{id}',
        summary: 'Update price list entry',
        description: 'Update a custom price entry. Only the owning supplier or admin can update.',
        tags: ['Price Lists'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'custom_price', type: 'number', format: 'float'),
                    new OA\Property(property: 'min_qty', type: 'integer', nullable: true),
                    new OA\Property(property: 'valid_from', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'valid_to', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Price list entry updated successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(UpdatePriceListRequest $request, int $id): JsonResponse
    {
        try {
            $entry = PriceList::findOrFail($id);
            $this->authorize('update', $entry);

            $entry->update($request->validated());
            $entry->load(['supplierCompany', 'buyerCompany', 'product.category', 'product.manufacturer']);

            return $this->success(
                new PriceListResource($entry),
                'Price list entry updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Price list update error', ['message' => $e->getMessage()]);

            return $this->error('Failed to update price list entry: '.$e->getMessage(), null, 500);
        }
    }

    #[OA\Delete(
        path: '/price-lists/{id}',
        summary: 'Delete price list entry',
        description: 'Delete a custom price entry. Only the owning supplier or admin can delete.',
        tags: ['Price Lists'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Price list entry deleted successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $entry = PriceList::findOrFail($id);
            $this->authorize('delete', $entry);

            $entry->delete();

            return $this->success(null, 'Price list entry deleted successfully');
        } catch (\Exception $e) {
            Log::error('Price list delete error', ['message' => $e->getMessage()]);

            return $this->error('Failed to delete price list entry: '.$e->getMessage(), null, 500);
        }
    }

    #[OA\Get(
        path: '/price-lists/for-buyer/{buyerCompanyId}',
        summary: 'Get all prices for a specific buyer',
        description: 'Get all currently valid price list entries for a specific buyer. Useful for showing a buyer their negotiated prices. Only the supplier, the buyer themselves, or admin can view.',
        tags: ['Price Lists'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'buyerCompanyId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Buyer price list retrieved successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function forBuyer(Request $request, int $buyerCompanyId): JsonResponse
    {
        try {
            $user = auth()->user();

            $isSupplierSide = $user->isSupplier() || ($user->isSalesman() && $user->isSupplierSalesman());

            if (! $user->isAdmin() && $user->company_id !== $buyerCompanyId) {
                if (! $isSupplierSide) {
                    return $this->error('You can only view your own price list', null, 403);
                }
            }

            $query = PriceList::with(['supplierCompany', 'buyerCompany', 'product.category', 'product.manufacturer'])
                ->where('buyer_company_id', $buyerCompanyId)
                ->currentlyValid();

            if ($isSupplierSide) {
                $query->where('supplier_company_id', $user->company_id);
            }

            [$entries, $pagination] = $this->paginateOrAll($query->latest(), $request);

            $additional = [
                'success' => true,
                'message' => 'Buyer price list retrieved successfully',
            ];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return PriceListResource::collection($entries)
                ->additional($additional)
                ->response();
        } catch (\Exception $e) {
            Log::error('Price list for buyer error', ['message' => $e->getMessage()]);

            return $this->error('Failed to retrieve buyer price list: '.$e->getMessage(), null, 500);
        }
    }
}
