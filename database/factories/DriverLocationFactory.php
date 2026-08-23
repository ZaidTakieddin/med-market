<?php

namespace Database\Factories;

use App\Models\DriverLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverLocation>
 */
class DriverLocationFactory extends Factory
{
    protected $model = DriverLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => User::factory()->driver(),
            'latitude' => fake()->latitude(32.0, 34.5),
            'longitude' => fake()->longitude(35.5, 38.5),
            'recorded_at' => now(),
        ];
    }
}
