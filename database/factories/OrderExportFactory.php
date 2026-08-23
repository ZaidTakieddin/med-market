<?php

namespace Database\Factories;

use App\Constants\ExportStatus;
use App\Models\Company;
use App\Models\OrderExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderExport>
 */
class OrderExportFactory extends Factory
{
    protected $model = OrderExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory()->supplier(),
            'status' => ExportStatus::QUEUED,
            'format' => 'csv',
            'filters' => null,
            'file_path' => null,
            'orders_count' => null,
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::COMPLETED,
            'file_path' => 'exports/orders/'.now()->format('Y/m').'/orders_export_'.fake()->numerify('########').'.csv',
            'orders_count' => fake()->numberBetween(1, 100),
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::FAILED,
            'error' => 'Export failed',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
