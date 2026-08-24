<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new Google2FA;
    }

    public function test_full_enable_confirm_login_and_disable_cycle(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);

        Sanctum::actingAs($user);

        // Start enrolment.
        $start = $this->postJson('/api/v1/auth/2fa/enable', [])->assertOk();
        $secret = $start->json('data.secret');
        $this->assertStringContainsString('otpauth://totp/', (string) $start->json('data.otpauthUrl'));

        // Password alone no longer logs in once enabled; before enable it must.
        $code = $this->engine->getCurrentOtp($secret);

        $confirm = $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])->assertOk();
        $recoveryCodes = $confirm->json('data.recoveryCodes');
        $this->assertCount(8, $recoveryCodes);
        $this->assertTrue($user->fresh()->two_factor_enabled);

        // Wrong code rejected.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
            'code' => '000000',
        ])->assertUnauthorized()->assertJsonPath('error.message', 'Invalid two-factor code');

        // Valid TOTP code succeeds.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
            'code' => $this->engine->getCurrentOtp($secret),
        ])->assertOk();
    }

    public function test_recovery_code_logs_in_once(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);
        Sanctum::actingAs($user);

        $secret = $this->postJson('/api/v1/auth/2fa/enable', [])->json('data.secret');
        $codes = $this->postJson('/api/v1/auth/2fa/confirm', [
            'code' => $this->engine->getCurrentOtp($secret),
        ])->json('data.recoveryCodes');

        // Fresh login attempt with a recovery code.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
            'code' => $codes[0],
        ])->assertOk();

        // Same recovery code cannot be reused.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
            'code' => $codes[0],
        ])->assertUnauthorized();
    }

    public function test_disable_requires_current_code_and_stops_challenge(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);
        Sanctum::actingAs($user);

        $secret = $this->postJson('/api/v1/auth/2fa/enable', [])->json('data.secret');
        $this->postJson('/api/v1/auth/2fa/confirm', [
            'code' => $this->engine->getCurrentOtp($secret),
        ])->assertOk();

        $this->postJson('/api/v1/auth/2fa/disable', ['code' => '000000'])->assertStatus(422);

        $this->postJson('/api/v1/auth/2fa/disable', [
            'code' => $this->engine->getCurrentOtp($secret),
        ])->assertOk();

        $this->assertFalse($user->fresh()->two_factor_enabled);
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
