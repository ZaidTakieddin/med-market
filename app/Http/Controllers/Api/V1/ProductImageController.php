<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Products\ProductImageService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Products\ReorderProductImagesRequest;
use App\Http\Requests\Products\UploadProductImagesRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Product Images', description: 'Product image gallery management')]
class ProductImageController extends BaseApiController
{
    public function __construct(
        private ProductImageService $productImageService
    ) {}

    /**
     * List a product's images.
     */
    #[OA\Get(
        path: '/products/{product}/images',
        summary: 'List product images',
        description: 'List all images for a product, ordered with the primary image first.',
        tags: ['Product Images'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Images retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function index(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->success(
            ProductImageResource::collection($product->images()->get()),
            'Images retrieved successfully'
        );
    }

    /**
     * Upload one or more images for a product.
     */
    #[OA\Post(
        path: '/products/{product}/images',
        summary: 'Upload product images',
        description: 'Upload one or more images (multipart field images[]). The first image of a product becomes its primary image. Only the owning supplier or an admin may upload.',
        tags: ['Product Images'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['images'],
                    properties: [
                        new OA\Property(
                            property: 'images[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'JPEG/PNG/WebP images, max 5 MB each, up to 8 per product.'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Images uploaded successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(UploadProductImagesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $uploaded = $request->file('images');
        /** @var list<UploadedFile> $files */
        $files = is_array($uploaded) ? array_values($uploaded) : [];

        $images = $this->productImageService->addImages($product, $files);

        return $this->success(
            ProductImageResource::collection($images),
            'Images uploaded successfully',
            201
        );
    }

    /**
     * Delete a product image.
     */
    #[OA\Delete(
        path: '/products/{product}/images/{image}',
        summary: 'Delete a product image',
        description: 'Delete an image and its stored file. If it was the primary image, the next image is promoted. Only the owning supplier or an admin may delete.',
        tags: ['Product Images'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'image', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Image deleted successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $product);
        $this->ensureImageBelongsToProduct($product, $image);

        $this->productImageService->delete($image);

        return $this->success(null, 'Image deleted successfully');
    }

    /**
     * Reorder a product's images.
     */
    #[OA\Patch(
        path: '/products/{product}/images/reorder',
        summary: 'Reorder product images',
        description: 'Set the display order of a product\'s images. Send ordered_ids as the full list of image IDs in the desired order. Only the owning supplier or an admin may reorder.',
        tags: ['Product Images'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ordered_ids'],
                properties: [
                    new OA\Property(property: 'ordered_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [3, 1, 2]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Images reordered successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function reorder(ReorderProductImagesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        /** @var array<int|string> $ids */
        $ids = (array) $request->validated('ordered_ids');
        $orderedIds = array_values(array_map('intval', $ids));

        $images = $this->productImageService->reorder($product, $orderedIds);

        return $this->success(
            ProductImageResource::collection($images),
            'Images reordered successfully'
        );
    }

    /**
     * Set a product's primary image.
     */
    #[OA\Patch(
        path: '/products/{product}/images/{image}/primary',
        summary: 'Set primary product image',
        description: 'Mark the given image as the product\'s primary (cover) image. Only the owning supplier or an admin may change it.',
        tags: ['Product Images'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'image', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Primary image updated successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function setPrimary(Product $product, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $product);
        $this->ensureImageBelongsToProduct($product, $image);

        $updated = $this->productImageService->setPrimary($product, $image);

        return $this->success(
            new ProductImageResource($updated),
            'Primary image updated successfully'
        );
    }

    /**
     * Guard nested route bindings: the image must belong to the product.
     */
    private function ensureImageBelongsToProduct(Product $product, ProductImage $image): void
    {
        abort_unless($image->product_id === $product->id, 404);
    }
}
