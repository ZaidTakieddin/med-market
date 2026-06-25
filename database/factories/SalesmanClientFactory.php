<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SalesmanClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesmanClient>
 */
class SalesmanClientFactory extends Factory
{
    protected $model = SalesmanClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salesman_user_id' => User::factory(),
            'salesman_company_id' => Company::factory()->supplier(),
            'pharmacy_company_id' => Company::factory()->pharmacy(),
            'is_active' => true,
            'assigned_at' => now(),
        ];
    }
}
