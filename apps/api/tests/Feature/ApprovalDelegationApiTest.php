<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalDelegationStatus;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalDelegationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approver_can_create_active_delegation_for_same_tenant_user(): void
    {
        [$tenant, $approver] = $this->tenantUser('approver');
        [, $delegate] = $this->tenantUser('approver', $tenant);

        $response = $this->actingAsTenant($tenant, $approver)
            ->postJson('/api/approval-delegations', [
                'delegateId' => $delegate->id,
                'scope' => 'all',
                'startsAt' => now()->toISOString(),
                'endsAt' => now()->addDay()->toISOString(),
                'reason' => 'Out of office.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.tenantId', (string) $tenant->id)
            ->assertJsonPath('data.delegatorId', (string) $approver->id)
            ->assertJsonPath('data.delegateId', (string) $delegate->id)
            ->assertJsonPath('data.scope', 'all')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.reason', 'Out of office.');

        $this->assertDatabaseHas('approval_delegations', [
            'tenant_id' => $tenant->id,
            'delegator_id' => $approver->id,
            'delegate_id' => $delegate->id,
            'scope' => 'all',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $approver->id,
            'event_type' => 'approval_delegation.created',
        ]);
    }

    public function test_delegate_can_act_on_delegated_task(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $delegate] = $this->tenantUser('approver', $tenant);
        $requisition = $this->routeRequisition($tenant, $requester, $approver);
        $task = ApprovalTask::query()->firstOrFail();

        $delegation = $this->actingAsTenant($tenant, $approver)
            ->postJson('/api/approval-delegations', [
                'delegateId' => $delegate->id,
                'scope' => 'task_specific',
                'startsAt' => now()->toISOString(),
                'endsAt' => now()->addDay()->toISOString(),
                'reason' => 'Covering a meeting.',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/delegate", [
                'approvalDelegationId' => $delegation['id'],
                'lockVersion' => $task->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', (string) $delegate->id)
            ->assertJsonPath('data.originalAssignee.id', (string) $approver->id);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $approver->id,
            'event_type' => 'approval_task.delegated',
        ]);

        $delegatedTask = ApprovalTask::query()->firstOrFail();

        $this->actingAsTenant($tenant, $delegate)
            ->postJson("/api/approval-tasks/{$delegatedTask->id}/approve", [
                'lockVersion' => $delegatedTask->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame(RequisitionStatus::Approved, $requisition->refresh()->status);
    }

    public function test_expired_delegation_cannot_act_on_task(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $delegate] = $this->tenantUser('approver', $tenant);
        $this->routeRequisition($tenant, $requester, $approver);
        $task = ApprovalTask::query()->firstOrFail();

        $delegation = ApprovalDelegation::query()->create([
            'tenant_id' => $tenant->id,
            'delegator_id' => $approver->id,
            'delegate_id' => $delegate->id,
            'scope' => 'task_specific',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subMinute(),
            'status' => ApprovalDelegationStatus::Active,
            'reason' => 'Temporary coverage.',
            'created_by' => $approver->id,
        ]);

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/delegate", [
                'approvalDelegationId' => $delegation->id,
                'lockVersion' => $task->lock_version,
            ])
            ->assertStatus(422);

        $this->assertSame($approver->id, $task->refresh()->assignee_id);
    }

    public function test_delegation_cannot_cross_tenants(): void
    {
        [$tenant, $approver] = $this->tenantUser('approver');
        [$otherTenant, $otherDelegate] = $this->tenantUser('approver');

        $this->actingAsTenant($tenant, $approver)
            ->postJson('/api/approval-delegations', [
                'delegateId' => $otherDelegate->id,
                'scope' => 'all',
                'startsAt' => now()->toISOString(),
                'endsAt' => now()->addDay()->toISOString(),
                'reason' => 'Cross-tenant check.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseMissing('approval_delegations', [
            'tenant_id' => $tenant->id,
            'delegator_id' => $approver->id,
            'delegate_id' => $otherDelegate->id,
        ]);
    }

    public function test_delegation_does_not_allow_self_approval_when_policy_forbids_it(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $this->createPublishedPolicyVersion($tenant, $requester, $approver);

        $delegation = $this->actingAsTenant($tenant, $approver)
            ->postJson('/api/approval-delegations', [
                'delegateId' => $requester->id,
                'scope' => 'task_specific',
                'startsAt' => now()->toISOString(),
                'endsAt' => now()->addDay()->toISOString(),
                'reason' => 'Temporary coverage.',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/route-approval")
            ->assertOk();

        $task = ApprovalTask::query()->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/delegate", [
                'approvalDelegationId' => $delegation['id'],
                'lockVersion' => $task->lock_version,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame($approver->id, $task->refresh()->assignee_id);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();

        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function routeRequisition(Tenant $tenant, User $requester, User $approver): Requisition
    {
        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $this->createPublishedPolicyVersion($tenant, $requester, $approver);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/route-approval")
            ->assertOk();

        return $requisition->refresh();
    }

    private function createSubmittedRequisition(Tenant $tenant, User $requester): Requisition
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => sprintf('REQ-2026-%06d', Requisition::query()->where('tenant_id', $tenant->id)->count() + 1),
            'title' => 'Laptop refresh',
            'business_justification' => 'Replace aging laptops.',
            'needed_by_date' => '2026-07-15',
            'department' => 'Operations',
            'cost_center' => 'OPS-220',
            'delivery_location' => 'Shah Alam warehouse',
            'currency' => 'MYR',
            'status' => RequisitionStatus::Submitted,
            'lock_version' => 0,
            'submitted_at' => now(),
        ]);

        $requisition->lineItems()->create([
            'name' => 'Developer laptop',
            'description' => 'Standard laptop',
            'quantity' => '2.0000',
            'unit_of_measure' => 'each',
            'estimated_unit_price' => '1800.00',
            'currency' => 'MYR',
        ]);

        return $requisition;
    }

    private function createPublishedPolicyVersion(Tenant $tenant, User $actor, User $approver): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard requisition approval',
            'description' => 'Default requisition approval route.',
            'subject_type' => 'requisition',
            'status' => ApprovalPolicyStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->id,
            'tenant_id' => $tenant->id,
            'subject_type' => 'requisition',
            'version_number' => 1,
            'status' => ApprovalPolicyVersionStatus::Published,
            'priority' => 100,
            'rules' => [['field' => 'amount', 'operator' => 'gte', 'value' => 1000]],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Manager review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'user', 'userId' => (string) $approver->id, 'label' => $approver->name],
                        ],
                        'fallbackApprovers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer fallback'],
                        ],
                    ],
                ],
            ],
            'sla_rules' => [['stage' => 'Manager review', 'dueInHours' => 48]],
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }
}
