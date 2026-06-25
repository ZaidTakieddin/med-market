<?php

namespace Database\Factories;

use App\Constants\OfferType;
use App\Models\Company;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_company_id' => Company::factory()->supplier(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'offer_type' => OfferType::PERCENTAGE_DISCOUNT,
            'discount_value' => fake()->randomFloat(2, 5, 30),
            'quantity_required' => null,
            'quantity_free' => null,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ];
    }
}
