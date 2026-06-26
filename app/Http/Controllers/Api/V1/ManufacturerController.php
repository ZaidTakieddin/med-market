<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Manufacturers\CreateManufacturerRequest;
use App\Http\Requests\Manufacturers\UpdateManufacturerRequest;
use App\Http\Resources\ManufacturerResource;
use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Manufacturers', description: 'Manufacturer management endpoints (Admin only)')]
class ManufacturerController extends BaseApiController
{
    /**
     * List manufacturers.
     */
    #[OA\Get(
        path: '/manufacturers',
        summary: 'List manufacturers',
        description: 'Get a paginated list of manufacturers. Available to all authenticated users for product creation.',
        tags: ['Manufacturers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search in name, country, or description'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Manufacturers retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        // The common "load every manufacturer" dropdown fetch is cached and flushed on
        // any manufacturer write (CacheableReference). Filtered/paginated requests skip it.
        if (! $request->hasAny(['search', 'is_active']) && $this->wantsUnpaginated($request)) {
            $manufacturers = Cache::remember(
                Manufacturer::referenceListCacheKey(),
                now()->addDay(),
                fn () => Manufacturer::query()->orderBy('sort_order')->orderBy('name')->get()
            );

            return ManufacturerResource::collection($manufacturers)
                ->additional(['success' => true, 'message' => 'Manufacturers retrieved successfully'])
                ->response();
        }

        $query = Manufacturer::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('country', "%{$search}%")
                    ->orWhereLike('description', "%{$search}%");
            });
        }

        [$manufacturers, $pagination] = $this->paginateOrAll(
            $query->orderBy('sort_order')->orderBy('name'),
            $request
        );

        $additional = [
            'success' => true,
            'message' => 'Manufacturers retrieved successfully',
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return ManufacturerResource::collection($manufacturers)
            ->additional($additional)
            ->response();
    }

    /**
     * Create a new manufacturer.
     */
    #[OA\Post(
        path: '/manufacturers',
        summary: 'Create manufacturer',
        description: 'Create a new manufacturer. Only admins can create manufacturers.',
        tags: ['Manufacturers'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'شركة الأدوية الوطنية'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'country', type: 'string', nullable: true),
                    new OA\Property(property: 'website', type: 'string', format: 'url', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true),
                    new OA\Property(property: 'sort_order', type: 'integer', default: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Manufacturer created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only admins can create manufacturers'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(CreateManufacturerRequest $request): JsonResponse
    {
        $user = $this->currentUser();
        if (! $user->isAdmin()) {
            return $this->error('Only admins can create manufacturers.', null, 403);
        }

        $manufacturer = Manufacturer::create($request->validated());

        return $this->success(
            new ManufacturerResource($manufacturer),
            'Manufacturer created successfully',
            201
        );
    }

    /**
     * Get a specific manufacturer.
     */
    #[OA\Get(
        path: '/manufacturers/{id}',
        summary: 'Get manufacturer',
        description: 'Get details of a specific manufacturer',
        tags: ['Manufacturers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Manufacturer retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Manufacturer not found'),
        ]
    )]
    public function show(Manufacturer $manufacturer): JsonResponse
    {
        return $this->success(new ManufacturerResource($manufacturer));
    }

    /**
     * Update a manufacturer.
     */
    #[OA\Put(
        path: '/manufacturers/{id}',
        summary: 'Update manufacturer',
        description: 'Update manufacturer details. Only admins can update manufacturers.',
        tags: ['Manufacturers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'country', type: 'string', nullable: true),
                    new OA\Property(property: 'website', type: 'string', format: 'url', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Manufacturer updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Manufacturer not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateManufacturerRequest $request, Manufacturer $manufacturer): JsonResponse
    {
        $user = $this->currentUser();
        if (! $user->isAdmin()) {
            return $this->error('Only admins can update manufacturers.', null, 403);
        }

        $manufacturer->update($request->validated());

        return $this->success(
            new ManufacturerResource($manufacturer),
            'Manufacturer updated successfully'
        );
    }

    /**
     * Delete a manufacturer.
     */
    #[OA\Delete(
        path: '/manufacturers/{id}',
        summary: 'Delete manufacturer',
        description: 'Soft delete a manufacturer. Only admins can delete manufacturers.',
        tags: ['Manufacturers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Manufacturer deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Manufacturer not found'),
        ]
    )]
    public function destroy(Manufacturer $manufacturer): JsonResponse
    {
        $user = $this->currentUser();
        if (! $user->isAdmin()) {
            return $this->error('Only admins can delete manufacturers.', null, 403);
        }

        $manufacturer->delete();

        return $this->success(null, 'Manufacturer deleted successfully');
    }
}
