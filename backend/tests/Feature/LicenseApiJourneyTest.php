<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseApiJourneyTest extends TestCase
{
    use RefreshDatabase;

    private string $secretPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->secretPath = tempnam(sys_get_temp_dir(), 'pro-one-api-key-');
        $keypair = sodium_crypto_sign_keypair();
        file_put_contents($this->secretPath, base64_encode(sodium_crypto_sign_secretkey($keypair)));
        config([
            'control_center.signing.key_id' => 'test-key-1',
            'control_center.signing.private_key_path' => $this->secretPath,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->secretPath) && is_file($this->secretPath)) {
            unlink($this->secretPath);
        }

        parent::tearDown();
    }

    public function test_owner_uses_the_api_to_register_approve_issue_and_download(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner);

        $customerId = $this->postJson('/api/v1/customers', [
            'reference' => 'CUST-API-100',
            'legal_name' => 'شركة واجهة React',
            'display_name' => 'عميل React',
            'contract_state' => 'active',
            'contacts' => [],
        ])->assertCreated()->json('data.id');

        $installationId = $this->postJson('/api/v1/installations', [
            'customer_id' => $customerId,
            'installation_uuid' => '750e8400-e29b-41d4-a716-446655440000',
            'site_label' => 'خادم React التجريبي',
            'deployment_mode' => 'offline',
            'activation_state' => 'approved',
        ])->assertCreated()->assertJsonPath('data.activation_state', 'pending')->json('data.id');

        $this->postJson("/api/v1/installations/{$installationId}/approve")
            ->assertOk()
            ->assertJsonPath('data.activation_state', 'approved');

        $this->getJson('/api/v1/features')
            ->assertOk()
            ->assertJsonFragment(['key' => 'system.onboarding']);

        $licenseId = $this->postJson("/api/v1/installations/{$installationId}/licenses", [
            'edition' => 'pro',
            'features' => ['system.onboarding', 'mobile.remote_access'],
            'reason' => 'إصدار أولي من واجهة React',
        ])->assertCreated()
            ->assertJsonPath('data.sequence', 1)
            ->assertJsonMissing(['signed_document'])
            ->json('data.id');

        $this->getJson('/api/v1/licenses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.installation.id', $installationId);

        $this->get("/api/v1/licenses/{$licenseId}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8');
    }
}
