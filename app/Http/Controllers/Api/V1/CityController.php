<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Cities\CreateCityRequest;
use App\Http\Requests\Cities\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Cities', description: 'City management endpoints (Admin only)')]
class CityController extends BaseApiController
{
    /**
     * List cities.
     */
    #[OA\Get(
            path: '/cities',
            summary: 'List cities',
            description: 'Get a paginated list of cities. Available to all authenticated users.',
            tags: ['Cities'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
                new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search in name or description'),
                new OA\Parameter(name: 'with_regions', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Include regions in response'),
                new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
                new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Cities retrieved successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )]
    public function index(Request $request): JsonResponse
    {
        try {
            $query = City::query();

            if ($request->boolean('with_regions')) {
                $query->with('regions');
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
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $cities = $query->orderBy('sort_order')->orderBy('name')->paginate($request->get('per_page', 15));

            return CityResource::collection($cities->items())
                ->additional([
                        'success' => true,
                        'message' => 'Cities retrieved successfully',
                        'pagination' => [
                            'current_page' => $cities->currentPage(),
                            'per_page' => $cities->perPage(),
                            'total' => $cities->total(),
                            'last_page' => $cities->lastPage(),
                            'from' => $cities->firstItem(),
                            'to' => $cities->lastItem(),
                        ],
                    ])
                ->response();
        } catch (\Exception $e) {
            Log::error('City list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve cities: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create a new city.
     */
    #[OA\Post(
            path: '/cities',
            summary: 'Create city',
            description: 'Create a new city. Only admins can create cities.',
            tags: ['Cities'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Damascus'),
                        new OA\Property(property: 'slug', type: 'string', nullable: true),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'is_active', type: 'boolean', default: true),
                        new OA\Property(property: 'sort_order', type: 'integer', default: 0),
                    ]
                )
            ),
            responses: [
                new OA\Response(response: 201, description: 'City created successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Only admins can create cities'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )]
    public function store(CreateCityRequest $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return $this->error('Only admins can create cities.', null, 403);
        }

        $city = City::create($request->validated());

        return $this->success(
            new CityResource($city),
            'City created successfully',
            201
        );
    }

    /**
     * Get a specific city.
     */
    #[OA\Get(
            path: '/cities/{id}',
            summary: 'Get city',
            description: 'Get details of a specific city',
            tags: ['Cities'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
                new OA\Parameter(name: 'with_regions', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Include regions in response'),
            ],
            responses: [
                new OA\Response(response: 200, description: 'City retrieved successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'City not found'),
            ]
        )]
    public function show(Request $request, City $city): JsonResponse
    {
        if ($request->boolean('with_regions')) {
            $city->load('regions');
        }
        return $this->success(new CityResource($city));
    }

    /**
     * Update a city.
     */
    #[OA\Put(
            path: '/cities/{id}',
            summary: 'Update city',
            description: 'Update city details. Only admins can update cities.',
            tags: ['Cities'],
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
                        new OA\Property(property: 'is_active', type: 'boolean'),
                        new OA\Property(property: 'sort_order', type: 'integer'),
                    ]
                )
            ),
            responses: [
                new OA\Response(response: 200, description: 'City updated successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden'),
                new OA\Response(response: 404, description: 'City not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )]
    public function update(UpdateCityRequest $request, City $city): JsonResponse
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return $this->error('Only admins can update cities.', null, 403);
        }

        $city->update($request->validated());

        return $this->success(
            new CityResource($city),
            'City updated successfully'
        );
    }

    /**
     * Delete a city.
     */
    #[OA\Delete(
            path: '/cities/{id}',
            summary: 'Delete city',
            description: 'Soft delete a city. Only admins can delete cities.',
            tags: ['Cities'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ],
            responses: [
                new OA\Response(response: 200, description: 'City deleted successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden'),
                new OA\Response(response: 404, description: 'City not found'),
            ]
        )]
    public function destroy(City $city): JsonResponse
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return $this->error('Only admins can delete cities.', null, 403);
        }

        $city->delete();

        return $this->success(null, 'City deleted successfully');
    }
}
