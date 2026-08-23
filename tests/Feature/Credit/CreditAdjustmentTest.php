<?php

namespace Tests\Feature\Credit;

use App\Constants\PaymentTerms;
use App\Models\BuyerSupplierRelationship;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: Company, 2: BuyerSupplierRelationship, 3: User}
     */
    private function makeScenario(float $balance = 0, ?float $creditLimit = 1000): array
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $relationship = BuyerSupplierRelationship::factory()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'payment_terms' => PaymentTerms::NET_30,
            'credit_limit' => $creditLimit,
            'current_balance' => $balance,
            'is_approved' => true,
        ]);
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();

        return [$supplier, $buyer, $relationship, $supplierUser];
    }

    public function test_positive_adjustment_increases_outstanding_balance(): void
    {
        [, , $relationship, $supplierUser] = $this->makeScenario(balance: 100);

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/buyer-supplier-relationships/{$relationship->id}/adjust-credit", [
            'amount' => 50,
            'reason' => 'Extra delivered goods',
        ])->assertOk();

        $this->assertEqualsWithDelta(150.0, (float) $relationship->fresh()->current_balance, 0.001);
    }

    public function test_negative_adjustment_decreases_balance(): void
    {
        [, , $relationship, $supplierUser] = $this->makeScenario(balance: 200);

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/buyer-supplier-relationships/{$relationship->id}/adjust-credit", [
            'amount' => -150,
            'reason' => 'Payment received off-platform',
        ])->assertOk();

        $this->assertEqualsWithDelta(50.0, (float) $relationship->fresh()->current_balance, 0.001);
    }

    public function test_adjustment_below_zero_is_rejected_with_422(): void
    {
        [, , $relationship, $supplierUser] = $this->makeScenario(balance: 100);

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/buyer-supplier-relationships/{$relationship->id}/adjust-credit", [
            'amount' => -500,
            'reason' => 'Too much',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEqualsWithDelta(100.0, (float) $relationship->fresh()->current_balance, 0.001);
    }

    public function test_adjustment_exceeding_credit_limit_is_rejected_with_422(): void
    {
        [, , $relationship, $supplierUser] = $this->makeScenario(balance: 900, creditLimit: 1000);

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/buyer-supplier-relationships/{$relationship->id}/adjust-credit", [
            'amount' => 500,
            'reason' => 'Over the limit',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEqualsWithDelta(900.0, (float) $relationship->fresh()->current_balance, 0.001);
    }

    public function test_buyer_cannot_adjust_own_credit(): void
    {
        [$supplier, $buyer, $relationship] = $this->makeScenario();
        $buyerUser = User::factory()->buyer()->forCompany($buyer)->create();

        Sanctum::actingAs($buyerUser);

        $this->postJson("/api/v1/buyer-supplier-relationships/{$relationship->id}/adjust-credit", [
            'amount' => -10,
            'reason' => 'Self service discount',
        ])->assertForbidden();
    }
}
