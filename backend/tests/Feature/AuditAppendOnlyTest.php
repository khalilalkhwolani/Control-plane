<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ControlAuditEvent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_audit_event_cannot_be_updated_or_deleted(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner);
        $this->postJson('/api/v1/customers', [
            'reference' => 'CUST-009',
            'legal_name' => 'شركة الاختبار',
            'display_name' => 'اختبار',
            'contract_state' => 'prospect',
        ])->assertCreated();

        $event = ControlAuditEvent::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $event->update(['action' => 'tampered']);
    }

    public function test_audit_event_cannot_be_deleted(): void
    {
        $event = ControlAuditEvent::query()->create([
            'action' => 'test.event',
            'request_id' => 'req-1',
            'risk' => 'low',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_auditor_can_filter_audit_log(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAsVerified($owner);
        $this->postJson('/api/v1/customers', [
            'reference' => 'CUST-010',
            'legal_name' => 'شركة التدقيق',
            'display_name' => 'التدقيق',
            'contract_state' => 'active',
        ])->assertCreated();

        $auditor = $this->makeUser('auditor', ['email' => 'auditor@proone.test']);
        $this->actingAsVerified($auditor);

        $this->getJson('/api/v1/audit-events?action=customers.created')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_release_manager_cannot_read_audit(): void
    {
        $manager = $this->makeUser('release_manager');
        $this->actingAsVerified($manager);

        $this->getJson('/api/v1/audit-events')->assertForbidden();
    }
}
