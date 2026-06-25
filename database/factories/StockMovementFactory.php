<?php

namespace Database\Factories;

use App\Constants\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $before = fake()->numberBetween(0, 100);
        $quantity = fake()->numberBetween(1, 50);

        return [
            'product_id' => Product::factory(),
            'order_id' => null,
            'user_id' => User::factory(),
            'type' => StockMovementType::IN,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $before + $quantity,
            'reason' => fake()->sentence(),
        ];
    }
}
