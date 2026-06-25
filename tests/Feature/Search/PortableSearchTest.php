<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards the portable, driver-agnostic search (whereLike) that replaced Postgres-only
 * `ilike`. The mere fact these run green on the sqlite test DB proves portability —
 * `ilike` would have thrown a syntax error here.
 */
class PortableSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_search_is_case_insensitive_on_any_driver(): void
    {
        Category::factory()->create(['name' => 'Antibiotics']);
        Category::factory()->create(['name' => 'Vitamins']);
        Sanctum::actingAs(User::factory()->create());

        // Uppercase query must match the mixed-case record (case-insensitive).
        $response = $this->getJson('/api/v1/categories?search=ANTI&per_page=all')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Antibiotics'), 'Case-insensitive search should match Antibiotics');
        $this->assertFalse($names->contains('Vitamins'), 'Non-matching record should be excluded');
    }

    public function test_product_search_matches_sku_case_insensitively(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        Product::factory()->create(['name' => 'Aspirin', 'sku' => 'ABC-999']);
        Product::factory()->create(['name' => 'Bandage', 'sku' => 'ZZZ-111']);

        $response = $this->getJson('/api/v1/products?search=abc-999&per_page=all')->assertOk();

        $skus = collect($response->json('data'))->pluck('sku');
        $this->assertTrue($skus->contains('ABC-999'), 'Lowercase query should match the uppercase SKU');
        $this->assertFalse($skus->contains('ZZZ-111'), 'Non-matching SKU should be excluded');
    }
}
