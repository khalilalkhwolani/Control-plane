<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAndRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_auditor_cannot_create_customers(): void
    {
        $auditor = $this->makeUser('auditor');
        $this->actingAsVerified($auditor);

        $this->postJson('/api/v1/customers', $this->customerPayload())
            ->assertForbidden();
    }

    public function test_support_engineer_cannot_write_installations(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner);
        $customer = $this->createCustomerViaApi();

        $support = $this->makeUser('support_engineer', ['email' => 'support@proone.test']);
        $this->actingAsVerified($support);

        $this->postJson('/api/v1/installations', [
            'customer_id' => $customer['id'],
            'installation_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'site_label' => 'المحل',
            'deployment_mode' => 'offline',
            'activation_state' => 'pending',
        ])->assertForbidden();
    }

    public function test_owner_registers_customer_and_installation(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner);

        $create = $this->postJson('/api/v1/customers', $this->customerPayload())->assertCreated();
        $customerId = $create->json('data.id');

        $this->getJson('/api/v1/customers?search=النور&contract_state=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->postJson('/api/v1/installations', [
            'customer_id' => $customerId,
            'installation_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'site_label' => 'الفرع الرئيسي',
            'deployment_mode' => 'offline',
            'activation_state' => 'pending',
            'reason' => 'تفعيل أولي',
        ])->assertCreated();

        $this->getJson('/api/v1/installations?activation_state=pending&deployment_mode=offline')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/overview')
            ->assertOk()
            ->assertJsonPath('data.customers_count', 1)
            ->assertJsonPath('data.pending_activations', 1)
            ->assertJsonPath('data.expiring_licenses', 0);

        $this->assertDatabaseHas('control_audit_events', ['action' => 'customers.created']);
        $this->assertDatabaseHas('control_audit_events', ['action' => 'installations.created']);
    }

    public function test_owner_keeps_full_access_when_permission_pivot_is_out_of_date(): void
    {
        $owner = $this->makeUser('owner');
        $owner->role->permissions()->detach();
        $this->actingAsVerified($owner->fresh('role'));

        $this->getJson('/api/v1/overview')->assertOk();
        $this->postJson('/api/v1/customers', $this->customerPayload())->assertCreated();
    }

    public function test_stale_mfa_requires_step_up_for_writes(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner, now()->subMinutes(10)->timestamp);

        $this->postJson('/api/v1/customers', $this->customerPayload())
            ->assertForbidden()
            ->assertJsonPath('code', 'mfa_step_up_required');

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_license_admin_cannot_create_users(): void
    {
        $admin = $this->makeUser('license_administrator');
        $this->actingAsVerified($admin);

        $this->postJson('/api/v1/users', [
            'name' => 'مدقق',
            'email' => 'auditor2@proone.test',
            'password' => 'password12chars',
            'password_confirmation' => 'password12chars',
            'role' => 'auditor',
        ])->assertForbidden();
    }

    public function test_owner_can_create_non_owner_user(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner);

        $this->postJson('/api/v1/users', [
            'name' => 'مسؤول تراخيص',
            'email' => 'licenses@proone.test',
            'password' => 'password12chars',
            'password_confirmation' => 'password12chars',
            'role' => 'license_administrator',
        ])->assertCreated()->assertJsonPath('data.role', 'license_administrator');
    }

    public function test_owner_cannot_assign_owner_role_via_api(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner);

        $this->postJson('/api/v1/users', [
            'name' => 'مالك ثاني',
            'email' => 'owner2@proone.test',
            'password' => 'password12chars',
            'password_confirmation' => 'password12chars',
            'role' => 'owner',
        ])->assertStatus(422);
    }

    public function test_filters_reset_pagination_semantics_return_empty_when_unmatched(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner);
        $this->postJson('/api/v1/customers', $this->customerPayload())->assertCreated();

        $this->getJson('/api/v1/customers?search=لا-يوجد&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(): array
    {
        return [
            'reference' => 'CUST-001',
            'legal_name' => 'متجر النور للتجارة',
            'display_name' => 'متجر النور',
            'contract_state' => 'active',
            'notes' => 'عميل تجريبي',
            'reason' => 'تسجيل أولي',
            'contacts' => [
                [
                    'name' => 'أحمد',
                    'email' => 'ahmed@example.com',
                    'phone' => '777000111',
                    'is_primary' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCustomerViaApi(): array
    {
        return $this->postJson('/api/v1/customers', $this->customerPayload())->json('data');
    }
}
