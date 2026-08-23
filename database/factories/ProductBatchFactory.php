<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBatch>
 */
class ProductBatchFactory extends Factory
{
    protected $model = ProductBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'batch_number' => 'LOT-'.fake()->unique()->numerify('######'),
            'expiry_date' => now()->addMonths(fake()->numberBetween(3, 24))->toDateString(),
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }

    public function unbatched(): static
    {
        return $this->state(fn (): array => [
            'batch_number' => null,
            'expiry_date' => null,
        ]);
    }
}
