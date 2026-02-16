<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Drivers\CreateDriverRequest;
use App\Http\Requests\Drivers\UpdateDriverRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\UserResource;
use App\Models\Order;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Drivers', description: 'Driver management endpoints')]
class DriverController extends BaseApiController
{
    /**
     * List drivers.
     */
    #[OA\Get(
        path: '/drivers',
        summary: 'List drivers',
        description: 'Get a paginated list of drivers. Suppliers see only their company drivers.',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Drivers retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isSupplier() && !$user->isAdmin()) {
                return $this->error('Only suppliers and admins can view drivers', null, 403);
            }

            $query = User::where('role', 'driver');

            // Suppliers see only their company drivers
            if ($user->isSupplier() && !$user->isAdmin()) {
                $query->where('company_id', $user->company_id);
            }

            [$drivers, $pagination] = $this->paginateOrAll(
                $query->with('company')->latest(),
                $request
            );

            $additional = [
                'success' => true,
                'message' => 'Drivers retrieved successfully',
            ];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return UserResource::collection($drivers)
                ->additional($additional)
                ->response();
        } catch (\Exception $e) {
            Log::error('Driver list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve drivers: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create a new driver.
     */
    #[OA\Post(
        path: '/drivers',
        summary: 'Create driver',
        description: 'Create a new driver (suppliers and admins only)',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Ahmed Ali'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ahmed@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Driver created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function store(CreateDriverRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isSupplier() && !$user->isAdmin()) {
                return $this->error('Only suppliers and admins can create drivers', null, 403);
            }

            $driver = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'company_id' => $user->company_id,
                'role' => 'driver',
            ]);

            return $this->success(
                new UserResource($driver->load('company')),
                'Driver created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Create driver error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create driver: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get a specific driver.
     */
    #[OA\Get(
        path: '/drivers/{id}',
        summary: 'Get driver',
        description: 'Get details of a specific driver',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Driver not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $driver = User::where('role', 'driver')->findOrFail($id);

            // Suppliers can only see their company drivers
            if ($user->isSupplier() && !$user->isAdmin() && $driver->company_id !== $user->company_id) {
                return $this->error('Unauthorized', null, 403);
            }

            $driver->load('company');

            return $this->success(new UserResource($driver));
        } catch (\Exception $e) {
            Log::error('Get driver error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve driver: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update a driver.
     */
    #[OA\Put(
        path: '/drivers/{id}',
        summary: 'Update driver',
        description: 'Update an existing driver',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Driver not found'),
        ]
    )]
    public function update(UpdateDriverRequest $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $driver = User::where('role', 'driver')->findOrFail($id);

            // Suppliers can only update their company drivers
            if ($user->isSupplier() && !$user->isAdmin() && $driver->company_id !== $user->company_id) {
                return $this->error('You can only update drivers from your company', null, 403);
            }

            $driver->update($request->validated());

            return $this->success(
                new UserResource($driver->load('company')),
                'Driver updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Update driver error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update driver: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete a driver.
     */
    #[OA\Delete(
        path: '/drivers/{id}',
        summary: 'Delete driver',
        description: 'Delete a driver (soft delete)',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Driver not found'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $driver = User::where('role', 'driver')->findOrFail($id);

            // Suppliers can only delete their company drivers
            if ($user->isSupplier() && !$user->isAdmin() && $driver->company_id !== $user->company_id) {
                return $this->error('You can only delete drivers from your company', null, 403);
            }

            $driver->delete();

            return $this->success(null, 'Driver deleted successfully');
        } catch (\Exception $e) {
            Log::error('Delete driver error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete driver: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get driver's assigned orders.
     */
    #[OA\Get(
        path: '/drivers/{id}/orders',
        summary: 'Get driver orders',
        description: 'Get all orders assigned to a specific driver',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
            new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Orders retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Driver not found'),
        ]
    )]
    public function orders(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $driver = User::where('role', 'driver')->findOrFail($id);

            // Suppliers can only see orders for their company drivers
            if ($user->isSupplier() && !$user->isAdmin() && $driver->company_id !== $user->company_id) {
                return $this->error('Unauthorized', null, 403);
            }

            $query = Order::where('driver_id', $id)
                ->with([
                    'buyerCompany.region.city',
                    'supplierCompany.categories',
                    'supplierCompany.manufacturers',
                    'items.product.category',
                    'items.product.manufacturer',
                    'items.product.supplierCompany',
                ]);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            [$orders, $pagination] = $this->paginateOrAll($query->latest(), $request);

            $additional = [
                'success' => true,
                'message' => 'Driver orders retrieved successfully',
            ];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return OrderResource::collection($orders)
                ->additional($additional)
                ->response();
        } catch (\Exception $e) {
            Log::error('Get driver orders error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve driver orders: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get driver's assigned regions.
     */
    #[OA\Get(
        path: '/drivers/{id}/regions',
        summary: 'Get driver regions',
        description: 'Get all regions assigned to a specific driver',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Regions retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Driver not found'),
        ]
    )]
    public function regions(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $driver = User::where('role', 'driver')->findOrFail($id);

            // Suppliers can only see regions for their company drivers
            if ($user->isSupplier() && !$user->isAdmin() && $driver->company_id !== $user->company_id) {
                return $this->error('Unauthorized', null, 403);
            }

            $regions = $driver->driverRegions()->with('city')->get();

            return $this->success(
                RegionResource::collection($regions),
                'Driver regions retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Get driver regions error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve driver regions: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Assign regions to driver.
     */
    #[OA\Post(
        path: '/drivers/{id}/regions',
        summary: 'Assign regions to driver',
        description: 'Assign regions to a driver. This replaces all existing region assignments. Only suppliers can assign regions to their own drivers.',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['region_ids'],
                properties: [
                    new OA\Property(
                        property: 'region_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'Array of region IDs'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Regions assigned successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Driver not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function assignRegions(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $driver = User::where('role', 'driver')->findOrFail($id);

            // Only suppliers can assign regions to their drivers
            if (!$user->isSupplier() && !$user->isAdmin()) {
                return $this->error('Only suppliers can assign regions to drivers', null, 403);
            }

            // Verify driver belongs to supplier's company
            if ($driver->company_id !== $user->company_id && !$user->isAdmin()) {
                return $this->error('You can only assign regions to your own drivers', null, 403);
            }

            $request->validate([
                'region_ids' => ['required', 'array'],
                'region_ids.*' => ['integer', 'exists:regions,id'],
            ]);

            // Sync regions (replace all existing assignments)
            $driver->driverRegions()->sync($request->region_ids);

            $regions = $driver->driverRegions()->with('city')->get();

            return $this->success(
                RegionResource::collection($regions),
                'Regions assigned successfully'
            );
        } catch (\Exception $e) {
            Log::error('Assign driver regions error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to assign regions: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove a region from driver.
     */
    #[OA\Delete(
        path: '/drivers/{id}/regions/{region_id}',
        summary: 'Remove region from driver',
        description: 'Remove a specific region from a driver. Only suppliers can remove regions from their own drivers.',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'region_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Region removed successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Driver or region not found'),
        ]
    )]
    public function removeRegion(int $id, int $region_id): JsonResponse
    {
        try {
            $user = auth()->user();
            $driver = User::where('role', 'driver')->findOrFail($id);

            // Only suppliers can remove regions from their drivers
            if (!$user->isSupplier() && !$user->isAdmin()) {
                return $this->error('Only suppliers can remove regions from drivers', null, 403);
            }

            // Verify driver belongs to supplier's company
            if ($driver->company_id !== $user->company_id && !$user->isAdmin()) {
                return $this->error('You can only remove regions from your own drivers', null, 403);
            }

            // Verify region exists
            Region::findOrFail($region_id);

            $driver->driverRegions()->detach($region_id);

            return $this->success(null, 'Region removed successfully');
        } catch (\Exception $e) {
            Log::error('Remove driver region error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to remove region: ' . $e->getMessage(), null, 500);
        }
    }
}

