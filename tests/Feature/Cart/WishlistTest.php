<?php

namespace Tests\Feature\Cart;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_list_remove_and_move_to_cart(): void
    {
        $buyerUser = User::factory()->buyer()->create();
        $product = Product::factory()->withStock(50)->create([
            'price' => 25,
            'pharmacist_price' => 25,
        ]);

        Sanctum::actingAs($buyerUser);

        $this->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])->assertOk();
        // Idempotent.
        $this->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])->assertOk();
        $this->assertSame(1, WishlistItem::query()->where('user_id', $buyerUser->id)->count());

        $list = $this->getJson('/api/v1/wishlist')->assertOk();
        $this->assertSame($product->id, $list->json('data.0.id'));

        // Move to cart → item lands in the cart at server-resolved price and leaves wishlist.
        $response = $this->postJson("/api/v1/wishlist/items/{$product->id}/move-to-cart", [
            'quantity' => 3,
        ])->assertOk();

        $item = collect($response->json('data.items'))->first(fn ($i): bool => ($i['product']['id'] ?? null) === $product->id);
        $this->assertNotNull($item);
        $this->assertSame(3, $item['quantity']);
        $this->assertEqualsWithDelta(25.0, $item['unitPrice'], 0.001); // server-resolved unit price

        $this->assertSame(0, WishlistItem::query()->where('user_id', $buyerUser->id)->count());
    }

    public function test_move_to_cart_requires_wishlisted_product(): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());
        $product = Product::factory()->withStock(10)->create();

        $this->postJson("/api/v1/wishlist/items/{$product->id}/move-to-cart", ['quantity' => 1])
            ->assertStatus(422);
    }

    public function test_price_drop_notifies_wishlist_watchers(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $watcher = User::factory()->buyer()->create();
        $other = User::factory()->buyer()->create();
        $product = Product::factory()->withStock(10)->create([
            'price' => 40,
            'pharmacist_price' => 40,
        ]);

        WishlistItem::create(['user_id' => $watcher->id, 'product_id' => $product->id]);

        $product->update(['price' => 30, 'pharmacist_price' => 30]);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $watcher,
            \App\Notifications\PriceDropNotification::class,
        );
        \Illuminate\Support\Facades\Notification::assertNotSentTo(
            $other,
            \App\Notifications\PriceDropNotification::class,
        );
    }
}
