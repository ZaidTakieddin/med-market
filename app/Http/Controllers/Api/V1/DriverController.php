<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\OrderStatus;
use App\Constants\RegionPlanType;
use App\Constants\UserRole;
use App\Domain\Deliveries\DriverLocationService;
use App\Domain\Deliveries\DriverRegionPlanService;
use App\Domain\Deliveries\DriverService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Drivers\AssignDriverRegionsRequest;
use App\Http\Requests\Drivers\CreateDriverRequest;
use App\Http\Requests\Drivers\SetDriverHolidaysRequest;
use App\Http\Requests\Drivers\SetDriverRegionPlanRequest;
use App\Http\Requests\Drivers\UpdateDriverRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\UserResource;
use App\Models\Order;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Drivers', description: 'Driver management endpoints')]
class DriverController extends BaseApiController
{
    public function __construct(
        private DriverRegionPlanService $regionPlanService,
        private DriverService $driverService,
        private DriverLocationService $locationService
    ) {}

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
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewDrivers', User::class);

        $user = $this->currentUser();

        $query = User::where('role', UserRole::DRIVER);

        // Suppliers see only their company drivers
        if ($user->isSupplier() && ! $user->isAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        [$drivers, $pagination] = $this->paginateOrAll(
            $this->withOrderCounts($query->with('company'))->latest(),
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
                    new OA\Property(property: 'phone', type: 'string', example: '+966501234567', description: 'Optional phone number'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'work_start_time', type: 'string', example: '08:00', description: 'Daily shift start time (HH:MM)'),
                    new OA\Property(property: 'work_end_time', type: 'string', example: '17:00', description: 'Daily shift end time (HH:MM)'),
                    new OA\Property(property: 'employment_start_date', type: 'string', format: 'date', example: '2026-01-01', description: 'Date the driver started working'),
                    new OA\Property(property: 'employment_end_date', type: 'string', format: 'date', example: '2026-12-31', description: 'Driver\'s last day at work'),
                    new OA\Property(property: 'driver_status', type: 'string', enum: ['available', 'on_delivery', 'off'], example: 'available', description: 'Driver availability status'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Driver created successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(CreateDriverRequest $request): JsonResponse
    {
        $this->authorize('createDriver', User::class);

        $user = $this->currentUser();

        // Account creation, password hashing and role assignment live in the domain service.
        $driver = $this->driverService->create($user, $request->validated());

        return $this->success(
            new UserResource($this->withOrderCounts(User::whereKey($driver->id))->with('company')->first()),
            'Driver created successfully',
            201
        );
    }

    /**
     * Get a specific driver.
     */
    #[OA\Get(
        path: '/drivers/{driver}',
        summary: 'Get driver',
        description: 'Get details of a specific driver',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('viewDriver', $driver);

        $driver->load('company')->loadCount($this->orderCountRelations());

        return $this->success(new UserResource($driver));
    }

    /**
     * Update a driver.
     */
    #[OA\Put(
        path: '/drivers/{driver}',
        summary: 'Update driver',
        description: 'Update an existing driver',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver updated successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function update(UpdateDriverRequest $request, User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('manageDriver', $driver);

        // Passwords can never be set through this path; profile fields only.
        $driver = $this->driverService->update($driver, $request->validated());

        $driver->load('company')->loadCount($this->orderCountRelations());

        return $this->success(
            new UserResource($driver),
            'Driver updated successfully'
        );
    }

    /**
     * Delete a driver.
     */
    #[OA\Delete(
        path: '/drivers/{driver}',
        summary: 'Delete driver',
        description: 'Delete a driver (soft delete)',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver deleted successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('manageDriver', $driver);

        $this->driverService->delete($driver);

        return $this->success(null, 'Driver deleted successfully');
    }

    /**
     * Record the authenticated driver's GPS position.
     */
    #[OA\Post(
        path: '/drivers/location',
        summary: 'Ping driver location',
        description: "Record the authenticated driver's current GPS coordinates. Called periodically by the driver app; clients then poll GET /drivers/{driver}/location for live tracking.",
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['latitude', 'longitude'],
                properties: [
                    new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 33.5138, description: 'Latitude (-90 to 90)'),
                    new OA\Property(property: 'longitude', type: 'number', format: 'float', example: 36.2765, description: 'Longitude (-180 to 180)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Location recorded successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function pingLocation(Request $request): JsonResponse
    {
        $user = $this->currentUser();

        if (! $user->isDriver()) {
            return $this->error('Only drivers can record locations', null, 403);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $this->locationService->ping($user, (float) $data['latitude'], (float) $data['longitude']);

        return $this->success(null, 'Location recorded successfully');
    }

    /**
     * A driver's latest known location.
     */
    #[OA\Get(
        path: '/drivers/{driver}/location',
        summary: "Get driver's latest location",
        description: 'The most recent GPS ping of the driver (data may be null when they never reported a position). Owner supplier or admin only.',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'driver', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Driver user ID'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Latest location returned'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function latestLocation(User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('viewDriver', $driver);

        return $this->success(
            $this->locationService->latest($driver),
            'Latest location retrieved successfully'
        );
    }

    /**
     * Get driver's assigned orders.
     */
    #[OA\Get(
        path: '/drivers/{driver}/orders',
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
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function orders(Request $request, User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('viewDriver', $driver);

        $query = Order::where('driver_id', $driver->id)
            ->with([
                'buyerCompany.region.city',
                'supplierCompany.categories',
                'supplierCompany.manufacturers',
                'driver',
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
    }

    /**
     * Get driver's region plan.
     */
    #[OA\Get(
        path: '/drivers/{driver}/regions',
        summary: 'Get driver region plan',
        description: 'Get a driver\'s region plan: the plan type (fixed or daily), holiday weekdays, and either the fixed regions covered every day or the per-weekday schedule.',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Region plan retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function regions(User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('viewDriver', $driver);

        return $this->success(
            $this->buildPlanResponse($driver),
            'Driver region plan retrieved successfully'
        );
    }

    /**
     * Set a driver's region plan (fixed or daily).
     */
    #[OA\Post(
        path: '/drivers/{driver}/region-plan',
        summary: 'Set driver region plan',
        description: 'Set a driver\'s region plan. For a `fixed` plan provide `region_ids` (one or more regions covered every day). For a `daily` plan provide `schedule`, a map of weekday to region id (one region per weekday). Weekdays marked as the driver\'s holidays cannot be assigned a region. Replaces any existing assignment. Only suppliers can manage their own drivers.',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['plan_type'],
                properties: [
                    new OA\Property(property: 'plan_type', type: 'string', enum: ['fixed', 'daily'], example: 'daily'),
                    new OA\Property(
                        property: 'region_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3],
                        description: 'Required for a fixed plan. Regions covered every day.'
                    ),
                    new OA\Property(
                        property: 'schedule',
                        type: 'object',
                        example: ['saturday' => 1, 'sunday' => 2, 'monday' => 3],
                        description: 'Required for a daily plan. Map of weekday (saturday..friday) to region id.',
                        additionalProperties: new OA\AdditionalProperties(type: 'integer')
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Region plan saved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function setRegionPlan(SetDriverRegionPlanRequest $request, User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('manageDriver', $driver);

        $validated = $request->validated();

        if ($validated['plan_type'] === RegionPlanType::FIXED) {
            $driver = $this->regionPlanService->setFixedPlan($driver, $validated['region_ids']);
        } else {
            $driver = $this->regionPlanService->setDailyPlan($driver, $validated['schedule']);
        }

        return $this->success(
            $this->buildPlanResponse($driver),
            'Region plan saved successfully'
        );
    }

    /**
     * Set a driver's holiday weekdays.
     */
    #[OA\Put(
        path: '/drivers/{driver}/holidays',
        summary: 'Set driver holidays',
        description: 'Set the weekdays a driver is off. A holiday weekday cannot be assigned a region in a daily plan; any region already assigned to a newly added holiday weekday is removed automatically. Pass an empty array to clear all holidays. Only suppliers can manage their own drivers.',
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['holidays'],
                properties: [
                    new OA\Property(
                        property: 'holidays',
                        type: 'array',
                        items: new OA\Items(type: 'string', enum: ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                        example: ['friday'],
                        description: 'Weekdays the driver is off.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Holidays saved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function setHolidays(SetDriverHolidaysRequest $request, User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('manageDriver', $driver);

        $driver = $this->regionPlanService->setHolidays($driver, $request->validated()['holidays']);

        return $this->success(
            $this->buildPlanResponse($driver),
            'Holidays saved successfully'
        );
    }

    /**
     * Assign a fixed region plan to a driver (backward-compatible shortcut).
     */
    #[OA\Post(
        path: '/drivers/{driver}/regions',
        summary: 'Assign regions to driver (fixed plan)',
        description: 'Assign regions to a driver as a fixed plan (covered every day). This replaces all existing region assignments and sets the plan type to fixed. Prefer POST /drivers/{driver}/region-plan for daily plans. Only suppliers can assign regions to their own drivers.',
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
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function assignRegions(AssignDriverRegionsRequest $request, User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('manageDriver', $driver);

        $driver = $this->regionPlanService->setFixedPlan($driver, $request->validated()['region_ids']);

        return $this->success(
            $this->buildPlanResponse($driver),
            'Regions assigned successfully'
        );
    }

    /**
     * Remove a region from driver.
     */
    #[OA\Delete(
        path: '/drivers/{driver}/regions/{region_id}',
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
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function removeRegion(User $driver, int $region_id): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('manageDriver', $driver);

        // Verify region exists
        Region::findOrFail($region_id);

        $driver->driverRegions()->detach($region_id);

        return $this->success(null, 'Region removed successfully');
    }

    /**
     * Eager-load delivered and total assigned order counts onto a driver query.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function withOrderCounts(Builder $query): Builder
    {
        return $query->withCount($this->orderCountRelations());
    }

    /**
     * Count relations exposing delivered vs total assigned orders for a driver.
     *
     * @return array<int|string, string|\Closure>
     */
    private function orderCountRelations(): array
    {
        return [
            'driverOrders as total_orders_count',
            'driverOrders as delivered_orders_count' => fn (Builder $query): Builder => $query->where('status', OrderStatus::DELIVERED),
        ];
    }

    /**
     * Shape a driver's region plan for the API: plan type, holidays, and either the
     * fixed regions or the per-weekday schedule.
     *
     * @return array<string, mixed>
     */
    private function buildPlanResponse(User $driver): array
    {
        $plan = $this->regionPlanService->getPlan($driver);

        $response = [
            'planType' => $plan['planType'],
            'holidays' => $plan['holidays'],
        ];

        if ($plan['planType'] === RegionPlanType::DAILY) {
            $response['schedule'] = array_map(
                fn ($region) => $region !== null ? new RegionResource($region) : null,
                $plan['schedule']
            );
        } else {
            $response['regions'] = RegionResource::collection($plan['regions']);
        }

        return $response;
    }

    /**
     * Set a driver's availability status.
     */
    #[OA\Post(
        path: '/drivers/{driver}/status',
        summary: 'Set driver availability status',
        description: "Update a driver's availability: available, on_delivery or off. Owner supplier or admin only.",
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'driver', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['driver_status'], properties: [
            new OA\Property(property: 'driver_status', type: 'string', enum: ['available', 'on_delivery', 'off']),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Status updated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function setStatus(Request $request, User $driver): JsonResponse
    {
        if (! $driver->isDriver()) {
            abort(404);
        }

        $this->authorize('manageDriver', $driver);

        $data = $request->validate([
            'driver_status' => ['required', 'string'],
        ]);

        try {
            $driver = $this->driverService->setStatus($driver, (string) $data['driver_status']);
        } catch (\App\Exceptions\DomainException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(
            ['id' => $driver->id, 'driverStatus' => $driver->driver_status],
            'Driver status updated successfully'
        );
    }

    /**
     * List available drivers (status + capacity aware).
     */
    #[OA\Get(
        path: '/drivers/available',
        summary: 'List available drivers',
        description: "Drivers whose status is `available` and below their max concurrent-delivery capacity, optionally filtered by `region_id`. Suppliers see their own drivers; admins see all companies'.",
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'region_id', in: 'query', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Available drivers retrieved'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function available(Request $request): JsonResponse
    {
        $this->authorize('viewDrivers', User::class);

        $regionId = $request->filled('region_id') ? (int) $request->region_id : null;
        $drivers = $this->driverService->availableFor($this->currentUser(), $regionId);

        return $this->success(
            \App\Http\Resources\UserResource::collection($drivers)->resolve(),
            'Available drivers retrieved successfully'
        );
    }

    /**
     * Suggest the best driver for an order.
     */
    #[OA\Get(
        path: '/orders/{order}/suggested-driver',
        summary: 'Suggest a driver for an order',
        description: "Least-loaded available driver of the supplying company covering the buyer's region (when known). For assignment decisions; does not assign.",
        tags: ['Drivers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Suggestion returned (data may be null when no candidate exists)'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function suggestForOrder(\App\Models\Order $order): JsonResponse
    {
        $user = $this->currentUser();
        $this->authorize('assignDriver', $order);
        $order->load('buyerCompany');

        return $this->success(
            ['driver' => ($suggested = $this->driverService->suggestFor($order, $user)) !== null
                ? new UserResource($suggested->load('company'))
                : null],
            'Suggested driver retrieved successfully'
        );
    }
}
