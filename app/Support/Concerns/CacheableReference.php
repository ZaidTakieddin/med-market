<?php

namespace App\Support\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * For rarely-changing lookup models (categories, manufacturers, …): caches the full
 * reference list under a single key and flushes it on any write. Uses one key per
 * model so it works with the file cache driver (no cache tags required).
 */
trait CacheableReference
{
    public static function bootCacheableReference(): void
    {
        $flush = static fn () => Cache::forget(static::referenceListCacheKey());

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    public static function referenceListCacheKey(): string
    {
        return 'ref-list:'.class_basename(static::class);
    }
}
