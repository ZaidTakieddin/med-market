<?php

namespace Tests\Feature\Security;

use App\Models\BuyerSupplierRelationship;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_adjustments_are_audited(): void
    {
        [$relationship, $supplierUser] = $this->makeScenario(balance: 100);

        Sanctum::actingAs($supplierUser);

        $this->postJson("/api/v1/buyer-supplier-relationships/{$relationship->id}/adjust-credit", [
            'amount' => 50,
            'reason' => 'Extra goods',
        ])->assertOk();

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/audit-logs?action=credit.balance.adjusted')->assertOk();

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(50.0, $entry['properties']['adjustment'], 0.001);
        $this->assertNotNull($entry['user']);
    }

    public function test_audit_logs_are_admin_only(): void
    {
        Sanctum::actingAs(User::factory()->supplier()->create());

        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    /**
     * @return array{0: BuyerSupplierRelationship, 1: User}
     */
    private function makeScenario(float $balance): array
    {
        $supplier = Company::factory()->supplier()->create();
        $buyer = Company::factory()->pharmacy()->create();
        $relationship = BuyerSupplierRelationship::factory()->create([
            'buyer_company_id' => $buyer->id,
            'supplier_company_id' => $supplier->id,
            'credit_limit' => 1000,
            'current_balance' => $balance,
            'is_approved' => true,
        ]);
        $supplierUser = User::factory()->supplier()->forCompany($supplier)->create();

        return [$relationship, $supplierUser];
    }
}
