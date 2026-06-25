<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferProduct>
 */
class OfferProductFactory extends Factory
{
    protected $model = OfferProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'product_id' => Product::factory(),
            'quantity_required' => fake()->numberBetween(1, 5),
            'quantity_free' => fake()->numberBetween(0, 2),
            'discount_override' => null,
        ];
    }
}
