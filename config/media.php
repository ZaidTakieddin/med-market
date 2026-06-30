<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used to store uploaded media (product images, company
    | logos). Image binaries NEVER live in the database — only their disk, path
    | and resolved URL are persisted. Flip this single switch to change where
    | files go without touching application code:
    |
    |   - "cloudinary"  Production default. Free tier with built-in resizing,
    |                   format optimization and CDN delivery.
    |   - "public"      Local dev / offline. Files in storage/app/public served
    |                   via APP_URL (run `php artisan storage:link`). No
    |                   Cloudinary credentials required.
    |
    | Any S3-compatible disk (e.g. Cloudflare R2) also works unchanged.
    |
    */

    'disk' => env('MEDIA_DISK', 'cloudinary'),

    /*
    |--------------------------------------------------------------------------
    | Cloudinary URL Transformation Marker
    |--------------------------------------------------------------------------
    |
    | Cloudinary delivery URLs contain this segment; thumbnail transformations
    | are injected immediately after it. Non-Cloudinary disks never contain it,
    | so thumbnail generation gracefully falls back to the full-size URL.
    |
    */

    'cloudinary_upload_marker' => '/upload/',

];
