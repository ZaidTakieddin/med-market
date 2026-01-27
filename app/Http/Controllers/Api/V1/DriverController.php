<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Drivers\CreateDriverRequest;
use App\Http\Requests\Drivers\UpdateDriverRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use App\Models\Order;
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
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
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

            $drivers = $query->with('company')
                ->latest()
                ->paginate($request->get('per_page', 15));

            return UserResource::collection($drivers->items())
                ->additional([
                    'success' => true,
                    'message' => 'Drivers retrieved successfully',
                    'pagination' => [
                        'current_page' => $drivers->currentPage(),
                        'per_page' => $drivers->perPage(),
                        'total' => $drivers->total(),
                        'last_page' => $drivers->lastPage(),
                        'from' => $drivers->firstItem(),
                        'to' => $drivers->lastItem(),
                    ],
                ])
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
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
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
                ->with(['buyerCompany', 'supplierCompany', 'items.product']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->latest()->paginate($request->get('per_page', 15));

            return OrderResource::collection($orders->items())
                ->additional([
                    'success' => true,
                    'message' => 'Driver orders retrieved successfully',
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'per_page' => $orders->perPage(),
                        'total' => $orders->total(),
                        'last_page' => $orders->lastPage(),
                        'from' => $orders->firstItem(),
                        'to' => $orders->lastItem(),
                    ],
                ])
                ->response();
        } catch (\Exception $e) {
            Log::error('Get driver orders error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve driver orders: ' . $e->getMessage(), null, 500);
        }
    }
}

