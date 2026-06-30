<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProductImage
 */
class ProductImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'thumbnailUrl' => $this->thumbnailUrl(),
            'isPrimary' => $this->is_primary,
            'sortOrder' => $this->sort_order,
            'originalFilename' => $this->original_filename,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
