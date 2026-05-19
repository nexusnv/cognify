<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalStage;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalDelegationStatus;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Approval\States\ApprovalStageStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Award\Models\Award;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;

class DemoRoadmapPreviewSeeder
{
    private const RFQ_DUE_AT = '2026-05-29 12:00:00';

    private const APPROVAL_DUE_AT = '2026-05-22 12:00:00';

    private const DECIDED_AT = '2026-05-20 12:00:00';

    public function run(DemoSeedContext $context): void
    {
        $this->seedAcmePreview($context);
        $this->seedNorthwindPreview($context);
    }

    private function seedAcmePreview(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $admin = $context->users->get('admin');
        $approver = $context->users->get('finance');
        $buyer = $context->users->get('buyer');
        $requester = $context->users->get('requester');

        $vendorRows = [
            'atlas' => ['Atlas Office Supplies', 'preferred', 'Office supplies', 'low'],
            'northstar' => ['Northstar Furniture Co', 'evaluation', 'Furniture', 'medium'],
            'secureworks' => ['SecureWorks Advisory', 'preferred', 'Professional services', 'low'],
            'papertrail' => ['Papertrail Logistics', 'restricted', 'Logistics', 'high'],
            'byteforge' => ['ByteForge Systems', 'evaluation', 'IT hardware', 'medium'],
            'greenline' => ['Greenline Facilities', 'preferred', 'Facilities', 'low'],
        ];

        foreach ($vendorRows as $key => [$name, $status, $category, $risk]) {
            $context->vendors->put(
                $key,
                Vendor::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    [
                        'status' => $status,
                        'category' => $category,
                        'risk_rating' => $risk,
                        'metadata' => ['demo' => true],
                    ],
                ),
            );
        }

        $project = ProcurementProject::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'PRJ-2026-0001'],
            [
                'owner_id' => $admin->id,
                'name' => 'HQ Workplace Refresh',
                'charter' => 'Coordinate related requisitions for the workspace refresh.',
                'status' => 'active',
                'budget_amount' => 120000,
                'currency' => 'USD',
                'department' => 'Operations',
                'cost_center' => 'OPS-100',
                'target_start_date' => '2026-06-01',
                'target_completion_date' => '2026-09-30',
                'metadata' => ['demo' => true],
            ],
        );
        $context->projects->put('workplace-refresh', $project);

        $rfq = Rfq::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'RFQ-2026-0001'],
            [
                'project_id' => $project->id,
                'requisition_id' => $context->requisitions->get('office-refresh')->id,
                'title' => 'Office furniture package',
                'status' => 'open',
                'due_at' => self::RFQ_DUE_AT,
                'metadata' => ['invited_vendors' => 3],
            ],
        );
        $context->rfqs->put('office-furniture', $rfq);

        $quotation = Quotation::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'QUO-2026-0001'],
            [
                'rfq_id' => $rfq->id,
                'vendor_id' => $context->vendors->get('northstar')->id,
                'status' => 'received',
                'total_amount' => 84500,
                'currency' => 'USD',
                'metadata' => ['lead_time_days' => 21],
            ],
        );
        $context->quotations->put('northstar-office', $quotation);

        $policyVersion = $this->seedApprovalPolicy($tenant, $admin, $buyer);
        $this->seedApprovalWorkflow(
            context: $context,
            key: 'security-audit-approval',
            requisition: $context->requisitions->get('security-audit'),
            policyVersion: $policyVersion,
            assignee: $approver,
            stageStatus: ApprovalStageStatus::Active,
            taskStatus: ApprovalTaskStatus::Active,
            assignedAt: '2026-05-18 09:00:00',
            dueAt: self::APPROVAL_DUE_AT,
        );

        $delegation = ApprovalDelegation::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'delegator_id' => $approver->id,
                'delegate_id' => $buyer->id,
                'scope' => 'task_specific',
            ],
            [
                'starts_at' => '2026-05-18 00:00:00',
                'ends_at' => '2026-05-31 23:59:59',
                'status' => ApprovalDelegationStatus::Active,
                'reason' => 'Demo coverage for delegated approval authority.',
                'created_by' => $approver->id,
            ],
        );
        $this->seedApprovalWorkflow(
            context: $context,
            key: 'office-refresh-delegated',
            requisition: $context->requisitions->get('office-refresh'),
            policyVersion: $policyVersion,
            assignee: $buyer,
            originalAssignee: $approver,
            stageStatus: ApprovalStageStatus::Active,
            taskStatus: ApprovalTaskStatus::Active,
            assignedAt: '2026-05-17 09:00:00',
            dueAt: '2026-05-18 12:00:00',
            metadata: ['delegationId' => (string) $delegation->id],
        );

        $award = Award::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'AWD-2026-0001'],
            [
                'project_id' => $project->id,
                'rfq_id' => $rfq->id,
                'quotation_id' => $quotation->id,
                'vendor_id' => $context->vendors->get('northstar')->id,
                'status' => 'recommended',
                'total_amount' => 84500,
                'currency' => 'USD',
                'decided_at' => self::DECIDED_AT,
                'metadata' => ['rationale' => 'Best delivery confidence'],
            ],
        );
        $context->awards->put('office-award', $award);
    }

    private function seedNorthwindPreview(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('northwind');
        $owner = $context->users->get('vendor_manager');

        $vendorRows = [
            'harbor' => ['Harbor Industrial Supply', 'preferred', 'Warehouse supplies', 'low'],
            'metrofleet' => ['MetroFleet Services', 'evaluation', 'Fleet services', 'medium'],
            'civic-safety' => ['Civic Safety Partners', 'restricted', 'Safety equipment', 'high'],
        ];

        foreach ($vendorRows as $key => [$name, $status, $category, $risk]) {
            $context->vendors->put(
                $key,
                Vendor::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    [
                        'status' => $status,
                        'category' => $category,
                        'risk_rating' => $risk,
                        'metadata' => ['demo' => true],
                    ],
                ),
            );
        }

        $project = ProcurementProject::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'PRJ-2026-1001'],
            [
                'owner_id' => $owner->id,
                'name' => 'Northwind Warehouse Launch',
                'charter' => 'Coordinate related requisitions for the workspace refresh.',
                'status' => 'active',
                'budget_amount' => 78000,
                'currency' => 'USD',
                'department' => 'Operations',
                'cost_center' => 'OPS-100',
                'target_start_date' => '2026-06-01',
                'target_completion_date' => '2026-09-30',
                'metadata' => ['demo' => true],
            ],
        );
        $context->projects->put('warehouse-launch', $project);

        $rfq = Rfq::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'RFQ-2026-1001'],
            [
                'project_id' => $project->id,
                'requisition_id' => $context->requisitions->get('warehouse-supplies')->id,
                'title' => 'Warehouse supply bundle',
                'status' => 'open',
                'due_at' => self::RFQ_DUE_AT,
                'metadata' => ['invited_vendors' => 2],
            ],
        );
        $context->rfqs->put('warehouse-supplies', $rfq);

        $quotation = Quotation::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'QUO-2026-1001'],
            [
                'rfq_id' => $rfq->id,
                'vendor_id' => $context->vendors->get('harbor')->id,
                'status' => 'received',
                'total_amount' => 61200,
                'currency' => 'USD',
                'metadata' => ['lead_time_days' => 14],
            ],
        );
        $context->quotations->put('harbor-warehouse', $quotation);

        $policyVersion = $this->seedApprovalPolicy($tenant, $owner, $owner);
        $this->seedApprovalWorkflow(
            context: $context,
            key: 'warehouse-approved',
            requisition: $context->requisitions->get('warehouse-supplies'),
            policyVersion: $policyVersion,
            assignee: $owner,
            stageStatus: ApprovalStageStatus::Completed,
            taskStatus: ApprovalTaskStatus::Approved,
            assignedAt: '2026-05-15 09:00:00',
            dueAt: self::APPROVAL_DUE_AT,
            decidedAt: self::DECIDED_AT,
            decision: 'approved',
        );
        $this->seedApprovalWorkflow(
            context: $context,
            key: 'fleet-rejected',
            requisition: $context->requisitions->get('fleet-maintenance'),
            policyVersion: $policyVersion,
            assignee: $owner,
            stageStatus: ApprovalStageStatus::Completed,
            taskStatus: ApprovalTaskStatus::Rejected,
            assignedAt: '2026-05-15 09:00:00',
            dueAt: self::APPROVAL_DUE_AT,
            decidedAt: self::DECIDED_AT,
            decision: 'rejected',
            decisionReason: 'Demo rejection for budget review.',
        );

        $award = Award::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'AWD-2026-1001'],
            [
                'project_id' => $project->id,
                'rfq_id' => $rfq->id,
                'quotation_id' => $quotation->id,
                'vendor_id' => $context->vendors->get('harbor')->id,
                'status' => 'recommended',
                'total_amount' => 61200,
                'currency' => 'USD',
                'decided_at' => self::DECIDED_AT,
                'metadata' => ['rationale' => 'Best local availability'],
            ],
        );
        $context->awards->put('warehouse-award', $award);
    }

    private function seedApprovalPolicy(Tenant $tenant, User $actor, User $fallbackApprover): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Demo requisition approval'],
            [
                'description' => 'Seeded approval route for the local demo workspace.',
                'subject_type' => 'requisition',
                'status' => ApprovalPolicyStatus::Active,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        return ApprovalPolicyVersion::query()->updateOrCreate(
            ['approval_policy_id' => $policy->id, 'version_number' => 1],
            [
                'tenant_id' => $tenant->id,
                'subject_type' => 'requisition',
                'status' => ApprovalPolicyVersionStatus::Published,
                'priority' => 100,
                'rules' => [['field' => 'amount', 'operator' => 'gte', 'value' => 1000]],
                'route_template' => [
                    'stages' => [
                        [
                            'name' => 'Manager review',
                            'completionRule' => 'all',
                            'approvers' => [['type' => 'role', 'role' => 'approver', 'label' => 'Approver']],
                            'fallbackApprovers' => [
                                ['type' => 'user', 'userId' => (string) $fallbackApprover->id, 'label' => $fallbackApprover->name],
                            ],
                        ],
                    ],
                ],
                'sla_rules' => [['stage' => 'Manager review', 'dueInHours' => 48]],
                'published_by' => $actor->id,
                'published_at' => '2026-05-15 09:00:00',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function seedApprovalWorkflow(
        DemoSeedContext $context,
        string $key,
        Requisition $requisition,
        ApprovalPolicyVersion $policyVersion,
        User $assignee,
        ApprovalStageStatus $stageStatus,
        ApprovalTaskStatus $taskStatus,
        string $assignedAt,
        string $dueAt,
        ?User $originalAssignee = null,
        ?string $decidedAt = null,
        ?string $decision = null,
        ?string $decisionReason = null,
        array $metadata = [],
    ): void {
        $completedAt = in_array($taskStatus, [ApprovalTaskStatus::Approved, ApprovalTaskStatus::Rejected, ApprovalTaskStatus::ChangesRequested], true)
            ? $decidedAt
            : null;
        $instanceStatus = match ($taskStatus) {
            ApprovalTaskStatus::Approved => ApprovalInstanceStatus::Approved,
            ApprovalTaskStatus::Rejected => ApprovalInstanceStatus::Rejected,
            ApprovalTaskStatus::ChangesRequested => ApprovalInstanceStatus::ChangesRequested,
            default => ApprovalInstanceStatus::Active,
        };

        $instance = ApprovalInstance::query()->updateOrCreate(
            [
                'tenant_id' => $requisition->tenant_id,
                'subject_type' => Requisition::class,
                'subject_id' => $requisition->id,
            ],
            [
                'approval_policy_version_id' => $policyVersion->id,
                'status' => $instanceStatus,
                'current_stage_sequence' => 1,
                'matched_context' => ['demo' => true, 'department' => $requisition->department],
                'matched_explanation' => ['policy' => 'Demo requisition approval'],
                'started_at' => $assignedAt,
                'completed_at' => $completedAt,
                'cancelled_at' => null,
            ],
        );

        $stage = ApprovalStage::query()->updateOrCreate(
            ['tenant_id' => $requisition->tenant_id, 'approval_instance_id' => $instance->id, 'sequence' => 1],
            [
                'name' => 'Manager review',
                'completion_rule' => 'all',
                'status' => $stageStatus,
                'activated_at' => $assignedAt,
                'completed_at' => $completedAt,
                'due_at' => $dueAt,
            ],
        );

        $task = ApprovalTask::query()->updateOrCreate(
            ['tenant_id' => $requisition->tenant_id, 'approval_instance_id' => $instance->id, 'approval_stage_id' => $stage->id],
            [
                'subject_type' => Requisition::class,
                'subject_id' => $requisition->id,
                'assignee_id' => $assignee->id,
                'original_assignee_id' => ($originalAssignee ?? $assignee)->id,
                'title' => "Approve {$requisition->number}",
                'status' => $taskStatus,
                'decision' => $decision,
                'decision_reason' => $decisionReason,
                'requested_fields' => [],
                'decided_by_id' => $decidedAt !== null ? $assignee->id : null,
                'assigned_at' => $assignedAt,
                'viewed_at' => null,
                'due_at' => $dueAt,
                'decided_at' => $decidedAt,
                'lock_version' => 0,
                'metadata' => ['demo' => true, ...$metadata],
            ],
        );

        $requisition->forceFill(['approval_instance_id' => $instance->id])->save();
        $context->approvalTasks->put($key, $task->refresh());
    }
}
