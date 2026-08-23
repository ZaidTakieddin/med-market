<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reviews\ReviewService;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Company;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Reviews', description: 'Verified-purchase reviews for products and suppliers. One review per user per target; admin-moderated.')]
class ReviewController extends BaseApiController
{
    public function __construct(
        private ReviewService $reviewService
    ) {}

    /**
     * Review a product.
     */
    #[OA\Post(
        path: '/products/{id}/reviews',
        summary: 'Review a product',
        description: 'Create or update the authenticated user\'s review of a product. Requires a delivered order from the buyer company containing the product. Re-reviewing updates the existing review.',
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['rating'], properties: [
            new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 5),
            new OA\Property(property: 'body', type: 'string', nullable: true),
            new OA\Property(property: 'order_id', type: 'integer', nullable: true, description: 'Optional delivered-order reference for verification'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Review saved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function storeProductReview(Request $request, Product $product): JsonResponse
    {
        return $this->store($request, Review::TYPE_PRODUCT, (int) $product->id);
    }

    /**
     * Review a supplier.
     */
    #[OA\Post(
        path: '/companies/{id}/reviews',
        summary: 'Review a supplier',
        description: "Create or update the authenticated user's review of a supplier. Requires a delivered order supplied by that company.",
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['rating'], properties: [
            new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5),
            new OA\Property(property: 'body', type: 'string', nullable: true),
            new OA\Property(property: 'order_id', type: 'integer', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Review saved successfully'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function storeCompanyReview(Request $request, Company $company): JsonResponse
    {
        return $this->store($request, Review::TYPE_COMPANY, (int) $company->id);
    }

    /**
     * Product reviews list + aggregates.
     */
    #[OA\Get(
        path: '/products/{id}/reviews',
        summary: 'List product reviews',
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Reviews retrieved with rating aggregates'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function productReviews(Product $product): JsonResponse
    {
        return $this->listReviews(Review::TYPE_PRODUCT, (int) $product->id);
    }

    /**
     * Supplier reviews list + aggregates.
     */
    #[OA\Get(
        path: '/companies/{id}/reviews',
        summary: 'List supplier reviews',
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Reviews retrieved with rating aggregates'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function companyReviews(Company $company): JsonResponse
    {
        return $this->listReviews(Review::TYPE_COMPANY, (int) $company->id);
    }

    /**
     * Moderate: approve.
     */
    #[OA\Post(path: '/reviews/{review}/approve', summary: 'Approve review', tags: ['Reviews'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'review', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [
        new OA\Response(response: 200, description: 'Review approved'),
        new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
    ])]
    public function approve(Review $review): JsonResponse
    {
        $this->authorizeModeration();

        $review->update(['status' => Review::STATUS_APPROVED]);

        return $this->success(null, 'Review approved');
    }

    /**
     * Moderate: reject.
     */
    #[OA\Post(path: '/reviews/{review}/reject', summary: 'Reject review', tags: ['Reviews'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'review', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [
        new OA\Response(response: 200, description: 'Review rejected'),
        new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
    ])]
    public function reject(Review $review): JsonResponse
    {
        $this->authorizeModeration();

        $review->update(['status' => Review::STATUS_REJECTED]);

        return $this->success(null, 'Review rejected');
    }

    /**
     * Moderation queue.
     */
    #[OA\Get(
        path: '/reviews',
        summary: 'List reviews (moderation)',
        description: 'All reviews with optional `?status=` filter (approved/pending/rejected). Admin only.',
        tags: ['Reviews'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['approved', 'pending', 'rejected']))],
        responses: [
            new OA\Response(response: 200, description: 'Reviews retrieved'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeModeration();

        $query = Review::query()->with(['user:id,name', 'reviewable'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest();

        return $this->success([
            'reviews' => $query->limit(100)->get()->map(fn ($review): array => [
                'id' => $review->id,
                'user' => $review->user?->name,
                'reviewableType' => class_basename($review->reviewable_type),
                'reviewableId' => $review->reviewable_id,
                'rating' => $review->rating,
                'body' => $review->body,
                'status' => $review->status,
            ]),
        ], 'Reviews retrieved successfully');
    }

    private function authorizeModeration(): void
    {
        if (! $this->currentUser()->isAdmin()) {
            abort(403, 'Only admins can moderate reviews.');
        }
    }

    private function store(Request $request, string $type, int $targetId): JsonResponse
    {
        $user = $this->currentUser();
        $this->assertCanReview($user);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $review = $this->reviewService->create(
            user: $user,
            reviewableType: $type,
            reviewableId: $targetId,
            rating: (int) $data['rating'],
            body: $data['body'] ?? null,
            orderId: isset($data['order_id']) ? (int) $data['order_id'] : null,
        );

        return $this->success([
            'id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'status' => $review->status,
        ], 'Review saved successfully', 201);
    }

    private function listReviews(string $type, int $targetId): JsonResponse
    {
        $aggregates = $this->reviewService->aggregates($type, $targetId);

        $reviews = Review::query()
            ->where('reviewable_type', $type)
            ->where('reviewable_id', $targetId)
            ->approved()
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'body' => $review->body,
                'user' => $review->user?->name,
                'createdAt' => $review->created_at?->toISOString(),
            ]);

        return $this->success([
            'averageRating' => $aggregates['average'],
            'reviewsCount' => $aggregates['count'],
            'distribution' => $aggregates['distribution'],
            'reviews' => $reviews,
        ], 'Reviews retrieved successfully');
    }

    private function assertCanReview(User $user): void
    {
        if (! $user->canBuy()) {
            abort(403, 'Only buyers and salesmen can write reviews.');
        }
    }
}
