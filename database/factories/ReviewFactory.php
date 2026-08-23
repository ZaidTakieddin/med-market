<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->buyer(),
            'reviewable_type' => Product::class,
            'reviewable_id' => Product::factory(),
            'order_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'body' => fake()->sentence(),
            'status' => Review::STATUS_APPROVED,
        ];
    }
}
