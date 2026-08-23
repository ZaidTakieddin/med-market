<?php

namespace Tests\Feature\Reviews;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_buyer_can_review_product_once_and_update(): void
    {
        [, $buyerUser, $product] = $this->makeDeliveredScenario();

        Sanctum::actingAs($buyerUser);

        $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'rating' => 5,
            'body' => 'Excellent quality',
        ])->assertCreated();

        // Re-review updates instead of duplicating.
        $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'rating' => 3,
            'body' => 'Downgraded after second batch',
        ])->assertCreated();

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews")->assertOk();
        $this->assertSame(1, $response->json('data.reviewsCount'));
        $this->assertEqualsWithDelta(3.0, $response->json('data.averageRating'), 0.001);
    }

    public function test_unverified_user_cannot_review(): void
    {
        $product = Product::factory()->withStock(10)->create();
        Sanctum::actingAs(User::factory()->buyer()->create()); // no delivered orders

        $this->postJson("/api/v1/products/{$product->id}/reviews", ['rating' => 5])
            ->assertStatus(422);
    }

    public function test_supplier_rating_from_company_reviews(): void
    {
        [$supplier, $buyerUser] = $this->makeDeliveredScenario();

        Sanctum::actingAs($buyerUser);
        $this->postJson("/api/v1/companies/{$supplier->id}/reviews", [
            'rating' => 4,
            'body' => 'Reliable delivery',
        ])->assertCreated();

        $response = $this->getJson("/api/v1/companies/{$supplier->id}/reviews")->assertOk();
        $this->assertEqualsWithDelta(4.0, $response->json('data.averageRating'), 0.001);
    }

    public function test_admin_moderates_rejected_reviews_disappear(): void
    {
        [, $buyerUser, $product] = $this->makeDeliveredScenario();

        Sanctum::actingAs($buyerUser);
        $reviewId = $this->postJson("/api/v1/products/{$product->id}/reviews", ['rating' => 2])->json('data.id');

        Sanctum::actingAs(User::factory()->admin()->create());
        $queue = $this->getJson('/api/v1/reviews?status=approved')->assertOk();
        $this->assertSame(1, collect($queue->json('data.reviews'))->count());

        $this->postJson("/api/v1/reviews/{$reviewId}/reject")->assertOk();

        $public = $this->getJson("/api/v1/products/{$product->id}/reviews")->assertOk();
        $this->assertSame(0, $public->json('data.reviewsCount'));
    }

    public function test_non_buyer_cannot_review(): void
    {
        Sanctum::actingAs(User::factory()->driver()->create());
        $product = Product::factory()->withStock(10)->create();

        $this->postJson("/api/v1/products/{$product->id}/reviews", ['rating' => 5])
            ->assertForbidden();
    }

    /**
     * @return array{0: Company, 1: User, 2: Product}
     */
    private function makeDeliveredScenario(): array
    {
        $supplier = Company::factory()->retail()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $buyerUser = User::factory()->buyer()->forCompany($buyer)->create();
        $product = Product::factory()->withStock(10)->create([
            'supplier_company_id' => $supplier->id,
        ]);

        Order::factory()->delivered()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'created_by' => $buyerUser->id,
        ])->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
            'total_price' => 20,
            'pricing_type' => 'retail',
        ]);

        return [$supplier, $buyerUser, $product];
    }
}
