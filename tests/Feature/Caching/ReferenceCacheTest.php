<?php

namespace Tests\Feature\Caching;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferenceCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_dropdown_is_cached_and_flushed_on_write(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Category::create(['name' => 'Painkillers']);

        $key = Category::referenceListCacheKey();
        $this->assertFalse(Cache::has($key));

        // First unfiltered fetch populates the cache.
        $first = $this->getJson('/api/v1/categories?per_page=all')->assertOk();
        $this->assertCount(1, $first->json('data'));
        $this->assertTrue(Cache::has($key));

        // A write flushes the cache (CacheableReference saved event)...
        Category::create(['name' => 'Antibiotics']);
        $this->assertFalse(Cache::has($key));

        // ...so the next fetch is fresh, not stale.
        $second = $this->getJson('/api/v1/categories?per_page=all')->assertOk();
        $this->assertCount(2, $second->json('data'));
    }

    public function test_filtered_request_is_not_served_from_the_dropdown_cache(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Category::create(['name' => 'Painkillers', 'is_active' => true]);

        // A filtered request (even unpaginated) must bypass the dropdown cache.
        $this->getJson('/api/v1/categories?is_active=true&per_page=all')->assertOk();

        $this->assertFalse(Cache::has(Category::referenceListCacheKey()));
    }
}
