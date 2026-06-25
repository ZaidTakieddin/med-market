<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_company_id' => Company::factory()->supplier(),
            'buyer_company_id' => Company::factory()->pharmacy(),
            'product_id' => Product::factory(),
            'custom_price' => fake()->randomFloat(2, 5, 200),
            'min_qty' => 1,
            'valid_from' => null,
            'valid_to' => null,
            'is_active' => true,
        ];
    }
}
