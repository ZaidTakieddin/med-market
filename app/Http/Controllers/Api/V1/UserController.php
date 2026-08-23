<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Users\UserManagementService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Users', description: 'Admin user management: list, create, inspect, update (role/company/password reset), and deactivate accounts.')]
class UserController extends BaseApiController
{
    public function __construct(
        private UserManagementService $userManagementService
    ) {}

    /**
     * List users.
     */
    #[OA\Get(
        path: '/users',
        summary: 'List users',
        description: 'Paginated list of all accounts with optional filters: `role`, `company_id`, and `search` (matches name, email, or phone). Admin only.',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string', enum: ['admin', 'supplier', 'buyer', 'driver', 'salesman'])),
            new OA\Parameter(name: 'company_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Matches name, email, or phone'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageAny', User::class);

        $query = User::query()->with('company');

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->input('company_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function (Builder $q) use ($search): void {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%");
            });
        }

        [$users, $pagination] = $this->paginateOrAll($query->orderBy('name'), $request);

        $additional = [
            'success' => true,
            'message' => 'Users retrieved successfully',
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return UserResource::collection($users)
            ->additional($additional)
            ->response();
    }

    /**
     * Create a user.
     */
    #[OA\Post(
        path: '/users',
        summary: 'Create a user',
        description: 'Create an account with any role. Company-requiring roles (supplier/buyer/driver/salesman) need `company_id`; admins have none.',
        tags: ['Users'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'supplier', 'buyer', 'driver', 'salesman']),
                    new OA\Property(property: 'company_id', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(CreateUserRequest $request): JsonResponse
    {
        $this->authorize('manageAny', User::class);

        $user = $this->userManagementService->create($request->validated());

        return $this->success(
            new UserResource($user->load('company')),
            'User created successfully',
            201
        );
    }

    /**
     * Get a user.
     */
    #[OA\Get(
        path: '/users/{id}',
        summary: 'Get user',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        $this->authorize('manageAny', User::class);

        return $this->success(
            new UserResource($user->load(['company', 'driverRegions'])),
            'User retrieved successfully'
        );
    }

    /**
     * Update a user.
     */
    #[OA\Put(
        path: '/users/{id}',
        summary: 'Update user',
        description: 'Update profile fields, reset the password (admin-set), or change role/company assignment. Demoting or deleting the last remaining admin is rejected with 422.',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'password', type: 'string', format: 'password', nullable: true, description: 'Set to reset; omit/empty to keep current password'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'supplier', 'buyer', 'driver', 'salesman']),
                    new OA\Property(property: 'company_id', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User updated successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('manageAny', User::class);

        $user = $this->userManagementService->update($user, $request->validated());

        return $this->success(
            new UserResource($user->load('company')),
            'User updated successfully'
        );
    }

    /**
     * Delete (soft) a user.
     */
    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Deactivate user',
        description: 'Soft-deletes the account (login tokens become unusable). Cannot delete yourself or the last remaining admin.',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User deleted successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function destroy(User $user): JsonResponse
    {
        $actor = $this->currentUser();
        $this->authorize('manageAny', User::class);

        // Soft delete keeps history intact for audits.
        $this->userManagementService->delete($actor, $user);

        return $this->success(null, 'User deleted successfully');
    }
}
