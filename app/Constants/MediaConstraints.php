<?php

namespace App\Constants;

class MediaConstraints
{
    /**
     * Maximum accepted size for a single uploaded image, in kilobytes.
     */
    public const MAX_IMAGE_SIZE_KB = 5120;

    /**
     * Maximum number of images a single product may have.
     */
    public const MAX_PRODUCT_IMAGES = 8;

    /**
     * Cloudinary transformation used to derive thumbnail URLs (fill-cropped,
     * auto format & quality). Injected into the delivery URL after /upload/.
     */
    public const THUMBNAIL_TRANSFORM = 'w_300,h_300,c_fill,f_auto,q_auto';

    /**
     * Allowed image MIME types for upload.
     *
     * @return list<string>
     */
    public static function allowedMimes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
    }

    /**
     * Allowed file extensions, for the validator's `mimes` rule.
     *
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return [
            'jpeg',
            'jpg',
            'png',
            'webp',
        ];
    }
}
