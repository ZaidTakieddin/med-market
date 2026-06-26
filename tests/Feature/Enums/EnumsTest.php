<?php

namespace Tests\Feature\Enums;

use App\Constants\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnumsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_all_enum_types(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/enums')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'offerType', 'orderStatus', 'userRole', 'companyType',
                    'supplierClassification', 'buyerClassification', 'pricingType',
                    'paymentTerms', 'stockMovementType', 'discountType',
                ],
            ]);
    }

    public function test_filters_to_a_single_enum_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/enums?type=orderStatus')->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('orderStatus', $data);
        $this->assertArrayNotHasKey('userRole', $data);
        $this->assertEqualsCanonicalizing(OrderStatus::all(), $data['orderStatus']);
    }

    public function test_enums_require_authentication(): void
    {
        $this->getJson('/api/v1/enums')->assertUnauthorized();
    }
}
