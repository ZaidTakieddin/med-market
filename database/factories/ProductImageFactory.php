<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);
        $path = "products/{$name}.jpg";

        return [
            'product_id' => Product::factory(),
            'disk' => 'public',
            'path' => $path,
            'url' => "https://res.cloudinary.com/demo/image/upload/{$name}.jpg",
            'original_filename' => $name.'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 500_000),
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    /**
     * Mark the image as the product's primary image.
     */
    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
