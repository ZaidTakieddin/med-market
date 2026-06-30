<?php

namespace Database\Factories;

use App\Constants\ProductImportStatus;
use App\Models\Company;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImport>
 */
class ProductImportFactory extends Factory
{
    protected $model = ProductImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_company_id' => Company::factory()->supplier(),
            'user_id' => User::factory(),
            'original_filename' => fake()->word().'.xlsx',
            'stored_path' => 'imports/products/'.fake()->uuid().'.xlsx',
            'status' => ProductImportStatus::PENDING,
            'total_rows' => null,
            'created_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductImportStatus::PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductImportStatus::COMPLETED,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
