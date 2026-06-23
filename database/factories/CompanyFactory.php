<?php

namespace Database\Factories;

use App\Constants\BuyerClassification;
use App\Constants\CompanyType;
use App\Constants\SupplierClassification;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * Default state: an active pharmacy (buyer) company.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => CompanyType::BUYER,
            'buyer_classification' => BuyerClassification::PHARMACY,
            'supplier_classification' => null,
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }

    /**
     * A supplier company (retail by default).
     */
    public function supplier(): static
    {
        return $this->state(fn () => [
            'type' => CompanyType::SUPPLIER,
            'buyer_classification' => null,
            'supplier_classification' => SupplierClassification::RETAIL,
        ]);
    }

    public function wholesale(): static
    {
        return $this->supplier()->state(fn () => [
            'supplier_classification' => SupplierClassification::WHOLESALE,
        ]);
    }

    public function hybrid(): static
    {
        return $this->supplier()->state(fn () => [
            'supplier_classification' => SupplierClassification::HYBRID,
        ]);
    }

    public function retail(): static
    {
        return $this->supplier()->state(fn () => [
            'supplier_classification' => SupplierClassification::RETAIL,
        ]);
    }

    /**
     * A pharmacy buyer company (the default, made explicit).
     */
    public function pharmacy(): static
    {
        return $this->state(fn () => [
            'type' => CompanyType::BUYER,
            'buyer_classification' => BuyerClassification::PHARMACY,
            'supplier_classification' => null,
        ]);
    }

    /**
     * A salesman buyer company (wholesale-eligible).
     */
    public function salesman(): static
    {
        return $this->state(fn () => [
            'type' => CompanyType::BUYER,
            'buyer_classification' => BuyerClassification::SALESMAN,
            'supplier_classification' => null,
        ]);
    }
}
