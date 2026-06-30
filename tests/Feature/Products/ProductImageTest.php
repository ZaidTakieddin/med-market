<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['media.disk' => 'public']);
        Storage::fake('public');
    }

    private function supplierWithProduct(): array
    {
        $user = User::factory()->supplier()->create();
        $product = Product::factory()->create(['supplier_company_id' => $user->company_id]);

        return [$user, $product];
    }

    public function test_owning_supplier_can_upload_images_and_first_becomes_primary(): void
    {
        [$user, $product] = $this->supplierWithProduct();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/products/{$product->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.png'),
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->assertSame(2, $product->images()->count());
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());

        foreach (ProductImage::all() as $image) {
            Storage::disk($image->disk)->assertExists($image->path);
        }
    }

    public function test_buyer_can_list_but_cannot_upload_images(): void
    {
        [$owner, $product] = $this->supplierWithProduct();
        ProductImage::factory()->primary()->create(['product_id' => $product->id]);

        $buyer = User::factory()->buyer()->create();
        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/products/{$product->id}/images")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertStatus(403);
    }

    public function test_other_supplier_cannot_upload_images(): void
    {
        [$owner, $product] = $this->supplierWithProduct();

        $otherSupplier = User::factory()->supplier()->create();
        Sanctum::actingAs($otherSupplier);

        $this->postJson("/api/v1/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertStatus(403);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        [$user, $product] = $this->supplierWithProduct();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf')],
        ])->assertStatus(422);
    }

    public function test_uploading_beyond_the_per_product_limit_is_rejected(): void
    {
        [$user, $product] = $this->supplierWithProduct();
        Sanctum::actingAs($user);

        // Fill to the cap (8), then one more should be rejected by the service.
        ProductImage::factory()->count(8)->create(['product_id' => $product->id]);

        $this->postJson("/api/v1/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('extra.jpg')],
        ])->assertStatus(422);

        $this->assertSame(8, $product->images()->count());
    }

    public function test_set_primary_flips_exactly_one_image(): void
    {
        [$user, $product] = $this->supplierWithProduct();
        Sanctum::actingAs($user);

        $first = ProductImage::factory()->primary()->create(['product_id' => $product->id]);
        $second = ProductImage::factory()->create(['product_id' => $product->id]);

        $this->patchJson("/api/v1/products/{$product->id}/images/{$second->id}/primary")
            ->assertOk()
            ->assertJsonPath('data.isPrimary', true);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_reorder_sets_sort_order_and_rejects_foreign_ids(): void
    {
        [$user, $product] = $this->supplierWithProduct();
        Sanctum::actingAs($user);

        $a = ProductImage::factory()->create(['product_id' => $product->id]);
        $b = ProductImage::factory()->create(['product_id' => $product->id]);

        $this->patchJson("/api/v1/products/{$product->id}/images/reorder", [
            'ordered_ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);

        // An image belonging to another product must be rejected.
        $foreign = ProductImage::factory()->create();
        $this->patchJson("/api/v1/products/{$product->id}/images/reorder", [
            'ordered_ids' => [$a->id, $foreign->id],
        ])->assertStatus(422);
    }

    public function test_deleting_primary_image_promotes_the_next_and_removes_the_file(): void
    {
        [$user, $product] = $this->supplierWithProduct();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/products/{$product->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ])->assertStatus(201);

        $primary = $product->images()->where('is_primary', true)->firstOrFail();
        $other = $product->images()->where('is_primary', false)->firstOrFail();

        $this->deleteJson("/api/v1/products/{$product->id}/images/{$primary->id}")
            ->assertOk();

        $this->assertDatabaseMissing('product_images', ['id' => $primary->id]);
        Storage::disk($primary->disk)->assertMissing($primary->path);
        $this->assertTrue($other->fresh()->is_primary);
    }

    public function test_cannot_delete_an_image_belonging_to_another_product(): void
    {
        [$user, $product] = $this->supplierWithProduct();
        Sanctum::actingAs($user);

        $foreign = ProductImage::factory()->create();

        $this->deleteJson("/api/v1/products/{$product->id}/images/{$foreign->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('product_images', ['id' => $foreign->id]);
    }

    public function test_product_payload_includes_images_and_primary_image_url(): void
    {
        [$user, $product] = $this->supplierWithProduct();
        Sanctum::actingAs($user);

        $primary = ProductImage::factory()->primary()->create(['product_id' => $product->id]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.primaryImageUrl', $primary->url);
    }
}
