<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\OrderTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTemplate>
 */
class OrderTemplateFactory extends Factory
{
    protected $model = OrderTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_company_id' => Company::factory()->pharmacy(),
            'created_by' => User::factory()->buyer(),
            'name' => 'Restock '.fake()->word(),
            'notes' => null,
        ];
    }
}
