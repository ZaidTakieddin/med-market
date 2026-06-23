<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 5, 200);

        return [
            'supplier_company_id' => Company::factory()->supplier(),
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'unit' => 'box',
            'price' => $price,
            'pharmacist_price' => $price,
            'customer_price' => round($price * 1.2, 2),
            'stock_quantity' => 100,
            'min_stock_level' => 10,
            'is_active' => true,
        ];
    }

    /**
     * Give the product a wholesale tier.
     */
    public function wholesale(int $minQty = 10, ?float $price = null): static
    {
        return $this->state(fn (array $attributes) => [
            'wholesale_price' => $price ?? round(($attributes['price'] ?? 100) * 0.8, 2),
            'wholesale_min_qty' => $minQty,
        ]);
    }

    public function withStock(int $quantity): static
    {
        return $this->state(fn () => ['stock_quantity' => $quantity]);
    }
}
