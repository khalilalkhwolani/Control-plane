<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ControlAuditEvent;
use App\Models\VendorUser;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_requires_mfa_enrollment_for_new_owner(): void
    {
        $this->makeUser('owner', ['email' => 'owner@proone.test'], false);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@proone.test',
            'password' => 'password12chars',
        ])->assertOk()->assertJsonPath('status', 'mfa_enrollment_required');

        $this->getJson('/api/v1/overview')->assertUnauthorized();
    }

    public function test_enrollment_and_challenge_grant_access(): void
    {
        $this->makeUser('owner', ['email' => 'owner@proone.test'], false);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@proone.test',
            'password' => 'password12chars',
        ])->assertOk();

        $start = $this->postJson('/api/v1/auth/mfa/enroll/start')->assertOk();
        $secret = $start->json('secret');
        $this->assertNotEmpty($secret);
        $this->assertStringStartsWith('otpauth://totp/', (string) $start->json('otpauth_uri'));

        $code = app(\App\Services\TotpService::class)->currentOtp($secret);
        $confirm = $this->postJson('/api/v1/auth/mfa/enroll/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.email', 'owner@proone.test');

        $this->assertCount(8, $confirm->json('recovery_codes'));
        $this->getJson('/api/v1/overview')->assertOk();
        $this->assertDatabaseHas('control_audit_events', ['action' => 'auth.mfa_enrolled']);
    }

    public function test_invalid_password_is_rejected_and_does_not_authenticate(): void
    {
        $this->makeUser('owner', ['email' => 'owner@proone.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@proone.test',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->getJson('/api/v1/overview')->assertUnauthorized();
    }

    public function test_mfa_challenge_succeeds_with_totp(): void
    {
        $this->makeUser('owner', ['email' => 'owner@proone.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@proone.test',
            'password' => 'password12chars',
        ])->assertJsonPath('status', 'mfa_required');

        $this->postJson('/api/v1/auth/mfa/challenge', [
            'code' => $this->currentOtp(),
        ])->assertOk();

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.role', 'owner');
    }

    public function test_lockout_after_repeated_failures(): void
    {
        $this->makeUser('owner', ['email' => 'owner@proone.test']);
        config(['control_center.lockout_max_attempts' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'owner@proone.test',
                'password' => 'bad-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@proone.test',
            'password' => 'password12chars',
        ])->assertStatus(429);
    }

    public function test_create_owner_command_creates_single_owner(): void
    {
        $this->artisan('control-center:create-owner', [
            'email' => 'first@proone.test',
            '--name' => 'المالك',
            '--password' => 'owner-password-1',
        ])->assertSuccessful();

        $this->assertDatabaseHas('vendor_users', ['email' => 'first@proone.test']);
        $this->assertTrue(VendorUser::query()->first()?->isOwner());

        $this->artisan('control-center:create-owner', [
            'email' => 'second@proone.test',
            '--password' => 'owner-password-1',
        ])->assertFailed();
    }
}
