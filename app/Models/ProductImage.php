<?php

namespace App\Models;

use App\Constants\MediaConstraints;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    /** @use HasFactory<\Database\Factories\ProductImageFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'disk',
        'path',
        'url',
        'original_filename',
        'mime_type',
        'size',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'size' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the product this image belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A CDN-optimized thumbnail URL. On Cloudinary the transformation is injected
     * into the delivery URL; on any other disk the full-size URL is returned.
     */
    public function thumbnailUrl(): string
    {
        $marker = config('media.cloudinary_upload_marker');

        if (is_string($marker) && $marker !== '' && str_contains($this->url, $marker)) {
            return str_replace($marker, $marker.MediaConstraints::THUMBNAIL_TRANSFORM.'/', $this->url);
        }

        return $this->url;
    }
}
