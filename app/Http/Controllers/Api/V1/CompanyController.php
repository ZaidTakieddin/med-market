<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Companies\CreateCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
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
                new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter suppliers by category ID'),
                new OA\Parameter(name: 'manufacturer_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter suppliers by manufacturer ID'),
                new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search in company name, email, or associated category/manufacturer names'),
                new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
                new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15), description: 'Items per page. Use "all" for no pagination.'),
                new OA\Parameter(name: 'paginate', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false']), description: 'Use "false" to get all results without pagination'),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Companies retrieved successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )]
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Company::with(['categories', 'manufacturers']);

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by category_id (for suppliers)
            if ($request->has('category_id')) {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where('categories.id', $request->category_id);
                });
            }

            // Filter by manufacturer_id (for suppliers)
            if ($request->has('manufacturer_id')) {
                $query->whereHas('manufacturers', function ($q) use ($request) {
                    $q->where('manufacturers.id', $request->manufacturer_id);
                });
            }

            // Search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhereHas('categories', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('categories.name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('manufacturers', function ($manufacturerQuery) use ($search) {
                            $manufacturerQuery->where('manufacturers.name', 'ilike', "%{$search}%");
                        });
                });
            }

            [$companies, $pagination] = $this->paginateOrAll($query->latest(), $request);

            // Ensure relationships are loaded even after whereHas filters
            $companies->load(['categories', 'manufacturers']);

            $additional = [
                'success' => true,
                'message' => 'Companies retrieved successfully',
            ];
            if ($pagination !== null) {
                $additional['pagination'] = $pagination;
            }

            return CompanyResource::collection($companies)
                ->additional($additional)
                ->response();
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
                        new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 2000, example: 'Leading pharmacy serving the community'),
                        new OA\Property(property: 'type', type: 'string', enum: ['supplier', 'buyer'], example: 'buyer'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                        new OA\Property(property: 'phone', type: 'string', nullable: true),
                        new OA\Property(property: 'address', type: 'string', nullable: true, description: 'Legacy address field'),
                        new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true, example: 24.7136, description: 'Latitude coordinate (-90 to 90)'),
                        new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true, example: 46.6753, description: 'Longitude coordinate (-180 to 180)'),
                        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Riyadh'),
                        new OA\Property(property: 'region', type: 'string', nullable: true, example: 'Riyadh Region'),
                        new OA\Property(property: 'address_line', type: 'string', nullable: true, example: 'King Fahd Road, Building 123, Floor 5'),
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
        $this->authorize('create', Company::class);

        $company = Company::create($request->validated());

        return $this->success(
            new CompanyResource($company),
            'Company created successfully',
            201
        );
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
        $company->load(['categories', 'manufacturers', 'region.city']);
        return $this->success(new CompanyResource($company));
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
                        new OA\Property(property: 'address', type: 'string', nullable: true, description: 'Legacy address field'),
                        new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true, example: 24.7136, description: 'Latitude coordinate (-90 to 90)'),
                        new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true, example: 46.6753, description: 'Longitude coordinate (-180 to 180)'),
                        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Riyadh'),
                        new OA\Property(property: 'region', type: 'string', nullable: true, example: 'Riyadh Region'),
                        new OA\Property(property: 'address_line', type: 'string', nullable: true, example: 'King Fahd Road, Building 123, Floor 5'),
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

        return $this->success(
            new CompanyResource($company),
            'Company updated successfully'
        );
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