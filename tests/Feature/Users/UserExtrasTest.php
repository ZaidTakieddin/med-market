<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_records_last_login_at(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);
        $this->assertNull($user->last_login_at);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_bulk_deactivate_skips_self_and_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->driver()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users/bulk-status', [
            'action' => 'deactivate',
            'user_ids' => [$target->id, $admin->id, 999999],
        ])->assertOk();

        $result = $response->json('data');
        $this->assertSame([$target->id], $result['deactivated']);
        $this->assertCount(2, $result['skipped']);
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_bulk_activate_restores_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $deactivated = User::factory()->driver()->create();
        $deactivated->delete();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/users/bulk-status', [
            'action' => 'activate',
            'user_ids' => [$deactivated->id],
        ])->assertOk();

        $this->assertNull($deactivated->fresh()->deleted_at);
    }

    public function test_export_streams_csv_for_admins_only(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->buyer()->create(['name' => 'Ziad Export']);

        // Non-admin forbidden.
        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->getJson('/api/v1/users/export')->assertForbidden();

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/users/export')->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Ziad Export', $content);
        unset($target);
    }
}
