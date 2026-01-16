<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Companies\CreateCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Companies', description: 'Company management endpoints')]
class CompanyController extends BaseApiController
{
    /**
     * List companies.
     */
    #[OA\Get(
            path: '/companies',
            summary: 'List companies',
            description: 'Get a paginated list of companies',
            tags: ['Companies'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['supplier', 'buyer'])),
                new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
                new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
                new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Companies retrieved successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )]
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Company::query();

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $companies = $query->latest()->paginate($request->get('per_page', 15));

            // Prevent circular references (no relationships loaded, but hide them just in case)
            $companies->getCollection()->each(function ($company) {
                $company->makeHidden(['users', 'products', 'buyerOrders', 'supplierOrders']);
            });

            return $this->paginated($companies, 'Companies retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Company list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve companies: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create a new company.
     */
    #[OA\Post(
            path: '/companies',
            summary: 'Create company',
            description: 'Create a new company. Only admins can create companies.',
            tags: ['Companies'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name', 'type'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'ABC Pharmacy'),
                        new OA\Property(property: 'type', type: 'string', enum: ['supplier', 'buyer'], example: 'buyer'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                        new OA\Property(property: 'phone', type: 'string', nullable: true),
                        new OA\Property(property: 'address', type: 'string', nullable: true),
                        new OA\Property(property: 'tax_id', type: 'string', nullable: true),
                        new OA\Property(property: 'registration_number', type: 'string', nullable: true),
                        new OA\Property(property: 'is_active', type: 'boolean', default: true),
                    ]
                )
            ),
            responses: [
                new OA\Response(response: 201, description: 'Company created successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Only admins can create companies'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )]
    public function store(CreateCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return $this->success([
            'company' => $company,
        ], 'Company created successfully', 201);
    }

    /**
     * Get a specific company.
     */
    #[OA\Get(
            path: '/companies/{id}',
            summary: 'Get company',
            description: 'Get details of a specific company',
            tags: ['Companies'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Company retrieved successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Company not found'),
            ]
        )]
    public function show(Company $company): JsonResponse
    {
        return $this->success([
            'company' => $company,
        ]);
    }

    /**
     * Update a company.
     */
    #[OA\Put(
            path: '/companies/{id}',
            summary: 'Update company',
            description: 'Update company details. Only admins can update companies.',
            tags: ['Companies'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                        new OA\Property(property: 'phone', type: 'string', nullable: true),
                        new OA\Property(property: 'address', type: 'string', nullable: true),
                        new OA\Property(property: 'tax_id', type: 'string', nullable: true),
                        new OA\Property(property: 'registration_number', type: 'string', nullable: true),
                        new OA\Property(property: 'is_active', type: 'boolean'),
                    ]
                )
            ),
            responses: [
                new OA\Response(response: 200, description: 'Company updated successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden'),
                new OA\Response(response: 404, description: 'Company not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )]
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $company->update($request->validated());

        return $this->success([
            'company' => $company,
        ], 'Company updated successfully');
    }

    /**
     * Delete a company.
     */
    #[OA\Delete(
            path: '/companies/{id}',
            summary: 'Delete company',
            description: 'Soft delete a company. Only admins can delete companies.',
            tags: ['Companies'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Company deleted successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden'),
                new OA\Response(response: 404, description: 'Company not found'),
            ]
        )]
    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $company->delete();

        return $this->success(null, 'Company deleted successfully');
    }
}