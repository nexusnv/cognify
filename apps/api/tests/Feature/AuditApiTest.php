<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_creates_append_only_tenant_scoped_event_with_context(): void
    {
        [$tenant, $actor] = $this->tenantUser('buyer');
        $requisition = $this->requisition($tenant, $actor);

        $event = app(AuditRecorder::class)->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'requisition.submitted',
            subject: $requisition,
            metadata: ['status' => 'submitted'],
            before: ['status' => 'draft'],
            after: ['status' => 'submitted'],
            subjectDisplay: $requisition->number,
        ));

        $this->assertDatabaseHas('audit_events', [
            'id' => $event->id,
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => 'requisition.submitted',
            'action' => 'requisition.submitted',
            'subject_display' => $requisition->number,
        ]);

        $this->assertNotEmpty($event->event_id);
        $this->assertSame(['status' => 'draft'], $event->before);
        $this->assertSame(['status' => 'submitted'], $event->after);
    }

    public function test_audit_events_are_append_only(): void
    {
        [$tenant, $actor] = $this->tenantUser('admin');
        $event = AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => 'tenant.updated',
            'action' => 'tenant.updated',
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
            'subject_display' => $tenant->name,
            'metadata' => ['field' => 'name'],
            'occurred_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $event->update(['metadata' => ['field' => 'changed']]);
    }

    public function test_audit_events_cannot_be_deleted(): void
    {
        [$tenant, $actor] = $this->tenantUser('admin');
        $event = AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => 'tenant.updated',
            'action' => 'tenant.updated',
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
            'subject_display' => $tenant->name,
            'metadata' => ['field' => 'name'],
            'occurred_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $event->delete();
    }

    public function test_recorder_redacts_sensitive_snapshot_values(): void
    {
        [$tenant, $actor] = $this->tenantUser('buyer');
        $requisition = $this->requisition($tenant, $actor);

        $event = app(AuditRecorder::class)->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'requisition.updated',
            subject: $requisition,
            metadata: ['apiToken' => 'secret-token', 'status' => 'draft'],
            before: ['password' => 'secret', 'nested' => ['refresh_token' => 'refresh']],
            after: ['title' => 'Laptop refresh', 'accessToken' => 'access'],
            subjectDisplay: $requisition->number,
        ));

        $this->assertSame('[redacted]', $event->metadata['apiToken']);
        $this->assertSame('draft', $event->metadata['status']);
        $this->assertSame('[redacted]', $event->before['password']);
        $this->assertSame('[redacted]', $event->before['nested']['refresh_token']);
        $this->assertSame('[redacted]', $event->after['accessToken']);
    }

    public function test_admin_can_query_tenant_audit_feed(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        $requisition = $this->requisition($tenant, $admin);
        $this->audit($tenant, $admin, $requisition, 'requisition.created');
        $this->audit($tenant, $admin, $requisition, 'requisition.submitted');

        $response = $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/audit/events?action=requisition.submitted&subjectType=requisition&perPage=50');

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'requisition.submitted')
            ->assertJsonPath('data.0.subject.type', 'requisition')
            ->assertJsonPath('data.0.subject.id', (string) $requisition->id)
            ->assertJsonPath('meta.perPage', 50);
    }

    public function test_audit_feed_date_filters_include_full_day_bounds(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        $requisition = $this->requisition($tenant, $admin);

        $this->audit($tenant, $admin, $requisition, 'requisition.previous', '2026-05-11 23:59:59');
        $this->audit($tenant, $admin, $requisition, 'requisition.same_day', '2026-05-12 23:59:59');
        $this->audit($tenant, $admin, $requisition, 'requisition.next_day', '2026-05-13 00:00:00');

        $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/audit/events?occurredFrom=2026-05-12&occurredTo=2026-05-12&perPage=50')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'requisition.same_day');
    }

    public function test_requester_cannot_query_platform_audit_feed(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/audit/events')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_audit_feed_is_tenant_scoped(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [$otherTenant, $otherAdmin] = $this->tenantUser('admin');
        $this->audit($tenant, $admin, $this->requisition($tenant, $admin), 'requisition.created');
        $this->audit($otherTenant, $otherAdmin, $this->requisition($otherTenant, $otherAdmin), 'requisition.submitted');

        $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/audit/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'requisition.created');
    }

    public function test_requisition_activity_uses_shared_audit_resource_shape(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->requisition($tenant, $requester);
        $this->audit($tenant, $requester, $requisition, 'requisition.updated');

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'requisition.updated')
            ->assertJsonPath('data.0.subject.type', 'requisition')
            ->assertJsonPath('data.0.actor.id', (string) $requester->id);
    }

    public function test_invalid_request_id_header_is_replaced(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');

        $response = $this->actingAsTenant($tenant, $admin)
            ->withHeader('X-Request-Id', "bad\nheader")
            ->getJson('/api/audit/events');

        $response->assertOk();
        $this->assertNotSame("bad\nheader", $response->headers->get('X-Request-Id'));
        $this->assertStringStartsWith('req_', (string) $response->headers->get('X-Request-Id'));
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function requisition(Tenant $tenant, User $requester): Requisition
    {
        return Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-000001',
            'title' => 'Laptop refresh',
            'business_justification' => 'Replace aging laptops.',
            'needed_by_date' => '2026-07-15',
            'currency' => 'USD',
            'status' => RequisitionStatus::Draft,
        ]);
    }

    private function audit(
        Tenant $tenant,
        User $actor,
        Requisition $subject,
        string $action,
        ?string $occurredAt = null,
    ): AuditEvent {
        return AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => $action,
            'action' => $action,
            'subject_type' => Requisition::class,
            'subject_id' => $subject->id,
            'subject_display' => $subject->number,
            'metadata' => ['status' => $subject->status->value],
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
