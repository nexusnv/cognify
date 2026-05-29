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

class RequisitionApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejecting_approval_cancels_future_blocked_tasks_and_reports_only_real_decisions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $firstApprover] = $this->tenantUser('approver', $tenant);
        [, $secondApprover] = $this->tenantUser('approver', $tenant);
        [, $financeApprover] = $this->tenantUser('approver', $tenant);
        $requisition = $this->routeSequentialRequisition($tenant, $requester, $firstApprover, $secondApprover, $financeApprover);

        $taskQuery = ApprovalTask::query()
            ->where('subject_type', Requisition::class)
            ->where('subject_id', $requisition->id);
        $decidingTask = (clone $taskQuery)->where('assignee_id', $firstApprover->id)->firstOrFail();
        $activeSibling = (clone $taskQuery)->where('assignee_id', $secondApprover->id)->firstOrFail();
        $futureTask = (clone $taskQuery)->where('assignee_id', $financeApprover->id)->firstOrFail();

        $this->assertSame('active', $activeSibling->status->value);
        $this->assertSame('blocked', $futureTask->status->value);

        $this->actingAsTenant($tenant, $firstApprover)
            ->postJson("/api/approval-tasks/{$decidingTask->id}/reject", [
                'lockVersion' => $decidingTask->lock_version,
                'reason' => 'Budget narrative does not support the spend.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame(RequisitionStatus::Rejected, $requisition->refresh()->status);
        $this->assertDatabaseHas('approval_instances', [
            'tenant_id' => $tenant->id,
            'subject_type' => Requisition::class,
            'subject_id' => $requisition->id,
            'status' => 'rejected',
        ]);
        $this->assertSame('cancelled', $activeSibling->refresh()->status->value);
        $this->assertSame('cancelled', $futureTask->refresh()->status->value);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/approval-summary")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonCount(1, 'data.completedDecisions')
            ->assertJsonPath('data.completedDecisions.0.taskId', (string) $decidingTask->id)
            ->assertJsonPath('data.completedDecisions.0.decision', 'rejected')
            ->assertJsonPath('data.completedDecisions.0.decidedBy.id', (string) $firstApprover->id);
    }

    public function test_requesting_changes_cancels_future_blocked_tasks_and_reports_only_real_decisions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $firstApprover] = $this->tenantUser('approver', $tenant);
        [, $secondApprover] = $this->tenantUser('approver', $tenant);
        [, $financeApprover] = $this->tenantUser('approver', $tenant);
        $requisition = $this->routeSequentialRequisition($tenant, $requester, $firstApprover, $secondApprover, $financeApprover);

        $taskQuery = ApprovalTask::query()
            ->where('subject_type', Requisition::class)
            ->where('subject_id', $requisition->id);
        $decidingTask = (clone $taskQuery)->where('assignee_id', $firstApprover->id)->firstOrFail();
        $activeSibling = (clone $taskQuery)->where('assignee_id', $secondApprover->id)->firstOrFail();
        $futureTask = (clone $taskQuery)->where('assignee_id', $financeApprover->id)->firstOrFail();

        $this->assertSame('active', $activeSibling->status->value);
        $this->assertSame('blocked', $futureTask->status->value);

        $this->actingAsTenant($tenant, $firstApprover)
            ->postJson("/api/approval-tasks/{$decidingTask->id}/request-changes", [
                'lockVersion' => $decidingTask->lock_version,
                'reason' => 'Attach the comparative supplier quote.',
                'requestedFields' => ['attachments', 'businessJustification'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested');

        $this->assertSame(RequisitionStatus::ChangesRequested, $requisition->refresh()->status);
        $this->assertDatabaseHas('approval_instances', [
            'tenant_id' => $tenant->id,
            'subject_type' => Requisition::class,
            'subject_id' => $requisition->id,
            'status' => 'changes_requested',
        ]);
        $this->assertSame('cancelled', $activeSibling->refresh()->status->value);
        $this->assertSame('cancelled', $futureTask->refresh()->status->value);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/approval-summary")
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested')
            ->assertJsonCount(1, 'data.completedDecisions')
            ->assertJsonPath('data.completedDecisions.0.taskId', (string) $decidingTask->id)
            ->assertJsonPath('data.completedDecisions.0.decision', 'changes_requested')
            ->assertJsonPath('data.completedDecisions.0.decidedBy.id', (string) $firstApprover->id);
    }

    private function routeSequentialRequisition(
        Tenant $tenant,
        User $requester,
        User $firstApprover,
        User $secondApprover,
        User $financeApprover,
    ): Requisition {
        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $this->createSequentialPolicyVersion($tenant, $requester, $firstApprover, $secondApprover, $financeApprover);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/route-approval")
            ->assertOk()
            ->assertJsonPath('data.instance.status', 'active')
            ->assertJsonPath('data.tasks.0.status', 'active')
            ->assertJsonPath('data.tasks.1.status', 'active')
            ->assertJsonPath('data.tasks.2.status', 'blocked');

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

    private function createSequentialPolicyVersion(
        Tenant $tenant,
        User $actor,
        User $firstApprover,
        User $secondApprover,
        User $financeApprover,
    ): ApprovalPolicyVersion {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sequential requisition approval',
            'description' => 'Sequential route with a parallel first stage.',
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
                            ['type' => 'user', 'userId' => (string) $firstApprover->id, 'label' => $firstApprover->name],
                            ['type' => 'user', 'userId' => (string) $secondApprover->id, 'label' => $secondApprover->name],
                        ],
                        'fallbackApprovers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer fallback'],
                        ],
                    ],
                    [
                        'name' => 'Finance review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'user', 'userId' => (string) $financeApprover->id, 'label' => $financeApprover->name],
                        ],
                        'fallbackApprovers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer fallback'],
                        ],
                    ],
                ],
            ],
            'sla_rules' => [
                ['stage' => 'Manager review', 'dueInHours' => 24],
                ['stage' => 'Finance review', 'dueInHours' => 48],
            ],
            'published_by' => $actor->id,
            'published_at' => now(),
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
}
