<?php

namespace App\Domain\Products;

use App\Constants\MediaConstraints;
use App\Domain\Media\ImageUploadService;
use App\Domain\Media\StoredImage;
use App\Exceptions\DomainException;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductImageService
{
    public function __construct(private ImageUploadService $uploader) {}

    /**
     * Upload and attach one or more images to a product. The first image of a
     * product (when none is primary yet) becomes the primary image. Files are
     * uploaded to storage first; the DB rows are then written in a single
     * transaction, with best-effort cleanup of uploaded files on failure.
     *
     * @param  list<UploadedFile>  $files
     * @return Collection<int, ProductImage>
     *
     * @throws DomainException When the product would exceed the per-product image limit.
     */
    public function addImages(Product $product, array $files): Collection
    {
        $existingCount = $product->images()->count();

        if ($existingCount + count($files) > MediaConstraints::MAX_PRODUCT_IMAGES) {
            throw new DomainException(
                'A product can have at most '.MediaConstraints::MAX_PRODUCT_IMAGES.' images.'
            );
        }

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $maxSort = (int) $product->images()->max('sort_order');

        /** @var list<StoredImage> $stored */
        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = $this->uploader->store($file, "products/{$product->id}");
            }

            return DB::transaction(function () use ($product, $stored, $hasPrimary, $maxSort): Collection {
                $created = new Collection;

                foreach ($stored as $index => $image) {
                    $created->push($product->images()->create([
                        'disk' => $image->disk,
                        'path' => $image->path,
                        'url' => $image->url,
                        'original_filename' => $image->originalFilename,
                        'mime_type' => $image->mimeType,
                        'size' => $image->size,
                        'sort_order' => $maxSort + 1 + $index,
                        'is_primary' => ! $hasPrimary && $index === 0,
                    ]));
                }

                return $created;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $image) {
                $this->safeDelete($image->disk, $image->path);
            }

            throw $e;
        }
    }

    /**
     * Delete a product image. If it was the primary image, the next image (by
     * display order) is promoted. The stored file is removed after the row.
     */
    public function delete(ProductImage $image): void
    {
        $wasPrimary = $image->is_primary;
        $productId = $image->product_id;
        $disk = $image->disk;
        $path = $image->path;

        DB::transaction(function () use ($image, $wasPrimary, $productId): void {
            $image->delete();

            if ($wasPrimary) {
                $next = ProductImage::query()
                    ->where('product_id', $productId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                $next?->update(['is_primary' => true]);
            }
        });

        $this->safeDelete($disk, $path);
    }

    /**
     * Make the given image the product's primary image (exactly one primary).
     */
    public function setPrimary(Product $product, ProductImage $image): ProductImage
    {
        return DB::transaction(function () use ($product, $image): ProductImage {
            $product->images()->where('is_primary', true)->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);

            return $image->refresh();
        });
    }

    /**
     * Reorder a product's images. Position in the list becomes sort_order.
     *
     * @param  list<int>  $orderedIds
     * @return Collection<int, ProductImage>
     */
    public function reorder(Product $product, array $orderedIds): Collection
    {
        DB::transaction(function () use ($product, $orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                $product->images()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return $product->images()->get();
    }

    /**
     * Best-effort removal of a stored file; never lets storage errors mask the
     * primary outcome (the DB change already succeeded).
     */
    private function safeDelete(string $disk, string $path): void
    {
        try {
            $this->uploader->delete($disk, $path);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
