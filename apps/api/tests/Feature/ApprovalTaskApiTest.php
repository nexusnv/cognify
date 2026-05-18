<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_requisition_can_be_routed_for_approval(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $this->createPublishedPolicyVersion($tenant, $requester, $approver);

        $this->assertSame(RequisitionStatus::Submitted, $requisition->status);

        $response = $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/route-approval");

        $response->assertOk()
            ->assertJsonPath('data.instance.status', 'active')
            ->assertJsonPath('data.tasks.0.status', 'active')
            ->assertJsonPath('data.tasks.0.assignee.id', (string) $approver->id);

        $this->assertSame(RequisitionStatus::PendingApproval, $requisition->refresh()->status);
        $this->assertDatabaseHas('approval_instances', [
            'tenant_id' => $tenant->id,
            'subject_type' => Requisition::class,
            'subject_id' => $requisition->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('approval_tasks', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $approver->id,
            'status' => 'active',
        ]);
    }

    public function test_approver_can_approve_assigned_task(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $requisition = $this->routeRequisition($tenant, $requester, $approver);
        $task = ApprovalTask::query()->firstOrFail();

        $this->assertSame(RequisitionStatus::PendingApproval, $requisition->refresh()->status);

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.lockVersion', 1);

        $this->assertSame(RequisitionStatus::Approved, $requisition->refresh()->status);
        $this->assertNotNull($requisition->approved_at);
        $this->assertSame($approver->id, $requisition->approved_by_id);
    }

    public function test_approver_can_reject_with_required_reason(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $requisition = $this->routeRequisition($tenant, $requester, $approver);
        $task = ApprovalTask::query()->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/reject", ['lockVersion' => $task->lock_version])
            ->assertStatus(422);

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/reject", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Budget is not justified.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decisionReason', 'Budget is not justified.');

        $this->assertSame(RequisitionStatus::Rejected, $requisition->refresh()->status);
        $this->assertSame('Budget is not justified.', $requisition->rejection_reason);
    }

    public function test_approver_can_request_changes_with_required_reason(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $requisition = $this->routeRequisition($tenant, $requester, $approver);
        $task = ApprovalTask::query()->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/request-changes", ['lockVersion' => $task->lock_version])
            ->assertStatus(422);

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/request-changes", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Please attach the supplier quote.',
                'requestedFields' => ['attachments', 'businessJustification'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested')
            ->assertJsonPath('data.requestedFields', ['attachments', 'businessJustification']);

        $this->assertSame(RequisitionStatus::ChangesRequested, $requisition->refresh()->status);
        $this->assertSame('Please attach the supplier quote.', $requisition->change_request_reason);
    }

    public function test_stale_task_action_returns_conflict(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $this->routeRequisition($tenant, $requester, $approver);
        $task = ApprovalTask::query()->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version + 1])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_cross_tenant_user_cannot_act_on_task(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $this->routeRequisition($tenant, $requester, $approver);
        $task = ApprovalTask::query()->firstOrFail();

        [$otherTenant, $otherApprover] = $this->tenantUser('approver');

        $this->actingAsTenant($otherTenant, $otherApprover)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
            ->assertNotFound();
    }

    public function test_different_same_tenant_approver_cannot_view_task_detail(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $firstApprover] = $this->tenantUser('approver', $tenant);
        [, $otherApprover] = $this->tenantUser('approver', $tenant);
        $this->routeRequisition($tenant, $requester, $firstApprover);
        $task = ApprovalTask::query()->firstOrFail();

        $this->actingAsTenant($tenant, $otherApprover)
            ->getJson("/api/approval-tasks/{$task->id}")
            ->assertForbidden();
    }

    public function test_multi_approver_stage_creates_only_first_resolved_task(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $firstApprover] = $this->tenantUser('approver', $tenant);
        [, $secondApprover] = $this->tenantUser('approver', $tenant);
        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $this->createPublishedPolicyVersionWithApprovers($tenant, $requester, [$firstApprover, $secondApprover]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/route-approval")
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.assignee.id', (string) $firstApprover->id);

        $this->assertSame(1, ApprovalTask::query()->count());
        $this->assertDatabaseHas('approval_tasks', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $firstApprover->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('approval_tasks', [
            'tenant_id' => $tenant->id,
            'assignee_id' => $secondApprover->id,
        ]);
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

    /**
     * @param array<int, User> $approvers
     */
    private function createPublishedPolicyVersionWithApprovers(Tenant $tenant, User $actor, array $approvers): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Multi approver requisition approval',
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
                        'approvers' => array_map(
                            fn (User $approver): array => ['type' => 'user', 'userId' => (string) $approver->id, 'label' => $approver->name],
                            $approvers,
                        ),
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
