<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Models\Company;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeCatalog(): array
    {
        $supplier = Company::factory()->retail()->create();
        $buyerUser = User::factory()->buyer()->create();
        $painkillers = Category::factory()->create(['name' => 'Pain relief']);
        $vitamins = Category::factory()->create(['name' => 'Vitamins']);
        $mfgA = Manufacturer::factory()->create(['name' => 'Alpha Pharma']);
        $mfgB = Manufacturer::factory()->create(['name' => 'Beta Pharma']);

        $cheap = Product::factory()->withStock(10)->create([
            'supplier_company_id' => $supplier->id,
            'category_id' => $painkillers->id,
            'manufacturer_id' => $mfgA->id,
            'pharmacist_price' => 10,
            'price' => 10,
        ]);
        $pricey = Product::factory()->withStock(0)->create([
            'supplier_company_id' => $supplier->id,
            'category_id' => $vitamins->id,
            'manufacturer_id' => $mfgB->id,
            'pharmacist_price' => 90,
            'price' => 90,
        ]);

        return [$supplier, $buyerUser, [$cheap, $pricey], [$painkillers, $vitamins], [$mfgA, $mfgB]];
    }

    public function test_multi_category_and_price_filters(): void
    {
        [, $buyerUser, [$cheap, $pricey], [$painkillers, $vitamins]] = $this->makeCatalog();

        Sanctum::actingAs($buyerUser);

        // Both categories at once.
        $both = collect($this->getJson("/api/v1/products?category_ids={$painkillers->id},{$vitamins->id}")
            ->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$cheap->id, $pricey->id], $both->all());

        // Price ceiling excludes the pricey product.
        $affordable = collect($this->getJson('/api/v1/products?max_price=50')->json('data'))->pluck('id');
        $this->assertSame([$cheap->id], $affordable->all());
    }

    public function test_manufacturer_ids_in_stock_and_sorting(): void
    {
        [$supplier, $buyerUser, [$cheap, $pricey], , [$mfgA]] = $this->makeCatalog();

        Sanctum::actingAs($buyerUser);

        $byMfg = collect($this->getJson("/api/v1/products?manufacturer_ids={$mfgA->id}")
            ->json('data'))->pluck('id');
        $this->assertSame([$cheap->id], $byMfg->all());

        // in_stock excludes the zero-stock product.
        $inStock = collect($this->getJson('/api/v1/products?in_stock=true')->json('data'))->pluck('id');
        $this->assertSame([$cheap->id], $inStock->all());

        // Sorting by price descending puts the pricey product first.
        $sorted = collect($this->getJson('/api/v1/products?sort=price_desc')->json('data'))->pluck('id');
        $this->assertSame([$pricey->id, $cheap->id], $sorted->all());
        unset($supplier);
    }

    public function test_company_filters_by_city(): void
    {
        $this->makeCatalog();
        Sanctum::actingAs(User::factory()->admin()->create());

        $city = \App\Models\City::factory()->create();
        $region = \App\Models\Region::factory()->create(['city_id' => $city->id]);
        $located = Company::factory()->retail()->create(['region_id' => $region->id]);

        $ids = collect($this->getJson("/api/v1/companies?city_id={$city->id}")->json('data'))->pluck('id');
        $this->assertSame([$located->id], $ids->all());
    }

    public function test_suggestions_match_products_categories_and_manufacturers(): void
    {
        [$supplier, $buyerUser, [$cheap]] = $this->makeCatalog();
        $cheap->update(['name' => 'Aspirin Cardio 100']);

        Sanctum::actingAs($buyerUser);

        $response = $this->getJson('/api/v1/search/suggestions?q=aspi')->assertOk();

        $products = collect($response->json('data.products'))->pluck('name');
        $this->assertTrue($products->contains(fn ($n) => str_contains($n, 'Aspirin')));

        $cats = collect($this->getJson('/api/v1/search/suggestions?q=vita&types=categories')->json('data.categories'))->pluck('name');
        $this->assertGreaterThan(0, $cats->count());
    }

    public function test_suggestions_require_minimum_term_length(): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->getJson('/api/v1/search/suggestions?q=a')->assertStatus(422);
    }
}
