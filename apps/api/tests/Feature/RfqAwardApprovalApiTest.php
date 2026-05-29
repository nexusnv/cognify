<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Notifications\NotificationRecord;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\EscalateOverdueApprovalTasks;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Domains\Quotation\States\QuotationNormalizationPricingMode;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RfqAwardApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_preview_award_recommendation_approval_route(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-preview")
            ->assertOk()
            ->assertJsonPath('data.context.subjectType', 'rfq_award_recommendation')
            ->assertJsonPath('data.context.rfqId', (string) $rfq->id)
            ->assertJsonPath('data.context.awardRecommendationId', (string) $recommendation->id)
            ->assertJsonPath('data.context.recommendedVendorId', (string) $recommendation->recommended_vendor_id)
            ->assertJsonPath('data.stages.0.name', 'Commercial approval');
    }

    public function test_buyer_can_route_pending_award_recommendation_for_approval(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.currentStage.name', 'Commercial approval')
            ->assertJsonPath('data.activeApprovers.0.name', $approver->name);

        $this->assertDatabaseHas('rfq_award_recommendations', [
            'id' => $recommendation->id,
            'tenant_id' => $tenant->id,
            'status' => 'approval_routed',
        ]);

        $this->assertDatabaseHas('approval_tasks', [
            'tenant_id' => $tenant->id,
            'subject_type' => RfqAwardRecommendation::class,
            'subject_id' => $recommendation->id,
            'assignee_id' => $approver->id,
            'status' => 'active',
        ]);
    }

    public function test_award_routing_matches_award_specific_context_fields_before_fallback(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);

        $this->createAwardPolicy($tenant, $buyer, $approver, [
            'priority' => 200,
            'rules' => [
                ['field' => 'recommendedVendorId', 'operator' => 'equals', 'value' => (string) Str::uuid()],
            ],
            'stage_name' => 'Unmatched executive award review',
        ]);
        $matchingVersion = $this->createAwardPolicy($tenant, $buyer, $approver, [
            'priority' => 50,
            'rules' => [
                ['field' => 'recommendedVendorId', 'operator' => 'equals', 'value' => (string) $recommendation->recommended_vendor_id],
            ],
            'stage_name' => 'Vendor-specific award review',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk()
            ->assertJsonPath('data.currentStage.name', 'Vendor-specific award review');

        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $this->assertDatabaseHas('approval_instances', [
            'subject_type' => RfqAwardRecommendation::class,
            'subject_id' => $recommendation->id,
            'approval_policy_version_id' => $matchingVersion->id,
        ]);
        $this->assertSame('Vendor-specific award review', $task->stage->name);
    }

    public function test_award_routing_without_matching_policy_or_ruleless_fallback_conflicts(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver, [
            'rules' => [
                ['field' => 'recommendedVendorId', 'operator' => 'equals', 'value' => (string) Str::uuid()],
            ],
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertConflict()
            ->assertJsonPath('error.code', 'conflict');

        $this->assertDatabaseMissing('approval_instances', [
            'subject_type' => RfqAwardRecommendation::class,
            'subject_id' => $recommendation->id,
        ]);
    }

    public function test_routing_pending_award_recommendation_is_idempotent(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);

        $first = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk()
            ->json('data.id');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertSame(1, ApprovalTask::query()->count());
    }

    public function test_non_pending_award_recommendation_cannot_be_routed(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);
        $recommendation->forceFill(['status' => 'draft'])->save();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertConflict();
    }

    public function test_missing_award_approval_policy_returns_matching_error(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [$rfq] = $this->pendingRecommendation($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertConflict()
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_approval_summary_returns_active_and_completed_award_route_state(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-summary")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-summary")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.currentStage.name', 'Commercial approval');
    }

    public function test_approving_final_award_task_marks_recommendation_approved(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);
        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
            ->assertOk()
            ->assertJsonPath('data.subject.type', 'rfq_award_recommendation')
            ->assertJsonPath('data.subject.primaryParty', $recommendation->recommendedVendor->name);

        $this->assertDatabaseHas('rfq_award_recommendations', [
            'id' => $recommendation->id,
            'status' => 'approved',
            'approved_by_user_id' => $approver->id,
        ]);
    }

    public function test_rejecting_award_task_marks_recommendation_rejected(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);
        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/reject", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Vendor selection rationale is incomplete.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('rfq_award_recommendations', [
            'id' => $recommendation->id,
            'status' => 'rejected',
            'rejected_by_user_id' => $approver->id,
            'decision_reason' => 'Vendor selection rationale is incomplete.',
        ]);
    }

    public function test_requesting_changes_marks_recommendation_changes_requested(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);
        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/request-changes", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Attach the final commercial clarification.',
                'requestedFields' => ['rationale', 'evidence'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('rfq_award_recommendations', [
            'id' => $recommendation->id,
            'status' => 'changes_requested',
            'changes_requested_by_user_id' => $approver->id,
            'changes_requested_reason' => 'Attach the final commercial clarification.',
        ]);
    }

    public function test_rejecting_award_approval_cancels_future_blocked_tasks_and_reports_only_real_decisions(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $firstApprover] = $this->tenantUser('approver', $tenant);
        [, $secondApprover] = $this->tenantUser('approver', $tenant);
        [, $financeApprover] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->routedSequentialRecommendation($tenant, $buyer, $firstApprover, $secondApprover, $financeApprover);

        $decidingTask = ApprovalTask::query()->where('subject_id', $recommendation->id)->where('assignee_id', $firstApprover->id)->firstOrFail();
        $activeSibling = ApprovalTask::query()->where('subject_id', $recommendation->id)->where('assignee_id', $secondApprover->id)->firstOrFail();
        $futureTask = ApprovalTask::query()->where('subject_id', $recommendation->id)->where('assignee_id', $financeApprover->id)->firstOrFail();

        $this->assertSame('active', $activeSibling->status->value);
        $this->assertSame('blocked', $futureTask->status->value);

        $this->actingAsTenant($tenant, $firstApprover)
            ->postJson("/api/approval-tasks/{$decidingTask->id}/reject", [
                'lockVersion' => $decidingTask->lock_version,
                'reason' => 'Recommendation rationale is incomplete.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('rejected', $recommendation->refresh()->status->value);
        $this->assertSame('cancelled', $activeSibling->refresh()->status->value);
        $this->assertSame('cancelled', $futureTask->refresh()->status->value);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-summary")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonCount(1, 'data.completedDecisions')
            ->assertJsonPath('data.completedDecisions.0.taskId', (string) $decidingTask->id)
            ->assertJsonPath('data.completedDecisions.0.decision', 'rejected')
            ->assertJsonPath('data.completedDecisions.0.decidedBy.id', (string) $firstApprover->id);
    }

    public function test_requesting_award_approval_changes_cancels_future_blocked_tasks_and_reports_only_real_decisions(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $firstApprover] = $this->tenantUser('approver', $tenant);
        [, $secondApprover] = $this->tenantUser('approver', $tenant);
        [, $financeApprover] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->routedSequentialRecommendation($tenant, $buyer, $firstApprover, $secondApprover, $financeApprover);

        $decidingTask = ApprovalTask::query()->where('subject_id', $recommendation->id)->where('assignee_id', $firstApprover->id)->firstOrFail();
        $activeSibling = ApprovalTask::query()->where('subject_id', $recommendation->id)->where('assignee_id', $secondApprover->id)->firstOrFail();
        $futureTask = ApprovalTask::query()->where('subject_id', $recommendation->id)->where('assignee_id', $financeApprover->id)->firstOrFail();

        $this->assertSame('active', $activeSibling->status->value);
        $this->assertSame('blocked', $futureTask->status->value);

        $this->actingAsTenant($tenant, $firstApprover)
            ->postJson("/api/approval-tasks/{$decidingTask->id}/request-changes", [
                'lockVersion' => $decidingTask->lock_version,
                'reason' => 'Attach the final commercial clarification.',
                'requestedFields' => ['rationale', 'evidence'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested');

        $this->assertSame('changes_requested', $recommendation->refresh()->status->value);
        $this->assertSame('cancelled', $activeSibling->refresh()->status->value);
        $this->assertSame('cancelled', $futureTask->refresh()->status->value);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-summary")
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested')
            ->assertJsonCount(1, 'data.completedDecisions')
            ->assertJsonPath('data.completedDecisions.0.taskId', (string) $decidingTask->id)
            ->assertJsonPath('data.completedDecisions.0.decision', 'changes_requested')
            ->assertJsonPath('data.completedDecisions.0.decidedBy.id', (string) $firstApprover->id);
    }

    public function test_award_approval_task_resource_contains_award_subject_summary(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);
        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/approval-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.subject.type', 'rfq_award_recommendation')
            ->assertJsonPath('data.subject.id', (string) $recommendation->id)
            ->assertJsonPath('data.subject.primaryParty', $recommendation->recommendedVendor->name)
            ->assertJsonPath('data.subject.href', "/quotations/awards/{$recommendation->rfq_id}")
            ->assertJsonPath('data.subject.metadata.recommendedVendorId', (string) $recommendation->recommended_vendor_id);
    }

    public function test_award_approval_queue_supports_subject_type_filter(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $approver)
            ->getJson('/api/approval-tasks?subjectType=rfq_award_recommendation')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject.id', (string) $recommendation->id);

        $this->actingAsTenant($tenant, $approver)
            ->getJson('/api/approval-tasks?subjectType=requisition')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cross_tenant_route_summary_show_and_action_attempts_fail(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        [, $otherApprover] = $this->tenantUser('approver', $otherTenant);
        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-summary")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherApprover)
            ->getJson("/api/approval-tasks/{$task->id}")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherApprover)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
            ->assertNotFound();
    }

    public function test_award_approval_records_audit_events_and_assignment_notifications(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk();

        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'approval_instance.routed',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq_award_recommendation.approval_routed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $approver->id,
            'type' => 'approval.task_assigned',
            'href' => "/approvals/tasks/{$task->id}",
        ]);
    }

    public function test_award_approval_task_can_be_delegated(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $delegate] = $this->tenantUser('approver', $tenant);
        [, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);
        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $delegation = $this->actingAsTenant($tenant, $approver)
            ->postJson('/api/approval-delegations', [
                'delegateId' => $delegate->id,
                'scope' => 'task_specific',
                'startsAt' => now()->toISOString(),
                'endsAt' => now()->addDay()->toISOString(),
                'reason' => 'Covering award review.',
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
            ->assertJsonPath('data.originalAssignee.id', (string) $approver->id)
            ->assertJsonPath('data.subject.type', 'rfq_award_recommendation');

        $delegatedTask = $task->refresh();

        $this->actingAsTenant($tenant, $delegate)
            ->postJson("/api/approval-tasks/{$delegatedTask->id}/approve", [
                'lockVersion' => $delegatedTask->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $notification = NotificationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('recipient_id', $delegate->id)
            ->where('title', 'Approval task delegated')
            ->firstOrFail();

        $this->assertSame(RfqAwardRecommendation::class, $notification->subject_type);
        $this->assertSame((string) $recommendation->id, (string) $notification->subject_id);
        $this->assertSame('Award recommendation for '.$recommendation->recommendedVendor->name, $notification->body);
        $this->assertSame($recommendation->rfq->number, $notification->metadata['subjectLabel'] ?? null);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $approver->id,
            'event_type' => 'approval_task.delegated',
            'subject_type' => RfqAwardRecommendation::class,
            'subject_id' => $recommendation->id,
        ]);
    }

    public function test_overdue_award_approval_task_escalates_through_subject_handler(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $fallbackApprover] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver, [
            'fallback_approvers' => [
                ['type' => 'user', 'userId' => (string) $fallbackApprover->id, 'label' => $fallbackApprover->name],
            ],
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk();

        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->where('assignee_id', $approver->id)->firstOrFail();
        $this->makeTaskOverdue($task);

        $this->assertSame(1, app(EscalateOverdueApprovalTasks::class)->handle($tenant));

        $escalatedTask = ApprovalTask::query()
            ->where('escalated_from_task_id', $task->id)
            ->where('assignee_id', $fallbackApprover->id)
            ->firstOrFail();

        $this->assertDatabaseHas('approval_tasks', [
            'id' => $task->id,
            'tenant_id' => $tenant->id,
            'status' => 'cancelled',
        ]);
        $this->assertSame('active', $escalatedTask->status->value);
        $this->actingAsTenant($tenant, $fallbackApprover)
            ->getJson("/api/approval-tasks/{$escalatedTask->id}")
            ->assertOk()
            ->assertJsonPath('data.assignee.id', (string) $fallbackApprover->id)
            ->assertJsonPath('data.subject.type', 'rfq_award_recommendation')
            ->assertJsonPath('data.subject.id', (string) $recommendation->id);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'approval_task.escalated',
            'subject_type' => RfqAwardRecommendation::class,
            'subject_id' => $recommendation->id,
        ]);

        $notification = NotificationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('recipient_id', $fallbackApprover->id)
            ->where('title', 'Approval task escalated')
            ->firstOrFail();

        $this->assertSame(RfqAwardRecommendation::class, $notification->subject_type);
        $this->assertSame((string) $recommendation->id, (string) $notification->subject_id);
        $this->assertSame('Award recommendation for '.$recommendation->recommendedVendor->name, $notification->body);
        $this->assertSame($recommendation->rfq->number, $notification->metadata['subjectLabel'] ?? null);
    }

    public function test_award_approval_routes_require_real_session_auth_and_tenant_context(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);

        $buyer->forceFill([
            'email' => 'award-approval-session@example.com',
            'password' => Hash::make('secret123'),
        ])->save();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'award-approval-session@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withoutHeader('X-Tenant-Id')
            ->withHeader('Origin', 'http://localhost:8880')
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertStatus(400);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-summary")
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-summary")
            ->assertUnauthorized();
    }

    private function routedRecommendation(Tenant $tenant, User $buyer, User $approver): array
    {
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk();

        return [$rfq, $recommendation->refresh()];
    }

    private function routedSequentialRecommendation(
        Tenant $tenant,
        User $buyer,
        User $firstApprover,
        User $secondApprover,
        User $financeApprover,
    ): array {
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $firstApprover, [
            'stages' => [
                [
                    'name' => 'Commercial approval',
                    'completionRule' => 'all',
                    'approvers' => [
                        ['type' => 'user', 'userId' => (string) $firstApprover->id, 'label' => $firstApprover->name],
                        ['type' => 'user', 'userId' => (string) $secondApprover->id, 'label' => $secondApprover->name],
                    ],
                    'fallbackApprovers' => [
                        ['type' => 'role', 'role' => 'approver', 'label' => 'Approver fallback'],
                    ],
                ],
                [
                    'name' => 'Finance approval',
                    'completionRule' => 'all',
                    'approvers' => [
                        ['type' => 'user', 'userId' => (string) $financeApprover->id, 'label' => $financeApprover->name],
                    ],
                    'fallbackApprovers' => [
                        ['type' => 'role', 'role' => 'approver', 'label' => 'Approver fallback'],
                    ],
                ],
            ],
            'sla_rules' => [
                ['stage' => 'Commercial approval', 'dueInHours' => 48],
                ['stage' => 'Finance approval', 'dueInHours' => 72],
            ],
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        return [$rfq, $recommendation->refresh()];
    }

    private function pendingRecommendation(Tenant $tenant, User $buyer): array
    {
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Best overall value with lower delivery risk.',
                'tradeoffSummary' => 'Higher price than lowest bid; stronger implementation plan.',
                'riskSummary' => 'No unresolved normalization issues.',
                'exceptionSummary' => null,
                'evidenceReferences' => [],
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertOk();

        $recommendation = RfqAwardRecommendation::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->firstOrFail();

        return [$rfq, $recommendation];
    }

    private function createAwardPolicy(Tenant $tenant, User $actor, User $approver, array $attributes = []): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['policy_name'] ?? 'Award recommendation approval',
            'description' => 'Commercial approval route for award recommendations.',
            'subject_type' => 'rfq_award_recommendation',
            'status' => ApprovalPolicyStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->id,
            'tenant_id' => $tenant->id,
            'subject_type' => 'rfq_award_recommendation',
            'version_number' => 1,
            'status' => ApprovalPolicyVersionStatus::Published,
            'priority' => $attributes['priority'] ?? 100,
            'rules' => $attributes['rules'] ?? [['field' => 'recommendedAmount', 'operator' => 'gte', 'value' => 1]],
            'route_template' => [
                'stages' => $attributes['stages'] ?? [[
                    'name' => $attributes['stage_name'] ?? 'Commercial approval',
                    'completionRule' => 'all',
                    'approvers' => [
                        ['type' => 'user', 'userId' => (string) $approver->id, 'label' => $approver->name],
                    ],
                    'fallbackApprovers' => $attributes['fallback_approvers'] ?? [
                        ['type' => 'role', 'role' => 'approver', 'label' => 'Approver fallback'],
                    ],
                ]],
            ],
            'sla_rules' => $attributes['sla_rules'] ?? [['stage' => 'Commercial approval', 'dueInHours' => 48]],
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }

    private function makeTaskOverdue(ApprovalTask $task): void
    {
        $task->forceFill([
            'assigned_at' => now()->subHours(6),
            'due_at' => now()->subHour(),
        ])->save();

        $task->stage->forceFill([
            'activated_at' => $task->stage->activated_at ?? now()->subHours(6),
            'due_at' => now()->subHour(),
        ])->save();
    }

    private function rfqWithApprovedQuotation(Tenant $tenant, User $buyer): Rfq
    {
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::upper(Str::random(6)),
            'title' => 'Network refresh',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Refresh network equipment',
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Switch',
                'description' => 'Network switch',
                'quantity' => '10',
                'unit_of_measure' => 'each',
                'currency' => 'USD',
            ]],
        ]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Traders',
            'status' => 'active',
        ]);

        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => 'northwind@example.com',
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => 'USD',
            'total_amount' => '128500.00',
            'lead_time_days' => 21,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '24 months',
            'compliance_notes' => 'Compliant',
            'manual_entry_complete' => true,
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => 'USD',
            'total_amount' => '128500.00',
            'lead_time_days' => 21,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '24 months',
            'compliance_notes' => 'Compliant',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);

        $quotation->forceFill(['current_version_id' => $version->id])->save();

        $version->lineItems()->create([
            'tenant_id' => $tenant->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'description' => 'Switch',
            'quantity' => '10.0000',
            'unit' => 'each',
            'unit_price' => '12850.0000',
            'total_amount' => '128500.00',
            'position' => 1,
        ]);

        $normalization = QuotationNormalization::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 1,
            'status' => QuotationNormalizationStatus::Approved->value,
            'is_current_for_version' => true,
            'approved_at' => now(),
            'approved_by_user_id' => $buyer->id,
            'algorithm_version' => 'deterministic-v1',
        ]);

        $normalization->fields()->createMany([
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.currency',
                'normalized_value' => 'USD',
                'data_type' => 'currency',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.totalAmount',
                'normalized_value' => '128500.00',
                'data_type' => 'money',
                'currency' => 'USD',
                'source' => 'manual_entry',
            ],
        ]);

        $lineGroup = $normalization->lineGroups()->create([
            'tenant_id' => $tenant->id,
            'group_number' => 1,
            'pricing_mode' => QuotationNormalizationPricingMode::PerLine->value,
            'description' => 'Switch',
            'currency' => 'USD',
        ]);

        $lineGroup->mappings()->create([
            'tenant_id' => $tenant->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'mapping_type' => QuotationNormalizationMappingType::Full->value,
            'quantity' => '10',
            'unit' => 'each',
            'line_total' => '128500.00',
        ]);

        return $rfq;
    }
}
