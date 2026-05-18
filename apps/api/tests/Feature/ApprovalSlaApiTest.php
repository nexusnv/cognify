<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\EscalateOverdueApprovalTasks;
use Domains\Approval\Jobs\EscalateOverdueApprovalTasksJob;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalStage;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalSlaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-19 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_approval_task_due_date_is_set_when_stage_activates(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $firstApprover] = $this->tenantUser('approver', $tenant);
        [, $secondApprover] = $this->tenantUser('approver', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $this->createPublishedSequentialPolicyVersion($tenant, $requester, $firstApprover, $secondApprover);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/route-approval")
            ->assertOk();

        $firstTask = ApprovalTask::query()->where('assignee_id', $firstApprover->id)->firstOrFail();
        $secondStage = ApprovalStage::query()->where('approval_instance_id', $firstTask->approval_instance_id)->where('sequence', 2)->firstOrFail();

        $this->actingAsTenant($tenant, $firstApprover)
            ->postJson("/api/approval-tasks/{$firstTask->id}/approve", [
                'lockVersion' => $firstTask->lock_version,
            ])
            ->assertOk();

        $secondTask = ApprovalTask::query()->where('approval_stage_id', $secondStage->id)->firstOrFail();

        $this->assertSame(Carbon::parse('2026-05-21 00:00:00')->toISOString(), $secondStage->refresh()->due_at?->toISOString());
        $this->assertSame(Carbon::parse('2026-05-21 00:00:00')->toISOString(), $secondTask->refresh()->due_at?->toISOString());
        $this->assertSame(ApprovalTaskStatus::Active, $secondTask->status);
    }

    public function test_overdue_task_is_escalated_to_fallback_approver(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $buyer] = $this->tenantUser('buyer', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $this->createPublishedPolicyVersion(
            tenant: $tenant,
            actor: $requester,
            approver: $approver,
            buyer: $buyer,
            includeFallbackApprovers: false,
        );

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/route-approval")
            ->assertOk();

        $task = ApprovalTask::query()->where('assignee_id', $approver->id)->firstOrFail();
        $this->makeTaskOverdue($task, 4);

        $escalatedCount = app(EscalateOverdueApprovalTasks::class)->handle($tenant);

        $this->assertSame(1, $escalatedCount);
        $this->assertDatabaseHas('approval_tasks', [
            'tenant_id' => $tenant->id,
            'approval_stage_id' => $task->approval_stage_id,
            'assignee_id' => $buyer->id,
            'original_assignee_id' => $approver->id,
            'escalated_from_task_id' => $task->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('approval_tasks', [
            'id' => $task->id,
            'tenant_id' => $tenant->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'approval_task.escalated',
            'subject_type' => Requisition::class,
            'subject_id' => $requisition->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $buyer->id,
            'type' => 'approval.task_assigned',
            'title' => 'Approval task escalated',
        ]);
    }

    public function test_escalation_job_is_idempotent(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $buyer] = $this->tenantUser('buyer', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $this->createPublishedPolicyVersion($tenant, $requester, $approver, $buyer);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/route-approval")
            ->assertOk();

        $task = ApprovalTask::query()->where('assignee_id', $approver->id)->firstOrFail();
        $this->makeTaskOverdue($task, 6);

        $job = app(EscalateOverdueApprovalTasksJob::class);
        $job->handle(app(EscalateOverdueApprovalTasks::class));
        $job->handle(app(EscalateOverdueApprovalTasks::class));

        $this->assertSame(1, ApprovalTask::query()->where('escalated_from_task_id', $task->id)->count());
        $this->assertSame(1, ApprovalTask::query()->where('status', ApprovalTaskStatus::Cancelled)->whereKey($task->id)->count());
        $this->assertSame(1, \DB::table('audit_events')->where('tenant_id', $tenant->id)->where('event_type', 'approval_task.escalated')->count());
        $this->assertSame(1, \DB::table('notifications')->where('tenant_id', $tenant->id)->where('title', 'Approval task escalated')->count());
    }

    public function test_sla_summary_counts_due_soon_overdue_and_escalated_tasks(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $buyer] = $this->tenantUser('buyer', $tenant);

        $dueSoonRequisition = $this->createSubmittedRequisition($tenant, $requester, 'Due soon requisition');
        $this->createPublishedPolicyVersion($tenant, $requester, $approver, $buyer, [
            ['stage' => 'Manager review', 'dueInHours' => 24],
        ]);
        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$dueSoonRequisition->id}/route-approval")
            ->assertOk();

        $dueSoonTask = ApprovalTask::query()->where('assignee_id', $approver->id)->where('subject_id', $dueSoonRequisition->id)->firstOrFail();
        $this->setTaskTiming($dueSoonTask, Carbon::now()->subHours(3), Carbon::now()->addHour());

        $overdueRequisition = $this->createSubmittedRequisition($tenant, $requester, 'Overdue requisition');
        $this->createPublishedPolicyVersion($tenant, $requester, $approver, $buyer, [
            ['stage' => 'Manager review', 'dueInHours' => 12],
        ]);
        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$overdueRequisition->id}/route-approval")
            ->assertOk();

        $overdueTask = ApprovalTask::query()->where('assignee_id', $approver->id)->where('subject_id', $overdueRequisition->id)->firstOrFail();
        $this->setTaskTiming($overdueTask, Carbon::now()->subHours(8), Carbon::now()->subHour());

        app(EscalateOverdueApprovalTasks::class)->handle($tenant);

        $response = $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/approvals/sla-summary')
            ->assertOk();

        $response->assertJsonPath('data.assigned', 2)
            ->assertJsonPath('data.dueSoon', 1)
            ->assertJsonPath('data.overdue', 1)
            ->assertJsonPath('data.escalated', 1)
            ->assertJsonPath('data.averageAgeMinutes', 90)
            ->assertJsonPath('data.oldestPendingApproval.taskId', (string) $dueSoonTask->id);
    }

    public function test_admin_can_view_tenant_sla_summary_but_cross_tenant_data_is_excluded(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $requester] = $this->tenantUser('requester', $tenant);

        $otherTenant = Tenant::query()->create(['name' => fake()->company()]);
        $otherAdmin = User::factory()->create();
        $otherRequester = User::factory()->create();
        $otherApprover = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $otherTenant->users()->attach($otherAdmin->id, ['role' => 'admin']);
        $otherTenant->users()->attach($otherRequester->id, ['role' => 'requester']);
        $otherTenant->users()->attach($otherApprover->id, ['role' => 'approver']);
        $otherTenant->users()->attach($otherBuyer->id, ['role' => 'buyer']);

        $tenantRequisition = $this->createSubmittedRequisition($tenant, $requester, 'Tenant A requisition');
        $this->createPublishedPolicyVersion($tenant, $requester, $approver, $buyer, [
            ['stage' => 'Manager review', 'dueInHours' => 24],
        ]);
        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$tenantRequisition->id}/route-approval")
            ->assertOk();
        $tenantTask = ApprovalTask::query()->where('subject_id', $tenantRequisition->id)->firstOrFail();
        $this->setTaskTiming($tenantTask, Carbon::now()->subHours(2), Carbon::now()->addHour());

        $otherRequisition = $this->createSubmittedRequisition($otherTenant, $otherRequester, 'Tenant B requisition');
        $this->createPublishedPolicyVersion($otherTenant, $otherRequester, $otherApprover, $otherBuyer, [
            ['stage' => 'Manager review', 'dueInHours' => 24],
        ]);
        $this->actingAsTenant($otherTenant, $otherRequester)
            ->postJson("/api/requisitions/{$otherRequisition->id}/route-approval")
            ->assertOk();
        $otherTask = ApprovalTask::query()->where('tenant_id', $otherTenant->id)->where('subject_id', $otherRequisition->id)->firstOrFail();
        $this->setTaskTiming($otherTask, Carbon::now()->subHours(10), Carbon::now()->subHour());
        app(EscalateOverdueApprovalTasks::class)->handle($otherTenant);

        $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/approvals/sla-summary')
            ->assertOk()
            ->assertJsonPath('data.assigned', 1)
            ->assertJsonPath('data.dueSoon', 1)
            ->assertJsonPath('data.overdue', 0)
            ->assertJsonPath('data.escalated', 0)
            ->assertJsonPath('data.oldestPendingApproval.taskId', (string) $tenantTask->id);
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

    private function createSubmittedRequisition(Tenant $tenant, User $requester, string $title = 'Laptop refresh'): Requisition
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => sprintf('REQ-2026-%06d', Requisition::query()->where('tenant_id', $tenant->id)->count() + 1),
            'title' => $title,
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

    /**
     * @param array<int, User>|null $stageApprovers
     * @param array<int, array<string, mixed>> $slaRules
     */
    private function createPublishedPolicyVersion(
        Tenant $tenant,
        User $actor,
        User $approver,
        User $buyer,
        array $slaRules = [['stage' => 'Manager review', 'dueInHours' => 48]],
        ?array $stageApprovers = null,
        bool $includeFallbackApprovers = true,
    ): ApprovalPolicyVersion {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Requisition approval',
            'description' => 'Default requisition approval route.',
            'subject_type' => 'requisition',
            'status' => ApprovalPolicyStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $stageApprovers ??= [$approver];

        $stageTemplate = [
            'name' => 'Manager review',
            'completionRule' => 'all',
            'approvers' => array_map(
                fn (User $user): array => ['type' => 'user', 'userId' => (string) $user->id, 'label' => $user->name],
                $stageApprovers,
            ),
        ];

        if ($includeFallbackApprovers) {
            $stageTemplate['fallbackApprovers'] = [
                ['type' => 'user', 'userId' => (string) $buyer->id, 'label' => $buyer->name],
            ];
        }

        return ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->id,
            'tenant_id' => $tenant->id,
            'subject_type' => 'requisition',
            'version_number' => 1,
            'status' => ApprovalPolicyVersionStatus::Published,
            'priority' => 100,
            'rules' => [['field' => 'amount', 'operator' => 'gte', 'value' => 1000]],
            'route_template' => [
                'stages' => [$stageTemplate],
            ],
            'sla_rules' => $slaRules,
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }

    private function createPublishedSequentialPolicyVersion(
        Tenant $tenant,
        User $actor,
        User $firstApprover,
        User $secondApprover,
    ): ApprovalPolicyVersion {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sequential requisition approval',
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
                            ['type' => 'user', 'userId' => (string) $firstApprover->id, 'label' => $firstApprover->name],
                        ],
                        'fallbackApprovers' => [
                            ['type' => 'user', 'userId' => (string) $secondApprover->id, 'label' => $secondApprover->name],
                        ],
                    ],
                    [
                        'name' => 'Finance review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'user', 'userId' => (string) $secondApprover->id, 'label' => $secondApprover->name],
                        ],
                        'fallbackApprovers' => [
                            ['type' => 'user', 'userId' => (string) $secondApprover->id, 'label' => $secondApprover->name],
                        ],
                    ],
                ],
            ],
            'sla_rules' => [
                ['stage' => 'Manager review', 'dueInHours' => 24],
                ['stage' => 'Finance review', 'dueInHours' => 36],
            ],
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }

    private function makeTaskOverdue(ApprovalTask $task, int $ageHours): void
    {
        $task->forceFill([
            'assigned_at' => now()->subHours($ageHours),
            'due_at' => now()->subHour(),
        ])->save();

        $task->stage->forceFill([
            'activated_at' => $task->stage->activated_at ?? now()->subHours($ageHours),
            'due_at' => now()->subHour(),
        ])->save();
    }

    private function setTaskTiming(ApprovalTask $task, Carbon $assignedAt, Carbon $dueAt): void
    {
        $task->forceFill([
            'assigned_at' => $assignedAt,
            'due_at' => $dueAt,
        ])->save();

        $task->stage->forceFill([
            'activated_at' => $assignedAt,
            'due_at' => $dueAt,
        ])->save();
    }
}
