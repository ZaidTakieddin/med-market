<?php

namespace App\Domain\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Low-level, disk-agnostic image persistence. The destination disk is driven by
 * config('media.disk') so the storage backend (Cloudinary, local public, S3/R2)
 * is a single config switch with no code changes. Callers persist only the
 * returned {disk, path, url} — image binaries never touch the database.
 */
class ImageUploadService
{
    /**
     * The configured media storage disk.
     */
    public function disk(): string
    {
        return (string) config('media.disk');
    }

    /**
     * Store an uploaded image on the media disk and return its persistence metadata.
     */
    public function store(UploadedFile $file, string $directory): StoredImage
    {
        $disk = $this->disk();
        $storage = Storage::disk($disk);

        $path = $storage->putFile($directory, $file);

        if ($path === false) {
            throw new \RuntimeException('Failed to store the uploaded image.');
        }

        $size = $file->getSize();

        return new StoredImage(
            disk: $disk,
            path: $path,
            url: $storage->url($path),
            originalFilename: $file->getClientOriginalName(),
            mimeType: $file->getClientMimeType(),
            size: $size !== false ? $size : null,
        );
    }

    /**
     * Delete a previously stored image from its disk.
     */
    public function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }
}
