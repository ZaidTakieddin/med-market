<?php

namespace App\Domain\Media;

/**
 * The result of persisting an uploaded image to a storage disk: enough to
 * record in the database. The binary itself lives on the disk, never the DB.
 */
class StoredImage
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $url,
        public readonly ?string $originalFilename,
        public readonly ?string $mimeType,
        public readonly ?int $size,
    ) {}
}
