<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Installation;
use App\Models\LicenseRevision;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLicenseJourneyTest extends TestCase
{
    use RefreshDatabase;

    private string $secretPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->secretPath = tempnam(sys_get_temp_dir(), 'pro-one-test-key-');
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

    public function test_laravel_root_redirects_to_the_react_control_center(): void
    {
        config(['control_center.frontend_url' => 'http://127.0.0.1:5190']);

        $this->get('/')->assertRedirect('http://127.0.0.1:5190');
        $this->get('/login')->assertRedirect('http://127.0.0.1:5190/login');
    }

    public function test_owner_completes_customer_installation_and_license_journey(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAs($owner);

        $this->post('/customers', [
            'reference' => 'CUST-100',
            'legal_name' => 'شركة الاختبار',
            'display_name' => 'عميل الاختبار',
        ])->assertRedirect();

        $customer = Customer::query()->firstOrFail();
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->post('/installations', [
            'customer_id' => $customer->id,
            'installation_uuid' => $uuid,
            'site_label' => 'الخادم المحلي',
            'deployment_mode' => 'offline',
        ])->assertRedirect();

        $installation = Installation::query()->firstOrFail();
        $this->assertSame('pending', $installation->activation_state);
        $this->post("/installations/{$installation->id}/approve")->assertRedirect();

        $this->post("/installations/{$installation->id}/license", [
            'edition' => 'pro',
            'features' => ['system.onboarding', 'mobile.remote_access'],
            'reason' => 'إصدار أولي وفق عقد العميل',
        ])->assertRedirect("/installations/{$installation->id}/license");

        $revision = LicenseRevision::query()->firstOrFail();
        $this->assertSame(1, $revision->sequence);
        $this->assertSame(['mobile.remote_access', 'system.onboarding'], $revision->features);
        $this->assertStringContainsString('superpro-license-envelope/v1', $revision->getRawOriginal('signed_document'));
        $this->assertDatabaseHas('control_audit_events', ['action' => 'licenses.issued']);

        $this->get("/licenses/{$revision->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8');
    }

    public function test_license_cannot_be_issued_before_installation_approval(): void
    {
        $owner = $this->makeUser('owner');
        $customer = Customer::query()->create([
            'reference' => 'CUST-200', 'legal_name' => 'عميل', 'display_name' => 'عميل',
            'contract_state' => 'active', 'created_by' => $owner->id, 'updated_by' => $owner->id,
        ]);
        $installation = Installation::query()->create([
            'customer_id' => $customer->id,
            'installation_uuid' => '650e8400-e29b-41d4-a716-446655440000',
            'site_label' => 'محلي', 'deployment_mode' => 'offline', 'activation_state' => 'pending',
            'created_by' => $owner->id, 'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)->from("/installations/{$installation->id}/license")
            ->post("/installations/{$installation->id}/license", [
                'edition' => 'pro', 'features' => ['system.onboarding'], 'reason' => 'محاولة قبل الاعتماد',
            ])->assertRedirect()->assertSessionHasErrors('license');

        $this->assertDatabaseCount('license_revisions', 0);
    }
}
