<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Users\RegistrationService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Authentication', description: 'User authentication endpoints')]
class AuthController extends BaseApiController
{
    /**
     * Register a new user.
     */
    #[OA\Post(
        path: '/auth/register',
        summary: 'Register a new buyer',
        description: 'Public self-registration. Creates a new buyer (pharmacy) account together with its own company and returns an authentication token. The role (buyer) and the company are assigned by the server — privileged roles and joining an existing company are not possible through this endpoint.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'company_name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+966500000000'),
                    new OA\Property(property: 'company_name', type: 'string', example: 'صيدلية المدينة', description: 'Name of the pharmacy/company created for this buyer'),
                    new OA\Property(property: 'company_phone', type: 'string', nullable: true, example: '+966112223333'),
                    new OA\Property(property: 'company_address', type: 'string', nullable: true, example: 'Riyadh, Al Narjis'),
                    new OA\Property(property: 'region_id', type: 'integer', nullable: true, example: 1, description: 'Region the pharmacy is located in (for delivery)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'User registered successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', type: 'object'),
                                new OA\Property(property: 'token', type: 'string', example: '1|xxxxxxxxxxxx'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function register(RegisterRequest $request, RegistrationService $registrationService): JsonResponse
    {
        $user = $registrationService->registerBuyer($request->validated());

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user->load(['company' => fn ($q) => $q->with(['categories', 'manufacturers'])])),
            'token' => $token,
        ], 'User registered successfully', 201);
    }

    /**
     * Login user and return token.
     */
    #[OA\Post(
        path: '/auth/login',
        summary: 'Login user',
        description: 'Authenticate user and return access token. Rate limited to 5 requests per minute.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', type: 'object'),
                                new OA\Property(property: 'token', type: 'string', example: '1|xxxxxxxxxxxx'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 429, description: 'Too many requests'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return $this->error('Invalid credentials', null, 401);
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->success([
                'user' => new UserResource($user->load(['company' => fn ($q) => $q->with(['categories', 'manufacturers'])])),
                'token' => $token,
            ], 'Login successful');
        } catch (\Exception $e) {
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Login failed. Please try again later.', null, 500);
        }
    }

    /**
     * Logout user (revoke current token).
     */
    #[OA\Post(
        path: '/auth/logout',
        summary: 'Logout user',
        description: 'Revoke the current authentication token',
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(): JsonResponse
    {
        $user = $this->currentUser();

        // Revoke the current token
        $user->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Get authenticated user.
     */
    #[OA\Get(
        path: '/auth/me',
        summary: 'Get authenticated user',
        description: 'Returns the currently authenticated user with company information',
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', type: 'object'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function me(): JsonResponse
    {
        $user = $this->currentUser()->load(['company' => fn ($q) => $q->with(['categories', 'manufacturers'])]);

        return $this->success(
            new UserResource($user)
        );
    }
}
