<?php

namespace Tests\Feature\Auth;

use App\Constants\BuyerClassification;
use App\Constants\CompanyType;
use App\Constants\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Pharmacist',
            'email' => 'pharmacist@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'City Pharmacy',
        ], $overrides);
    }

    public function test_public_registration_creates_a_buyer_with_its_own_company(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', UserRole::BUYER);

        $user = User::where('email', 'pharmacist@example.com')->firstOrFail();
        $this->assertSame(UserRole::BUYER, $user->role);
        $this->assertNotNull($user->company_id);

        $company = Company::findOrFail($user->company_id);
        $this->assertSame(CompanyType::BUYER, $company->type);
        $this->assertSame(BuyerClassification::PHARMACY, $company->buyer_classification);
        $this->assertSame('City Pharmacy', $company->name);
    }

    public function test_registration_ignores_client_supplied_role(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['role' => UserRole::ADMIN]))
            ->assertCreated();

        $user = User::where('email', 'pharmacist@example.com')->firstOrFail();
        $this->assertSame(UserRole::BUYER, $user->role, 'A client-supplied role must never be honored.');
    }

    public function test_registration_ignores_client_supplied_company_id(): void
    {
        $existing = Company::create([
            'name' => 'Existing Competitor Co',
            'type' => CompanyType::SUPPLIER,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/register', $this->payload(['company_id' => $existing->id]))
            ->assertCreated();

        $user = User::where('email', 'pharmacist@example.com')->firstOrFail();
        $this->assertNotSame($existing->id, $user->company_id, 'Registrant must not be able to join an arbitrary existing company.');
        $this->assertSame(CompanyType::BUYER, $user->company->type, 'Registrant must own a new buyer company, not the existing supplier.');
    }

    public function test_registration_requires_company_name(): void
    {
        $payload = $this->payload();
        unset($payload['company_name']);

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
