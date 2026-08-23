<?php

namespace Tests\Feature\Users;

use App\Constants\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_users_with_search(): void
    {
        $admin = $this->actingAsAdmin();
        $needle = User::factory()->create(['name' => 'Ziad Alhakim']);

        $response = $this->getJson('/api/v1/users?search=Ziad')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($needle->id, $ids);
        $this->assertNotContains($admin->id, $ids);
    }

    public function test_non_admin_is_forbidden_from_user_management(): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->getJson('/api/v1/users')->assertForbidden();

        // Even a fully valid creation payload is blocked by the admin-only policy.
        $this->postJson('/api/v1/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret1234',
            'role' => UserRole::ADMIN,
        ])->assertForbidden();
    }

    public function test_admin_creates_buyer_with_company(): void
    {
        $this->actingAsAdmin();
        $company = Company::factory()->pharmacy()->create();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Buyer',
            'email' => 'new-buyer@example.com',
            'password' => 'secret1234',
            'role' => UserRole::BUYER,
            'company_id' => $company->id,
        ])->assertCreated();

        $this->assertSame($company->id, $response->json('data.company.id'));
        $user = User::query()->where('email', 'new-buyer@example.com')->firstOrFail();
        $this->assertSame(UserRole::BUYER, $user->role);
    }

    public function test_company_requiring_role_without_company_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/users', [
            'name' => 'No Company',
            'email' => 'no-company@example.com',
            'password' => 'secret1234',
            'role' => UserRole::DRIVER,
        ])->assertStatus(422);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->actingAsAdmin();
        $existing = User::factory()->create();

        $this->postJson('/api/v1/users', [
            'name' => 'Dup',
            'email' => $existing->email,
            'password' => 'secret1234',
            'role' => UserRole::ADMIN,
        ])->assertStatus(422);
    }

    public function test_admin_can_change_role_and_company(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()->buyer()->create();
        $supplier = Company::factory()->supplier()->create();

        $this->putJson("/api/v1/users/{$target->id}", [
            'role' => UserRole::SALESMAN,
            'company_id' => $supplier->id,
        ])->assertOk();

        $target->refresh();
        $this->assertSame(UserRole::SALESMAN, $target->role);
        $this->assertSame($supplier->id, $target->company_id);
    }

    public function test_cannot_delete_own_account(): void
    {
        $admin = $this->actingAsAdmin();

        $this->deleteJson("/api/v1/users/{$admin->id}")->assertStatus(422);
        $this->assertNull($admin->fresh()->deleted_at);
    }

    public function test_sole_admin_cannot_demote_themselves(): void
    {
        $admin = $this->actingAsAdmin();
        // Ensure exactly one admin exists in this test's world.
        User::query()->where('role', UserRole::ADMIN)->whereKeyNot($admin->getKey())->delete();

        $this->putJson("/api/v1/users/{$admin->getKey()}", [
            'role' => UserRole::BUYER,
        ])->assertStatus(422);

        $this->assertSame(UserRole::ADMIN, $admin->fresh()->role);
    }

    public function test_admins_can_be_deleted_while_others_remain(): void
    {
        $admin = $this->actingAsAdmin();
        $otherAdmin = User::factory()->admin()->create();
        $thirdAdmin = User::factory()->admin()->create();

        $this->deleteJson("/api/v1/users/{$otherAdmin->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $otherAdmin->id]);
        unset($thirdAdmin);
    }

    public function test_delete_soft_deletes_and_revokes_access(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()->driver()->create();

        $this->deleteJson("/api/v1/users/{$target->id}")->assertOk();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }
}
