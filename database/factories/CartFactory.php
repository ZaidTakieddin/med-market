<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ];
    }
}
