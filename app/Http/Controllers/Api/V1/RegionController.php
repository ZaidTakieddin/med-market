<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Regions\CreateRegionRequest;
use App\Http\Requests\Regions\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Regions', description: 'Region management endpoints (Admin only)')]
class RegionController extends BaseApiController
{
    /**
     * List regions.
     */
    #[OA\Get(
        path: '/regions',
        summary: 'List regions',
        description: 'Get a paginated list of regions. Available to all authenticated users.',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'city_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by city ID'),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search in name or description'),
            new OA\Parameter(name: 'with_city', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Include city in response'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Regions retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        // Cache the unfiltered "all regions" dropdown fetch; flushed on any write
        // (CacheableReference). Filtered/paginated/with-city requests skip it.
        if (! $request->hasAny(['search', 'is_active', 'city_id', 'with_city']) && $this->wantsUnpaginated($request)) {
            $regions = Cache::remember(
                Region::referenceListCacheKey(),
                now()->addDay(),
                fn () => Region::query()->orderBy('sort_order')->orderBy('name')->get()
            );

            return RegionResource::collection($regions)
                ->additional(['success' => true, 'message' => 'Regions retrieved successfully'])
                ->response();
        }

        $query = Region::query();

        if ($request->boolean('with_city')) {
            $query->with('city');
        }

        // Filter by city
        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
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
                    ->orWhereLike('description', "%{$search}%");
            });
        }

        [$regions, $pagination] = $this->paginateOrAll(
            $query->orderBy('sort_order')->orderBy('name'),
            $request
        );

        $additional = [
            'success' => true,
            'message' => 'Regions retrieved successfully',
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return RegionResource::collection($regions)
            ->additional($additional)
            ->response();
    }

    /**
     * Create a new region.
     */
    #[OA\Post(
        path: '/regions',
        summary: 'Create region',
        description: 'Create a new region. Only admins can create regions.',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'city_id'],
                properties: [
                    new OA\Property(property: 'city_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Mazzeh'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true),
                    new OA\Property(property: 'sort_order', type: 'integer', default: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Region created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Only admins can create regions'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(CreateRegionRequest $request): JsonResponse
    {
        $this->authorize('create', Region::class);

        $region = Region::create($request->validated());

        return $this->success(
            new RegionResource($region->load('city')),
            'Region created successfully',
            201
        );
    }

    /**
     * Get a specific region.
     */
    #[OA\Get(
        path: '/regions/{id}',
        summary: 'Get region',
        description: 'Get details of a specific region',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'with_city', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Include city in response'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Region retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Region not found'),
        ]
    )]
    public function show(Request $request, Region $region): JsonResponse
    {
        if ($request->boolean('with_city')) {
            $region->load('city');
        }

        return $this->success(new RegionResource($region));
    }

    /**
     * Update a region.
     */
    #[OA\Put(
        path: '/regions/{id}',
        summary: 'Update region',
        description: 'Update region details. Only admins can update regions.',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'city_id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Region updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Region not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateRegionRequest $request, Region $region): JsonResponse
    {
        $this->authorize('update', $region);

        $region->update($request->validated());

        return $this->success(
            new RegionResource($region->load('city')),
            'Region updated successfully'
        );
    }

    /**
     * Delete a region.
     */
    #[OA\Delete(
        path: '/regions/{id}',
        summary: 'Delete region',
        description: 'Soft delete a region. Only admins can delete regions.',
        tags: ['Regions'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Region deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Region not found'),
        ]
    )]
    public function destroy(Region $region): JsonResponse
    {
        $this->authorize('delete', $region);

        $region->delete();

        return $this->success(null, 'Region deleted successfully');
    }
}
