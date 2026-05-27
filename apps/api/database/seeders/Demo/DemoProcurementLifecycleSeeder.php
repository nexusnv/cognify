<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalStage;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Approval\States\ApprovalStageStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationNormalizationField;
use Domains\Quotation\Models\QuotationNormalizationIssue;
use Domains\Quotation\Models\QuotationNormalizationLineGroup;
use Domains\Quotation\Models\QuotationNormalizationLineMapping;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\QuotationScoringTemplateCriterion;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqAwardRecommendationEvidence;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\Models\RfqScorecardCriterion;
use Domains\Quotation\Models\RfqScorecardEntry;
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationIssueStatus;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\RfqAwardRecommendationEvidenceType;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqScorecardStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Str;

class DemoProcurementLifecycleSeeder
{
    private const PROJECT_NUMBER = 'PRJ-2026-SUSTAIN';
    private const REQ_NUMBER = 'REQ-2026-SUSTAIN';
    private const RFQ_NUMBER = 'RFQ-2026-SUSTAIN';

    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $requester = $context->users->get('requester');
        $buyer = $context->users->get('buyer');
        $finance = $context->users->get('finance');
        $admin = $context->users->get('admin');

        // 1. Project
        $project = $this->seedProject($tenant, $admin);
        $context->projects->put('sustainable-expansion', $project);

        // 2. Requisition
        $requisition = $this->seedRequisition($tenant, $requester, $project);
        $context->requisitions->put('sustainable-furniture', $requisition);

        // 3. Collaboration
        $this->seedCollaboration($tenant, $requisition, $requester, $buyer);

        // 4. Approval (3-stage complex)
        $this->seedApprovalWorkflow($tenant, $requisition, $finance, $buyer);

        // 5. RFQ
        $rfq = $this->seedRfq($tenant, $project, $requisition);
        $context->rfqs->put('sustainable-furniture-rfq', $rfq);

        // 6. Vendor Invitations
        $this->seedInvitations($context, $rfq);

        // 7. Quotations (Varied & Versioned)
        $this->seedQuotations($context, $rfq, $buyer);

        // 8. Normalization
        $this->seedNormalizations($context, $rfq, $buyer);

        // 9. Scoring
        $this->seedScoring($context, $rfq, $buyer);

        // 10. Award Recommendation
        $this->seedAwardRecommendation($context, $rfq, $buyer);
    }

    private function seedProject(Tenant $tenant, User $owner): ProcurementProject
    {
        return ProcurementProject::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => self::PROJECT_NUMBER],
            [
                'owner_id' => $owner->id,
                'name' => 'Sustainable Office Expansion 2026',
                'charter' => 'Expanding the HQ East Wing with 100% sustainable materials and carbon-neutral logistics.',
                'status' => 'active',
                'budget_amount' => 150000,
                'currency' => 'USD',
                'department' => 'Operations',
                'cost_center' => 'OPS-100',
                'target_start_date' => '2026-06-01',
                'target_completion_date' => '2026-12-31',
                'metadata' => ['demo' => true, 'priority' => 'high', 'esg_critical' => true],
            ]
        );
    }

    private function seedRequisition(Tenant $tenant, User $requester, ProcurementProject $project): Requisition
    {
        $requisition = Requisition::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => self::REQ_NUMBER],
            [
                'project_id' => $project->id,
                'requester_id' => $requester->id,
                'title' => 'Ergonomic & Eco-friendly Furniture Bundle',
                'business_justification' => 'Provisioning the new East Wing expansion. All furniture must be certified sustainable as per the 2026 HQ Charter.',
                'needed_by_date' => '2026-08-15',
                'department' => 'Operations',
                'cost_center' => 'OPS-100',
                'delivery_location' => 'HQ East Wing - Level 4',
                'currency' => 'USD',
                'status' => RequisitionStatus::Approved,
                'submitted_at' => '2026-05-18 10:00:00',
            ]
        );

        $requisition->lineItems()->delete();
        $requisition->lineItems()->createMany([
            [
                'name' => 'Eco-Chair (Recycled Ocean Plastic)',
                'quantity' => 50,
                'unit_of_measure' => 'each',
                'estimated_unit_price' => 450,
                'currency' => 'USD',
            ],
            [
                'name' => 'Bamboo Adjustable Desk',
                'quantity' => 50,
                'unit_of_measure' => 'each',
                'estimated_unit_price' => 950,
                'currency' => 'USD',
            ],
            [
                'name' => 'Solar-powered Desk Lamp',
                'quantity' => 50,
                'unit_of_measure' => 'each',
                'estimated_unit_price' => 120,
                'currency' => 'USD',
            ],
            [
                'name' => 'Furniture Maintenance Contract (Annual)',
                'quantity' => 1,
                'unit_of_measure' => 'year',
                'estimated_unit_price' => 2400,
                'currency' => 'USD',
            ],
        ]);

        return $requisition;
    }

    private function seedCollaboration(Tenant $tenant, Requisition $requisition, User $requester, User $buyer): void
    {
        CollaborationComment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'subject_type' => $requisition::class, 'subject_id' => $requisition->id, 'body' => 'I have updated the specification for the Eco-Chairs. They must be made from at least 60% ocean plastic.'],
            [
                'user_id' => $requester->id,
                'created_at' => '2026-05-18 11:30:00',
            ]
        );

        CollaborationComment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'subject_type' => $requisition::class, 'subject_id' => $requisition->id, 'body' => 'Noted. I will ensure the RFQ explicitly mentions the 60% threshold for all invited vendors.'],
            [
                'user_id' => $buyer->id,
                'created_at' => '2026-05-18 14:15:00',
            ]
        );
    }

    private function seedApprovalWorkflow(Tenant $tenant, Requisition $requisition, User $finance, User $buyer): void
    {
        $policy = ApprovalPolicy::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'High Value ESG Spend Policy'],
            [
                'description' => 'Mandatory 3-stage review for all ESG-critical spend over $50k.',
                'subject_type' => 'requisition',
                'status' => ApprovalPolicyStatus::Active,
                'created_by' => $finance->id,
            ]
        );

        $version = ApprovalPolicyVersion::query()->updateOrCreate(
            ['approval_policy_id' => $policy->id, 'version_number' => 1],
            [
                'tenant_id' => $tenant->id,
                'subject_type' => 'requisition',
                'status' => ApprovalPolicyVersionStatus::Published,
                'priority' => 200,
                'rules' => [['field' => 'amount', 'operator' => 'gte', 'value' => 50000]],
                'route_template' => [
                    'stages' => [
                        ['name' => 'Manager Review', 'completionRule' => 'all'],
                        ['name' => 'Compliance Review', 'completionRule' => 'any'],
                        ['name' => 'Finance Final', 'completionRule' => 'all'],
                    ],
                ],
                'published_by' => $finance->id,
                'published_at' => '2026-05-01 09:00:00',
            ]
        );

        $instance = ApprovalInstance::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'subject_type' => $requisition::class, 'subject_id' => $requisition->id],
            [
                'approval_policy_version_id' => $version->id,
                'status' => ApprovalInstanceStatus::Approved,
                'current_stage_sequence' => 3,
                'started_at' => '2026-05-18 10:05:00',
                'completed_at' => '2026-05-20 16:00:00',
            ]
        );

        // Stage 1: Completed
        $stage1 = ApprovalStage::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'approval_instance_id' => $instance->id, 'sequence' => 1],
            [
                'name' => 'Manager Review',
                'status' => ApprovalStageStatus::Completed,
                'activated_at' => '2026-05-18 10:05:00',
                'completed_at' => '2026-05-18 16:30:00',
            ]
        );

        ApprovalTask::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'approval_instance_id' => $instance->id, 'approval_stage_id' => $stage1->id],
            [
                'subject_type' => $requisition::class,
                'subject_id' => $requisition->id,
                'assignee_id' => $finance->id, // Finance manager acting as dept manager
                'title' => 'Approve Requisition for Furniture Bundle',
                'status' => ApprovalTaskStatus::Approved,
                'decision' => 'approved',
                'assigned_at' => '2026-05-18 10:05:00',
                'decided_at' => '2026-05-18 16:30:00',
                'decided_by_id' => $finance->id,
            ]
        );

        // Stage 2: Completed
        $stage2 = ApprovalStage::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'approval_instance_id' => $instance->id, 'sequence' => 2],
            [
                'name' => 'Compliance Review',
                'status' => ApprovalStageStatus::Completed,
                'activated_at' => '2026-05-18 16:30:00',
                'completed_at' => '2026-05-19 11:00:00',
            ]
        );

        ApprovalTask::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'approval_instance_id' => $instance->id, 'approval_stage_id' => $stage2->id],
            [
                'subject_type' => $requisition::class,
                'subject_id' => $requisition->id,
                'assignee_id' => $buyer->id, // Buyer acting as compliance
                'title' => 'Sustainability Compliance Check',
                'status' => ApprovalTaskStatus::Approved,
                'decision' => 'approved',
                'assigned_at' => '2026-05-18 16:30:00',
                'decided_at' => '2026-05-19 11:00:00',
                'decided_by_id' => $buyer->id,
            ]
        );

        // Stage 3: Completed
        $stage3 = ApprovalStage::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'approval_instance_id' => $instance->id, 'sequence' => 3],
            [
                'name' => 'Finance Final',
                'status' => ApprovalStageStatus::Completed,
                'activated_at' => '2026-05-19 11:00:00',
                'completed_at' => '2026-05-20 16:00:00',
            ]
        );

        ApprovalTask::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'approval_instance_id' => $instance->id, 'approval_stage_id' => $stage3->id],
            [
                'subject_type' => $requisition::class,
                'subject_id' => $requisition->id,
                'assignee_id' => $finance->id,
                'title' => 'Final Financial Award Approval',
                'status' => ApprovalTaskStatus::Approved,
                'decision' => 'approved',
                'assigned_at' => '2026-05-19 11:00:00',
                'decided_at' => '2026-05-20 16:00:00',
                'decided_by_id' => $finance->id,
            ]
        );

        $requisition->approval_instance_id = $instance->id;
        $requisition->save();
    }

    private function seedRfq(Tenant $tenant, ProcurementProject $project, Requisition $requisition): Rfq
    {
        return Rfq::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => self::RFQ_NUMBER],
            [
                'project_id' => $project->id,
                'requisition_id' => $requisition->id,
                'title' => 'Sustainable Furniture RFQ - East Wing',
                'status' => RfqStatus::Closed,
                'due_at' => '2026-05-25 12:00:00',
                'metadata' => ['invited_vendors' => 3, 'esg_requirement' => '60% recycled minimum'],
            ]
        );
    }

    private function seedInvitations(DemoSeedContext $context, Rfq $rfq): void
    {
        $vendors = ['atlas', 'northstar', 'greenline'];
        foreach ($vendors as $key) {
            $vendor = $context->vendors->get($key);
            RfqInvitation::query()->updateOrCreate(
                ['tenant_id' => $rfq->tenant_id, 'rfq_id' => $rfq->id, 'vendor_id' => $vendor->id],
                [
                    'status' => RfqInvitationStatus::Responded,
                    'invited_at' => '2026-05-20 09:00:00',
                    'responded_at' => '2026-05-23 10:00:00',
                ]
            );
        }
    }

    private function seedQuotations(DemoSeedContext $context, Rfq $rfq, User $buyer): void
    {
        // 1. Greenline - Single Version
        $greenline = $context->vendors->get('greenline');
        $quoG = Quotation::query()->updateOrCreate(
            ['tenant_id' => $rfq->tenant_id, 'rfq_id' => $rfq->id, 'vendor_id' => $greenline->id],
            [
                'number' => 'QUO-2026-SUSTAIN-G',
                'status' => QuotationStatus::Received,
                'total_amount' => 82500,
                'currency' => 'USD',
            ]
        );
        $context->quotations->put('greenline-quo', $quoG);

        $verG = $quoG->versions()->updateOrCreate(
            ['version_number' => 1],
            [
                'tenant_id' => $rfq->tenant_id,
                'status' => QuotationStatus::Received,
                'submission_source' => QuotationSubmissionSource::Manual,
                'submitted_at' => '2026-05-23 09:15:00',
                'submitted_by_user_id' => $buyer->id,
                'is_current' => true,
                'quotation_reference' => 'GL-9922',
                'total_amount' => 82500,
                'currency' => 'USD',
                'lead_time_days' => 14,
                'metadata' => ['materials' => '100% Sustainable'],
            ]
        );

        $verG->lineItems()->createMany([
            ['position' => 1, 'name' => 'Eco-Chair Model S', 'quantity' => 50, 'unit_price' => 480, 'total_price' => 24000],
            ['position' => 2, 'name' => 'Bamboo Desk V2', 'quantity' => 50, 'unit_price' => 1050, 'total_price' => 52500],
            ['position' => 3, 'name' => 'Solar Lamp X', 'quantity' => 50, 'unit_price' => 120, 'total_price' => 6000],
        ]);

        // 2. Northstar - Two Versions
        $northstar = $context->vendors->get('northstar');
        $quoN = Quotation::query()->updateOrCreate(
            ['tenant_id' => $rfq->tenant_id, 'rfq_id' => $rfq->id, 'vendor_id' => $northstar->id],
            [
                'number' => 'QUO-2026-SUSTAIN-N',
                'status' => QuotationStatus::Received,
                'total_amount' => 74500,
                'currency' => 'USD',
            ]
        );
        $context->quotations->put('northstar-quo', $quoN);

        // v1: Superseded
        $verN1 = $quoN->versions()->updateOrCreate(
            ['version_number' => 1],
            [
                'tenant_id' => $rfq->tenant_id,
                'status' => QuotationStatus::Received,
                'submission_source' => QuotationSubmissionSource::VendorPortal,
                'submitted_at' => '2026-05-21 14:00:00',
                'is_current' => false,
                'superseded_at' => '2026-05-24 10:00:00',
                'total_amount' => 89000,
                'currency' => 'USD',
                'lead_time_days' => 21,
            ]
        );
        $verN1->lineItems()->createMany([
            ['position' => 1, 'name' => 'Premium Ergonomic Chair', 'quantity' => 50, 'unit_price' => 550, 'total_price' => 27500],
            ['position' => 2, 'name' => 'Industrial Desk', 'quantity' => 50, 'unit_price' => 1100, 'total_price' => 55000],
            ['position' => 3, 'name' => 'Standard Lamp', 'quantity' => 50, 'unit_price' => 130, 'total_price' => 6500],
        ]);

        // v2: Current
        $verN2 = $quoN->versions()->updateOrCreate(
            ['version_number' => 2],
            [
                'tenant_id' => $rfq->tenant_id,
                'status' => QuotationStatus::Received,
                'submission_source' => QuotationSubmissionSource::VendorPortal,
                'submitted_at' => '2026-05-24 10:00:00',
                'is_current' => true,
                'total_amount' => 77000,
                'currency' => 'USD',
                'lead_time_days' => 21,
                'vendor_notes' => 'Revised pricing based on volume negotiation.',
            ]
        );
        $verN2->lineItems()->createMany([
            ['position' => 1, 'name' => 'Premium Ergonomic Chair', 'quantity' => 50, 'unit_price' => 450, 'total_price' => 22500],
            ['position' => 2, 'name' => 'Industrial Desk', 'quantity' => 50, 'unit_price' => 900, 'total_price' => 45000],
            ['position' => 3, 'name' => 'Standard Lamp', 'quantity' => 50, 'unit_price' => 140, 'total_price' => 7000],
        ]);

        // 3. Atlas - Single Version
        $atlas = $context->vendors->get('atlas');
        $quoA = Quotation::query()->updateOrCreate(
            ['tenant_id' => $rfq->tenant_id, 'rfq_id' => $rfq->id, 'vendor_id' => $atlas->id],
            [
                'number' => 'QUO-2026-SUSTAIN-A',
                'status' => QuotationStatus::Received,
                'total_amount' => 70800,
                'currency' => 'USD',
            ]
        );
        $context->quotations->put('atlas-quo', $quoA);

        $verA = $quoA->versions()->updateOrCreate(
            ['version_number' => 1],
            [
                'tenant_id' => $rfq->tenant_id,
                'status' => QuotationStatus::Received,
                'submission_source' => QuotationSubmissionSource::VendorPortal,
                'submitted_at' => '2026-05-22 16:45:00',
                'is_current' => true,
                'total_amount' => 70800,
                'currency' => 'USD',
                'lead_time_days' => 7,
            ]
        );
        $verA->lineItems()->createMany([
            ['position' => 1, 'name' => 'Basic Office Chair', 'quantity' => 50, 'unit_price' => 380, 'total_price' => 19000],
            ['position' => 2, 'name' => 'Standard Desk', 'quantity' => 50, 'unit_price' => 900, 'total_price' => 45000],
            ['position' => 3, 'name' => 'Budget Lamp', 'quantity' => 50, 'unit_price' => 100, 'total_price' => 5000],
            ['position' => 4, 'name' => 'Basic Maintenance', 'quantity' => 1, 'unit_price' => 1800, 'total_price' => 1800],
        ]);
    }

    private function seedNormalizations(DemoSeedContext $context, Rfq $rfq, User $buyer): void
    {
        $requisition = Rfq::query()->find($rfq->id)->requisition;
        $reqItems = $requisition->lineItems;

        foreach ($context->quotations as $key => $quotation) {
            if (!Str::contains($quotation->number, 'SUSTAIN')) continue;

            $version = $quotation->versions()->where('is_current', true)->first();
            $normalization = QuotationNormalization::query()->updateOrCreate(
                ['tenant_id' => $rfq->tenant_id, 'quotation_id' => $quotation->id, 'quotation_version_id' => $version->id],
                [
                    'status' => QuotationNormalizationStatus::ReadyForApproval,
                    'is_current_for_version' => true,
                    'normalized_at' => '2026-05-24 11:00:00',
                    'approved_at' => '2026-05-24 14:00:00',
                    'approved_by_user_id' => $buyer->id,
                    'algorithm_version' => '1.0.0',
                ]
            );

            $group = QuotationNormalizationLineGroup::query()->updateOrCreate(
                ['normalization_id' => $normalization->id, 'group_number' => 1],
                ['name' => 'Main Package', 'is_primary' => true]
            );

            foreach ($version->lineItems as $idx => $vItem) {
                $field = QuotationNormalizationField::query()->updateOrCreate(
                    ['normalization_id' => $normalization->id, 'source_field' => "line_item_{$idx}"],
                    [
                        'target_field' => 'line_item',
                        'normalized_value' => $vItem->name,
                        'confidence_score' => 0.95,
                    ]
                );

                // Map to requisition item
                $reqItem = $reqItems[$idx] ?? null;
                if ($reqItem) {
                    QuotationNormalizationLineMapping::query()->updateOrCreate(
                        ['normalization_id' => $normalization->id, 'quotation_version_line_item_id' => $vItem->id],
                        [
                            'requisition_line_item_id' => $reqItem->id,
                            'group_id' => $group->id,
                            'mapping_type' => QuotationNormalizationMappingType::Exact,
                            'confidence' => 1.0,
                        ]
                    );
                }
            }

            // Specific Issue for Atlas
            if (Str::contains($quotation->number, 'SUSTAIN-A')) {
                QuotationNormalizationIssue::query()->updateOrCreate(
                    ['normalization_id' => $normalization->id, 'field_key' => 'material_compliance'],
                    [
                        'severity' => QuotationNormalizationIssueSeverity::Error,
                        'status' => QuotationNormalizationIssueStatus::Open,
                        'label' => 'Sustainability Check Failed',
                        'description' => 'Vendor does not specify recycled material content. Fails ESG requirement.',
                    ]
                );
            }
        }
    }

    private function seedScoring(DemoSeedContext $context, Rfq $rfq, User $buyer): void
    {
        $template = QuotationScoringTemplate::query()->updateOrCreate(
            ['tenant_id' => $rfq->tenant_id, 'name' => 'Sustainable Furniture Evaluation'],
            [
                'description' => 'Weighting for ESG compliance, cost efficiency, and lead time.',
                'is_active' => true,
                'created_by_user_id' => $buyer->id,
            ]
        );

        $template->criteria()->delete();
        $critCost = $template->criteria()->create([
            'tenant_id' => $rfq->tenant_id,
            'name' => 'Total Commercial Cost',
            'category' => QuotationScoringCriterionCategory::Commercial,
            'weight' => 40,
            'display_order' => 1,
        ]);
        $critESG = $template->criteria()->create([
            'tenant_id' => $rfq->tenant_id,
            'name' => 'ESG & Sustainability Compliance',
            'category' => QuotationScoringCriterionCategory::Compliance,
            'weight' => 40,
            'display_order' => 2,
        ]);
        $critLead = $template->criteria()->create([
            'tenant_id' => $rfq->tenant_id,
            'name' => 'Lead Time & Delivery',
            'category' => QuotationScoringCriterionCategory::Technical,
            'weight' => 20,
            'display_order' => 3,
        ]);

        $scorecard = RfqScorecard::query()->updateOrCreate(
            ['tenant_id' => $rfq->tenant_id, 'rfq_id' => $rfq->id, 'template_id' => $template->id],
            [
                'template_name' => $template->name,
                'status' => RfqScorecardStatus::Completed,
                'applied_by_user_id' => $buyer->id,
                'applied_at' => '2026-05-24 15:00:00',
                'completed_by_user_id' => $buyer->id,
                'completed_at' => '2026-05-24 16:30:00',
            ]
        );

        foreach ($context->quotations as $quotation) {
            if (!Str::contains($quotation->number, 'SUSTAIN')) continue;

            $isGreen = Str::contains($quotation->number, 'SUSTAIN-G');
            $isNorth = Str::contains($quotation->number, 'SUSTAIN-N');

            $scorecard->entries()->createMany([
                [
                    'tenant_id' => $rfq->tenant_id,
                    'quotation_id' => $quotation->id,
                    'criterion_id' => $critCost->id,
                    'score' => $isGreen ? 3 : ($isNorth ? 4 : 5), // Atlas cheapest
                    'comment' => $isGreen ? 'Premium pricing.' : 'Competitive pricing.',
                ],
                [
                    'tenant_id' => $rfq->tenant_id,
                    'quotation_id' => $quotation->id,
                    'criterion_id' => $critESG->id,
                    'score' => $isGreen ? 5 : ($isNorth ? 3 : 1), // Greenline best ESG
                    'comment' => $isGreen ? '100% Recycled content confirmed.' : ($isNorth ? 'Partial compliance.' : 'No ESG info provided.'),
                ],
                [
                    'tenant_id' => $rfq->tenant_id,
                    'quotation_id' => $quotation->id,
                    'criterion_id' => $critLead->id,
                    'score' => $isGreen ? 4 : ($isNorth ? 3 : 5), // Atlas fastest
                    'comment' => 'Acceptable lead time.',
                ],
            ]);
        }
    }

    private function seedAwardRecommendation(DemoSeedContext $context, Rfq $rfq, User $buyer): void
    {
        $greenlineQuo = $context->quotations->get('greenline-quo');
        $recommendation = RfqAwardRecommendation::query()->updateOrCreate(
            ['tenant_id' => $rfq->tenant_id, 'rfq_id' => $rfq->id],
            [
                'quotation_id' => $greenlineQuo->id,
                'vendor_id' => $greenlineQuo->vendor_id,
                'status' => RfqAwardRecommendationStatus::Draft,
                'total_amount' => $greenlineQuo->total_amount,
                'currency' => $greenlineQuo->currency,
                'rationale' => 'Greenline Facilities is the only vendor meeting our 100% sustainable materials mandate. While they are not the lowest cost, they provide the best alignment with our HQ East Wing Charter.',
                'created_by_user_id' => $buyer->id,
                'metadata' => ['selection_priority' => 'ESG Compliance'],
            ]
        );

        $recommendation->evidence()->createMany([
            [
                'tenant_id' => $rfq->tenant_id,
                'evidence_type' => RfqAwardRecommendationEvidenceType::Quotation,
                'evidence_id' => $greenlineQuo->id,
                'label' => 'Greenline Final Quote',
            ],
            [
                'tenant_id' => $rfq->tenant_id,
                'evidence_type' => RfqAwardRecommendationEvidenceType::Scorecard,
                'evidence_id' => RfqScorecard::where('rfq_id', $rfq->id)->first()->id,
                'label' => 'Sustainable Furniture Evaluation Scorecard',
            ],
        ]);
    }
}
   }
}
