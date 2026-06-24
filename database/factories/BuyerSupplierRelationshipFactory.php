<?php

namespace Database\Factories;

use App\Constants\PaymentTerms;
use App\Models\BuyerSupplierRelationship;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerSupplierRelationship>
 */
class BuyerSupplierRelationshipFactory extends Factory
{
    protected $model = BuyerSupplierRelationship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_company_id' => Company::factory()->pharmacy(),
            'supplier_company_id' => Company::factory()->supplier(),
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => 1000,
            'current_balance' => 0,
            'is_approved' => true,
        ];
    }

    public function unapproved(): static
    {
        return $this->state(fn () => ['is_approved' => false]);
    }

    public function creditLimit(?float $limit): static
    {
        return $this->state(fn () => ['credit_limit' => $limit]);
    }
}
