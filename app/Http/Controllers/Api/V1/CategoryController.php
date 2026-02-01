<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Categories\CreateCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Categories', description: 'Category management endpoints (Admin only)')]
class CategoryController extends BaseApiController
{
    /**
     * List categories.
     */
    #[OA\Get(
            path: '/categories',
            summary: 'List categories',
            description: 'Get a paginated list of categories. Available to all authenticated users for product creation.',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status'),
                new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search in name or description'),
                new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
                new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Categories retrieved successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
            ]
        )]
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Category::query();

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

            $categories = $query->orderBy('sort_order')->orderBy('name')->paginate($request->get('per_page', 15));

            return CategoryResource::collection($categories->items())
                ->additional([
                        'success' => true,
                        'message' => 'Categories retrieved successfully',
                        'pagination' => [
                            'current_page' => $categories->currentPage(),
                            'per_page' => $categories->perPage(),
                            'total' => $categories->total(),
                            'last_page' => $categories->lastPage(),
                            'from' => $categories->firstItem(),
                            'to' => $categories->lastItem(),
                        ],
                    ])
                ->response();
        } catch (\Exception $e) {
            Log::error('Category list error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve categories: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create a new category.
     */
    #[OA\Post(
            path: '/categories',
            summary: 'Create category',
            description: 'Create a new category. Only admins can create categories.',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['name'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'مسكنات'),
                        new OA\Property(property: 'slug', type: 'string', nullable: true),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'is_active', type: 'boolean', default: true),
                        new OA\Property(property: 'sort_order', type: 'integer', default: 0),
                    ]
                )
            ),
            responses: [
                new OA\Response(response: 201, description: 'Category created successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden - Only admins can create categories'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )]
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return $this->error('Only admins can create categories.', null, 403);
        }

        $category = Category::create($request->validated());

        return $this->success(
            new CategoryResource($category),
            'Category created successfully',
            201
        );
    }

    /**
     * Get a specific category.
     */
    #[OA\Get(
            path: '/categories/{id}',
            summary: 'Get category',
            description: 'Get details of a specific category',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Category retrieved successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 404, description: 'Category not found'),
            ]
        )]
    public function show(Category $category): JsonResponse
    {
        return $this->success(new CategoryResource($category));
    }

    /**
     * Update a category.
     */
    #[OA\Put(
            path: '/categories/{id}',
            summary: 'Update category',
            description: 'Update category details. Only admins can update categories.',
            tags: ['Categories'],
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
                new OA\Response(response: 200, description: 'Category updated successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden'),
                new OA\Response(response: 404, description: 'Category not found'),
                new OA\Response(response: 422, description: 'Validation error'),
            ]
        )]
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return $this->error('Only admins can update categories.', null, 403);
        }

        $category->update($request->validated());

        return $this->success(
            new CategoryResource($category),
            'Category updated successfully'
        );
    }

    /**
     * Delete a category.
     */
    #[OA\Delete(
            path: '/categories/{id}',
            summary: 'Delete category',
            description: 'Soft delete a category. Only admins can delete categories.',
            tags: ['Categories'],
            security: [['sanctum' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            ],
            responses: [
                new OA\Response(response: 200, description: 'Category deleted successfully'),
                new OA\Response(response: 401, description: 'Unauthenticated'),
                new OA\Response(response: 403, description: 'Forbidden'),
                new OA\Response(response: 404, description: 'Category not found'),
            ]
        )]
    public function destroy(Category $category): JsonResponse
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return $this->error('Only admins can delete categories.', null, 403);
        }

        $category->delete();

        return $this->success(null, 'Category deleted successfully');
    }
}
