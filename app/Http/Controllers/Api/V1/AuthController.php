<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Authentication', description: 'User authentication endpoints')]
class AuthController extends BaseApiController
{
    /**
     * Register a new user.
     */
    #[OA\Post(
            path: '/api/v1/auth/register',
            summary: 'Register a new user',
            description: 'Creates a new user account and returns an authentication token',
            tags: ['Authentication'],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name', 'email', 'password', 'password_confirmation'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                        new OA\Property(property: 'company_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'supplier', 'buyer', 'driver'], example: 'buyer'),
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
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'company_id' => $request->company_id,
            'role' => $request->role ?? 'buyer',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => $user->load('company'),
            'token' => $token,
        ], 'User registered successfully', 201);
    }

    /**
     * Login user and return token.
     */
    #[OA\Post(
            path: '/api/v1/auth/login',
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
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials', null, 401);
        }

        // Revoke all existing tokens (optional - for single device login)
        // $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => $user->load('company'),
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Logout user (revoke current token).
     */
    #[OA\Post(
            path: '/api/v1/auth/logout',
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
        $user = auth()->user();

        if ($user) {
            // Revoke the current token
            $user->currentAccessToken()->delete();
        }

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Get authenticated user.
     */
    #[OA\Get(
            path: '/api/v1/auth/me',
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
        $user = auth()->user()->load('company');

        return $this->success([
            'user' => $user,
        ]);
    }
}

