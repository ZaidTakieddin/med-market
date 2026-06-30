<?php

namespace Database\Factories;

use App\Models\ProductImport;
use App\Models\ProductImportError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImportError>
 */
class ProductImportErrorFactory extends Factory
{
    protected $model = ProductImportError::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_import_id' => ProductImport::factory(),
            'row_number' => fake()->numberBetween(2, 1000),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'message' => fake()->sentence(),
        ];
    }
}
