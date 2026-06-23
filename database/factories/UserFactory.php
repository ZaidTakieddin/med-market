<?php

namespace Database\Factories;

use App\Constants\UserRole;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::BUYER,
            'company_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN, 'company_id' => null]);
    }

    public function supplier(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::SUPPLIER,
            'company_id' => Company::factory()->supplier(),
        ]);
    }

    public function buyer(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::BUYER,
            'company_id' => Company::factory()->pharmacy(),
        ]);
    }

    public function driver(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::DRIVER,
            'company_id' => Company::factory()->supplier(),
        ]);
    }

    public function salesman(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::SALESMAN,
            'company_id' => Company::factory()->supplier(),
        ]);
    }

    /**
     * Attach the user to an existing company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}
