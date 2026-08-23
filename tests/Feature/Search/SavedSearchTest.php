<?php

namespace Tests\Feature\Search;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_list_delete_own_searches_only(): void
    {
        $user = User::factory()->buyer()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/saved-searches', [
            'name' => 'Cheap vitamins',
            'entity' => 'products',
            'params' => ['category_ids' => '2', 'max_price' => 50],
        ])->assertCreated()->json('data.id');

        // Another user's saved search is invisible.
        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->getJson('/api/v1/saved-searches')->assertOk()->assertJsonCount(0, 'data');

        // Owner deletes it.
        Sanctum::actingAs($user);
        $this->deleteJson("/api/v1/saved-searches/{$id}")->assertOk();
        $this->getJson('/api/v1/saved-searches')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_saved_search_can_be_replayed_on_products_index(): void
    {
        $supplier = \App\Models\Company::factory()->retail()->create();
        $pharmacy = \App\Models\Company::factory()->pharmacy()->create();
        $user = User::factory()->buyer()->forCompany($pharmacy)->create();

        Product::factory()->withStock(10)->create([
            'supplier_company_id' => $supplier->id,
            'pharmacist_price' => 90,
            'price' => 90,
        ]);
        $cheap = Product::factory()->withStock(10)->create([
            'supplier_company_id' => $supplier->id,
            'pharmacist_price' => 20,
            'price' => 20,
        ]);

        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/saved-searches', [
            'name' => 'Under 50',
            'entity' => 'products',
            'params' => ['max_price' => 50],
        ])->json('data.id');

        $ids = collect($this->getJson("/api/v1/products?saved_search_id={$id}")->json('data'))->pluck('id');
        $this->assertSame([$cheap->id], $ids->all());
    }

    public function test_cannot_replay_someone_elses_saved_search(): void
    {
        $owner = User::factory()->buyer()->create();
        $saved = new \App\Models\SavedSearch([
            'name' => 'X',
            'entity' => 'products',
            'params' => [],
        ]);
        $saved->user_id = $owner->id;
        $saved->save();

        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->getJson('/api/v1/products?saved_search_id='.$saved->id)->assertNotFound();
    }

    public function test_search_history_records_and_prunes_to_twenty(): void
    {
        $user = User::factory()->buyer()->create();
        Sanctum::actingAs($user);

        for ($i = 1; $i <= 25; $i++) {
            $this->postJson('/api/v1/search/history', [
                'entity' => 'products',
                'term' => "term-{$i}",
            ])->assertOk();
        }

        $history = collect($this->getJson('/api/v1/search/history')->json('data'));
        $this->assertCount(20, $history);
        $this->assertSame('term-25', $history->first()['term']); // newest first
        $terms = $history->pluck('term');
        $this->assertFalse($terms->contains('term-5')); // pruned
    }

    public function test_history_is_private(): void
    {
        $user = User::factory()->buyer()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/search/history', [
            'entity' => 'products',
            'term' => 'secret-term',
        ])->assertOk();

        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->getJson('/api/v1/search/history')->assertOk()->assertJsonCount(0, 'data');
    }
}
