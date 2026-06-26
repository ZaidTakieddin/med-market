<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateCompanyRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Profile', description: 'User profile management endpoints')]
class ProfileController extends BaseApiController
{
    /**
     * Get current user profile.
     */
    #[OA\Get(
        path: '/profile',
        summary: 'Get profile',
        description: 'Get the authenticated user\'s profile information',
        tags: ['Profile'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Profile retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(): JsonResponse
    {
        try {
            $user = $this->currentUser()->load(['company' => fn ($q) => $q->with(['categories', 'manufacturers'])]);

            return $this->success(new UserResource($user));
        } catch (\Exception $e) {
            Log::error('Get profile error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to retrieve profile. Please try again later.', null, 500);
        }
    }

    /**
     * Update current user profile.
     */
    #[OA\Put(
        path: '/profile',
        summary: 'Update profile',
        description: 'Update the authenticated user\'s profile information',
        tags: ['Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $user->update($request->validated());

            return $this->success([
                'user' => new UserResource($user->load(['company' => fn ($q) => $q->with(['categories', 'manufacturers'])])),
            ], 'Profile updated successfully');
        } catch (\Exception $e) {
            Log::error('Update profile error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to update profile. Please try again later.', null, 500);
        }
    }

    /**
     * Change password.
     */
    #[OA\Put(
        path: '/profile/password',
        summary: 'Change password',
        description: 'Change the authenticated user\'s password',
        tags: ['Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();

            if (! Hash::check($request->current_password, $user->password)) {
                return $this->error('Current password is incorrect', null, 422);
            }

            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return $this->success(null, 'Password changed successfully');
        } catch (\Exception $e) {
            Log::error('Change password error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to change password. Please try again later.', null, 500);
        }
    }

    /**
     * Update company information (if user is company admin).
     */
    #[OA\Put(
        path: '/profile/company',
        summary: 'Update company',
        description: 'Update company information (only for company admins)',
        tags: ['Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'address', type: 'string', description: 'Legacy address field'),
                    new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true, example: 24.7136, description: 'Latitude coordinate (-90 to 90)'),
                    new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true, example: 46.6753, description: 'Longitude coordinate (-180 to 180)'),
                    new OA\Property(property: 'region_id', type: 'integer', nullable: true, example: 1, description: 'Region ID (from regions table)'),
                    new OA\Property(property: 'address_line', type: 'string', nullable: true, example: 'King Fahd Road, Building 123, Floor 5'),
                    new OA\Property(property: 'tax_id', type: 'string', nullable: true),
                    new OA\Property(property: 'registration_number', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Company updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateCompany(UpdateCompanyRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();

            if (! $user->isAdmin() && $user->role !== 'supplier' && $user->role !== 'buyer' && $user->role !== 'salesman') {
                return $this->error('You do not have permission to update company information', null, 403);
            }

            $company = $user->company;
            if (! $company) {
                return $this->error('User does not belong to a company', null, 404);
            }

            $company->update($request->validated());
            $company->load(['categories', 'manufacturers']);

            return $this->success(
                new CompanyResource($company),
                'Company updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Update company error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to update company. Please try again later.', null, 500);
        }
    }
}
