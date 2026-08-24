<?php

namespace App\Domain\Auth;

use App\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        private Google2FA $engine
    ) {}

    /**
     * Start enrolment: generate (but do not activate) a secret for the user and
     * return it with a ready-to-render otpauth:// URI.
     *
     * @return array{secret: string, otpauthUrl: string}
     */
    public function start(User $user): array
    {
        $secret = $this->engine->generateSecretKey(32);

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled' => false,
            'two_factor_recovery_codes' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauthUrl' => $this->engine->getQRCodeUrl(
                config('app.name'),
                (string) $user->email,
                $secret,
            ),
        ];
    }

    /**
     * Confirm enrolment by verifying the first code from the authenticator app.
     * Generates single-use recovery codes (returned in plain text exactly once).
     *
     * @return list<string>
     *
     * @throws DomainException When there is no pending enrolment or the code is invalid
     */
    public function confirm(User $user, string $code): array
    {
        $secret = $this->secretFor($user);

        if ($secret === null) {
            throw new DomainException('Two-factor enrolment has not been started.');
        }

        if (! $this->verifyCode($secret, $code)) {
            throw new DomainException('The provided two-factor code is invalid.');
        }

        $plainCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => json_encode(
                array_map(fn ($c) => Hash::make($c), $plainCodes)
            ),
        ])->save();

        return $plainCodes;
    }

    /**
     * Turn 2FA off after verifying a current code.
     *
     * @throws DomainException When the code is invalid
     */
    public function disable(User $user, string $code): void
    {
        $secret = $this->secretFor($user);

        if ($secret === null || ! $this->verifyCode($secret, $code)) {
            throw new DomainException('The provided two-factor code is invalid.');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    /**
     * Verify a login-time second factor: accepts a TOTP code or an unused
     * recovery code (which is then consumed).
     */
    public function verifyLoginChallenge(User $user, string $code): bool
    {
        if (! $user->two_factor_enabled) {
            return true;
        }

        $secret = $this->secretFor($user);

        if ($secret !== null && $this->verifyCode($secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * @throws DomainException When 2FA is enabled but no code was supplied
     */
    public function assertChallengeSatisfied(User $user, ?string $code): void
    {
        if (! $user->two_factor_enabled) {
            return;
        }

        if ($code === null || trim($code) === '') {
            throw new DomainException('Two-factor authentication code is required.');
        }
    }

    private function secretFor(User $user): ?string
    {
        $stored = $user->two_factor_secret;

        return $stored !== null ? decrypt($stored) : null;
    }

    private function verifyCode(string $secret, string $code): bool
    {
        // ±1 window tolerates clock drift between devices.
        return (bool) $this->engine->verifyKey($secret, (string) preg_replace('/\s+/', '', $code), 1);
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }

        return $codes;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashed = $user->two_factor_recovery_codes !== null
            ? json_decode($user->two_factor_recovery_codes, true)
            : [];

        if (! is_array($hashed)) {
            return false;
        }

        $normalized = (string) strtoupper((string) preg_replace('/[\s-]+/', '', $code));

        foreach ($hashed as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                unset($hashed[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => json_encode(array_values($hashed)),
                ])->save();

                return true;
            }
        }

        return false;
    }
}
