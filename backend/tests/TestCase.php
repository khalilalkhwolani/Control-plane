<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Role;
use App\Models\VendorUser;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const TEST_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sanctum.stateful' => ['localhost', 'localhost:5190', '127.0.0.1'],
            'control_center.mfa_required' => true,
            'control_center.mfa_step_up_seconds' => 300,
        ]);
        $this->withHeaders([
            'Origin' => 'http://localhost:5190',
            'Referer' => 'http://localhost:5190/',
        ]);
    }

    protected function makeUser(string $roleKey = 'owner', array $overrides = [], bool $enrollMfa = true): VendorUser
    {
        $role = Role::query()->where('key', $roleKey)->firstOrFail();
        $user = VendorUser::factory()->create(array_merge([
            'role_id' => $role->id,
            'password' => 'password12chars',
        ], $overrides));

        if ($enrollMfa) {
            $user->forceFill([
                'mfa_secret' => app(TotpService::class)->encryptSecret(self::TEST_TOTP_SECRET),
                'mfa_confirmed_at' => now(),
            ])->save();
        }

        return $user->fresh(['role.permissions']);
    }

    protected function actingAsVerified(VendorUser $user, ?int $verifiedAt = null): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user)->withSession([
            'mfa.verified_at' => $verifiedAt ?? now()->timestamp,
        ]);
    }

    protected function currentOtp(): string
    {
        return app(TotpService::class)->currentOtp(self::TEST_TOTP_SECRET);
    }
}
