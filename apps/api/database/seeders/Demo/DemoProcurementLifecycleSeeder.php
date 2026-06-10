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
use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Project\Models\ProcurementProject;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationNormalizationField;
use Domains\Quotation\Models\QuotationNormalizationIssue;
use Domains\Quotation\Models\QuotationNormalizationLineGroup;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\Models\RfqScorecardCriterion;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\QuotationComparisonNoteSection;
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationIssueStatus;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Domains\Quotation\States\QuotationNormalizationPricingMode;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\RfqAwardRecommendationEvidenceType;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqScorecardStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Collection;

class DemoProcurementLifecycleSeeder
{
    private const SEEDED_AT = '2026-05-21 09:00:00';

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $requester = $context->users->get('requester');
        $buyer = $context->users->get('buyer');
        $approver = $context->users->get('approver');
        $finance = $context->users->get('finance');
        $admin = $context->users->get('admin');

        $project = $this->seedProject($tenant, $admin);
        $context->projects->put('sustainable-expansion', $project->refresh());

        $requisitions = $this->seedRequisitions($context, $tenant, $requester, $project);
        $this->seedCollaboration($tenant, $requisitions->get('sustainable'), $requester, $buyer);
        $this->seedSourcingIntakeStates($context, $tenant, $buyer, $requisitions);

        $requisitionPolicy = $this->approvalPolicy($tenant, $finance, 'requisition', 'Demo ESG requisition policy');
        $this->seedChangesRequestedApproval($context, $tenant, $requisitions->get('changes'), $requisitionPolicy, $approver);
        $this->seedDelegation($tenant, $finance, $buyer);

        $rfqs = $this->seedRfqs($context, $tenant, $project, $requisitions->get('sustainable'));
        $this->seedInvitations($context, $rfqs->get('sustainable'));

        $quotations = $this->seedQuotations($context, $tenant, $rfqs->get('sustainable'), $buyer);
        $normalizations = $this->seedNormalizations($context, $tenant, $quotations, $buyer);
        $this->seedComparisonNote($tenant, $rfqs->get('sustainable'), $quotations->get('greenline'), $buyer);

        $scorecard = $this->seedScoring($context, $tenant, $rfqs->get('sustainable'), $quotations, $buyer);
        $awardPolicy = $this->approvalPolicy($tenant, $finance, 'rfq_award_recommendation', 'Demo award recommendation policy');
        $this->seedAwardRecommendations(
            $context,
            $tenant,
            $rfqs,
            $quotations,
            $scorecard,
            $awardPolicy,
            $buyer,
            $finance,
        );
        $purchaseOrderPolicy = $this->approvalPolicy($tenant, $finance, 'purchase_order', 'Demo purchase order approval policy');
        $this->seedPurchaseOrders($context, $tenant, $buyer, $finance, $purchaseOrderPolicy);
    }

    private function seedProject(Tenant $tenant, User $owner): ProcurementProject
    {
        return ProcurementProject::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'PRJ-2026-SUSTAIN'],
            [
                'owner_id' => $owner->id,
                'name' => 'Sustainable Office Expansion 2026',
                'charter' => 'Coordinate requisition, sourcing, quotation evaluation, and award governance for the HQ East Wing.',
                'status' => 'active',
                'budget_amount' => 150000,
                'currency' => 'USD',
                'department' => 'Operations',
                'cost_center' => 'OPS-100',
                'target_start_date' => '2026-06-01',
                'target_completion_date' => '2026-12-31',
                'metadata' => ['demo' => true, 'priority' => 'high', 'esgCritical' => true],
            ],
        );
    }

    /**
     * @return Collection<string, Requisition>
     */
    private function seedRequisitions(DemoSeedContext $context, Tenant $tenant, User $requester, ProcurementProject $project): Collection
    {
        $records = collect([
            'sustainable' => ['REQ-2026-SUSTAIN', 'Ergonomic and eco-friendly furniture bundle', RequisitionStatus::Approved],
            'changes' => ['REQ-2026-CHANGE', 'Catering vendor clarification', RequisitionStatus::ChangesRequested],
            'withdrawn' => ['REQ-2026-WITHDRAWN', 'Legacy monitor replacement', RequisitionStatus::Withdrawn],
            'cancelled' => ['REQ-2026-CANCELLED', 'Temporary pop-up storage', RequisitionStatus::Cancelled],
            'draft' => ['REQ-2026-DRAFT-DEMO', 'Draft professional services request', RequisitionStatus::Draft],
        ]);

        return $records->map(function (array $record, string $key) use ($context, $tenant, $requester, $project): Requisition {
            [$number, $title, $status] = $record;
            $requisition = Requisition::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'number' => $number],
                [
                    'project_id' => $key === 'sustainable' ? $project->id : null,
                    'requester_id' => $requester->id,
                    'title' => $title,
                    'business_justification' => "Seeded P1 lifecycle scenario for {$title}.",
                    'needed_by_date' => $status === RequisitionStatus::Draft ? null : '2026-08-15',
                    'department' => $key === 'draft' ? 'Procurement' : 'Operations',
                    'cost_center' => $key === 'draft' ? 'OPS-110' : 'OPS-100',
                    'delivery_location' => 'HQ East Wing - Level 4',
                    'currency' => 'USD',
                    'status' => $status,
                    'submitted_at' => $status === RequisitionStatus::Draft ? null : self::SEEDED_AT,
                    'withdrawn_at' => $status === RequisitionStatus::Withdrawn ? '2026-05-22 10:00:00' : null,
                    'cancelled_at' => $status === RequisitionStatus::Cancelled ? '2026-05-22 11:00:00' : null,
                    'cancellation_reason' => $status === RequisitionStatus::Cancelled ? 'Demo cancellation for stale operational need.' : null,
                    'change_request_reason' => $status === RequisitionStatus::ChangesRequested ? 'Supplier scope needs clearer dietary constraints.' : null,
                ],
            );

            $requisition->lineItems()->delete();
            foreach ($this->lineItemsFor($key) as $lineItem) {
                $requisition->lineItems()->create($lineItem);
            }

            $context->requisitions->put($key === 'sustainable' ? 'sustainable-furniture' : $key, $requisition->refresh());

            return $requisition->refresh();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineItemsFor(string $key): array
    {
        if ($key !== 'sustainable') {
            return [[
                'name' => 'Seeded lifecycle line item',
                'quantity' => 1,
                'unit_of_measure' => 'each',
                'estimated_unit_price' => 2500,
                'currency' => 'USD',
            ]];
        }

        return [
            ['name' => 'Eco-Chair (recycled ocean plastic)', 'quantity' => 50, 'unit_of_measure' => 'each', 'estimated_unit_price' => 450, 'currency' => 'USD'],
            ['name' => 'Bamboo adjustable desk', 'quantity' => 50, 'unit_of_measure' => 'each', 'estimated_unit_price' => 950, 'currency' => 'USD'],
            ['name' => 'Solar-powered desk lamp', 'quantity' => 50, 'unit_of_measure' => 'each', 'estimated_unit_price' => 120, 'currency' => 'USD'],
        ];
    }

    private function seedCollaboration(Tenant $tenant, Requisition $requisition, User $requester, User $buyer): void
    {
        foreach ([
            [$requester, 'The Eco-Chairs must contain at least 60% recycled ocean plastic.'],
            [$buyer, 'I will include the recycled-material threshold in the RFQ requirements and evaluation notes.'],
        ] as [$author, $body]) {
            CollaborationComment::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'subject_type' => Requisition::class, 'subject_id' => $requisition->id, 'body' => $body],
                ['author_id' => $author->id],
            );
        }
    }

    /**
     * @param  Collection<string, Requisition>  $requisitions
     */
    private function seedSourcingIntakeStates(DemoSeedContext $context, Tenant $tenant, User $buyer, Collection $requisitions): void
    {
        $records = [
            'sustainable-in-review' => [$requisitions->get('sustainable'), SourcingIntakeStatus::InReview, SourcingPath::NeedsRfq, 'Facilities', 'Furniture', null],
            'changes-clarification' => [$requisitions->get('changes'), SourcingIntakeStatus::ClarificationRequested, SourcingPath::NeedsClarification, 'Facilities', 'Catering', 'Requester must clarify dietary requirements.'],
            'withdrawn-direct-award' => [$requisitions->get('withdrawn'), SourcingIntakeStatus::DirectAwardRecorded, SourcingPath::DirectAward, 'IT Hardware', 'Displays', 'Demo direct-award state.'],
            'cancelled-closed' => [$requisitions->get('cancelled'), SourcingIntakeStatus::Closed, SourcingPath::NoSourcingRequired, 'Operations', 'Storage', 'Demo closed sourcing state.'],
        ];

        foreach ($records as $key => [$requisition, $status, $path, $category, $subcategory, $reason]) {
            $review = SourcingIntakeReview::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'requisition_id' => $requisition->id],
                [
                    'project_id' => $requisition->project_id,
                    'assigned_buyer_id' => $buyer->id,
                    'status' => $status,
                    'sourcing_path' => $path,
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'urgency' => 'standard',
                    'complexity' => 'medium',
                    'target_decision_date' => '2026-05-27',
                    'checklist' => [
                        ['key' => 'specification_complete', 'label' => 'Specification complete', 'complete' => $status !== SourcingIntakeStatus::ClarificationRequested],
                        ['key' => 'budget_clear', 'label' => 'Budget clear', 'complete' => true],
                    ],
                    'internal_notes' => 'Seeded P1 lifecycle sourcing state.',
                    'decision_reason' => $reason,
                    'claimed_at' => '2026-05-21 09:30:00',
                    'decided_at' => in_array($status, [SourcingIntakeStatus::DirectAwardRecorded, SourcingIntakeStatus::Closed], true) ? '2026-05-21 12:00:00' : null,
                ],
            );

            $context->sourcingIntakeReviews->put($key, $review->refresh());
        }
    }

    private function seedChangesRequestedApproval(
        DemoSeedContext $context,
        Tenant $tenant,
        Requisition $requisition,
        ApprovalPolicyVersion $policyVersion,
        User $approver,
    ): void {
        $task = $this->seedApprovalRoute(
            tenant: $tenant,
            subject: $requisition,
            policyVersion: $policyVersion,
            assignee: $approver,
            title: 'Sustainability compliance review',
            instanceStatus: ApprovalInstanceStatus::ChangesRequested,
            taskStatus: ApprovalTaskStatus::ChangesRequested,
            startedAt: '2026-05-21 10:00:00',
            dueAt: '2026-05-23 10:00:00',
            decidedAt: '2026-05-21 13:00:00',
            decision: 'changes_requested',
            decisionReason: 'The catering requisition must identify reusable serviceware requirements.',
        );

        $context->approvalTasks->put('sustainability-changes-requested', $task->refresh());
        $requisition->forceFill(['approval_instance_id' => $task->approval_instance_id])->save();
    }

    private function seedDelegation(Tenant $tenant, User $finance, User $buyer): void
    {
        ApprovalDelegation::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'delegator_id' => $finance->id,
                'delegate_id' => $buyer->id,
                'scope' => 'task_specific',
            ],
            [
                'starts_at' => '2026-05-20 00:00:00',
                'ends_at' => '2026-05-31 23:59:59',
                'status' => ApprovalDelegationStatus::Active,
                'reason' => 'Seeded delegation for approval queue demonstrations.',
                'created_by' => $finance->id,
            ],
        );
    }

    /**
     * @return Collection<string, Rfq>
     */
    private function seedRfqs(DemoSeedContext $context, Tenant $tenant, ProcurementProject $project, Requisition $requisition): Collection
    {
        $records = [
            'sustainable' => ['RFQ-2026-SUSTAIN', 'Sustainable Furniture RFQ - East Wing', RfqStatus::Open, '2026-05-29 12:00:00'],
            'draft' => ['RFQ-2026-DRAFT', 'Draft furniture alternate RFQ', RfqStatus::Draft, null],
            'cancelled' => ['RFQ-2026-CANCELLED', 'Cancelled storage services RFQ', RfqStatus::Cancelled, '2026-05-25 12:00:00'],
        ];

        return collect($records)->map(function (array $record, string $key) use ($context, $tenant, $project, $requisition): Rfq {
            [$number, $title, $status, $dueAt] = $record;
            $rfq = Rfq::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'number' => $number],
                [
                    'project_id' => $project->id,
                    'requisition_id' => $requisition->id,
                    'title' => $title,
                    'status' => $status,
                    'due_at' => $dueAt,
                    'response_due_at' => $dueAt,
                    'scope_summary' => 'Seeded RFQ scope for P1 lifecycle demonstration.',
                    'response_instructions' => 'Submit structured line pricing, sustainability evidence, and lead-time commitments.',
                    'required_documents' => [
                        ['key' => 'quotation_pdf', 'label' => 'Quotation PDF', 'required' => true],
                        ['key' => 'esg_certificate', 'label' => 'Sustainability certificate', 'required' => false],
                    ],
                    'line_items' => [
                        ['name' => 'Eco-Chair', 'quantity' => 50, 'unit' => 'each'],
                        ['name' => 'Bamboo desk', 'quantity' => 50, 'unit' => 'each'],
                        ['name' => 'Solar lamp', 'quantity' => 50, 'unit' => 'each'],
                    ],
                    'evaluation_notes' => 'Balance total cost, ESG compliance, and delivery confidence.',
                    'cancel_reason' => $status === RfqStatus::Cancelled ? 'Seeded cancellation state.' : null,
                    'cancelled_at' => $status === RfqStatus::Cancelled ? '2026-05-22 15:00:00' : null,
                    'metadata' => ['demo' => true, 'lifecycle' => $key],
                ],
            );

            $context->rfqs->put($key === 'sustainable' ? 'sustainable-furniture-rfq' : "p1-{$key}-rfq", $rfq->refresh());

            return $rfq->refresh();
        });
    }

    private function seedInvitations(DemoSeedContext $context, Rfq $rfq): void
    {
        $statusByVendor = [
            'atlas' => RfqInvitationStatus::Pending,
            'northstar' => RfqInvitationStatus::Sent,
            'greenline' => RfqInvitationStatus::Acknowledged,
            'secureworks' => RfqInvitationStatus::Declined,
            'papertrail' => RfqInvitationStatus::Expired,
            'byteforge' => RfqInvitationStatus::Cancelled,
        ];

        foreach ($statusByVendor as $vendorKey => $status) {
            $vendor = $context->vendors->get($vendorKey);
            RfqInvitation::query()->updateOrCreate(
                ['tenant_id' => $rfq->tenant_id, 'rfq_id' => $rfq->id, 'vendor_id' => $vendor->id],
                [
                    'status' => $status,
                    'portal_token_hash' => hash('sha256', "demo-{$rfq->number}-{$vendorKey}"),
                    'portal_token_created_at' => '2026-05-21 09:00:00',
                    'portal_token_expires_at' => '2026-06-30 23:59:59',
                    'response_due_at' => '2026-05-29 12:00:00',
                    'sent_at' => in_array($status, [RfqInvitationStatus::Sent, RfqInvitationStatus::Acknowledged, RfqInvitationStatus::Declined, RfqInvitationStatus::Expired], true) ? '2026-05-21 09:00:00' : null,
                    'acknowledged_at' => $status === RfqInvitationStatus::Acknowledged ? '2026-05-21 11:00:00' : null,
                    'declined_at' => $status === RfqInvitationStatus::Declined ? '2026-05-21 12:00:00' : null,
                    'expired_at' => $status === RfqInvitationStatus::Expired ? '2026-05-30 00:00:00' : null,
                    'cancelled_at' => $status === RfqInvitationStatus::Cancelled ? '2026-05-21 13:00:00' : null,
                    'metadata' => ['demo' => true],
                ],
            );
        }
    }

    /**
     * @return Collection<string, Quotation>
     */
    private function seedQuotations(DemoSeedContext $context, Tenant $tenant, Rfq $rfq, User $buyer): Collection
    {
        $records = [
            'greenline' => ['greenline', 'QUO-2026-SUSTAIN-G', 82500, 14, QuotationSubmissionSource::BuyerUpload],
            'northstar' => ['northstar', 'QUO-2026-SUSTAIN-N', 77000, 21, QuotationSubmissionSource::VendorPortal],
            'atlas' => ['atlas', 'QUO-2026-SUSTAIN-A', 70800, 7, QuotationSubmissionSource::VendorPortal],
        ];

        return collect($records)->map(function (array $record, string $key) use ($context, $tenant, $rfq, $buyer): Quotation {
            [$vendorKey, $number, $total, $leadTime, $source] = $record;
            $vendor = $context->vendors->get($vendorKey);
            $invitation = RfqInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->where('vendor_id', $vendor->id)
                ->first();

            $quotation = Quotation::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'number' => $number],
                [
                    'rfq_id' => $rfq->id,
                    'vendor_id' => $vendor->id,
                    'rfq_invitation_id' => $invitation?->id,
                    'status' => QuotationStatus::Received,
                    'submission_source' => $source,
                    'submitted_at' => '2026-05-24 10:00:00',
                    'submitted_by_user_id' => $source === QuotationSubmissionSource::BuyerUpload ? $buyer->id : null,
                    'submitted_by_vendor_contact' => $source === QuotationSubmissionSource::VendorPortal ? ['name' => "{$vendor->name} Portal Contact"] : null,
                    'file_count' => $source === QuotationSubmissionSource::VendorPortal ? 1 : 0,
                    'latest_received_at' => '2026-05-24 10:00:00',
                    'quotation_reference' => "{$number}-REF",
                    'currency' => 'USD',
                    'subtotal_amount' => $total - 1500,
                    'tax_amount' => 0,
                    'freight_amount' => 1500,
                    'discount_amount' => 0,
                    'total_amount' => $total,
                    'lead_time_days' => $leadTime,
                    'payment_terms' => $key === 'atlas' ? 'Net 15' : 'Net 30',
                    'delivery_terms' => 'Delivered duty paid',
                    'manual_entry_complete' => true,
                    'manual_entry_saved_at' => '2026-05-24 10:05:00',
                    'manual_entry_saved_source' => $source->value,
                    'metadata' => ['demo' => true, 'vendorKey' => $key],
                ],
            );

            $versions = $key === 'northstar'
                ? [[1, 89000, false, '2026-05-22 12:00:00'], [2, $total, true, null]]
                : [[1, $total, true, null]];

            foreach ($versions as [$versionNumber, $versionTotal, $isCurrent, $supersededAt]) {
                $version = $quotation->versions()->updateOrCreate(
                    ['version_number' => $versionNumber],
                    [
                        'tenant_id' => $tenant->id,
                        'status' => QuotationStatus::Received,
                        'submission_source' => $source,
                        'submitted_at' => $isCurrent ? '2026-05-24 10:00:00' : '2026-05-22 12:00:00',
                        'submitted_by_user_id' => $source === QuotationSubmissionSource::BuyerUpload ? $buyer->id : null,
                        'is_current' => $isCurrent,
                        'superseded_at' => $supersededAt,
                        'quotation_reference' => "{$number}-V{$versionNumber}",
                        'currency' => 'USD',
                        'subtotal_amount' => $versionTotal - 1500,
                        'freight_amount' => 1500,
                        'total_amount' => $versionTotal,
                        'payment_terms' => $key === 'atlas' ? 'Net 15' : 'Net 30',
                        'delivery_terms' => 'Delivered duty paid',
                        'lead_time_days' => $leadTime,
                        'manual_entry_complete' => true,
                        'attachment_snapshots' => [['filename' => strtolower($number).'.pdf', 'source' => $source->value]],
                        'metadata' => ['demo' => true],
                    ],
                );

                $version->lineItems()->delete();
                foreach ($this->quotationLines($key, $versionTotal) as $line) {
                    $version->lineItems()->create(['tenant_id' => $tenant->id, ...$line]);
                }

                if ($isCurrent) {
                    $quotation->forceFill([
                        'current_version_id' => $version->id,
                        'version_count' => count($versions),
                    ])->save();
                }
            }

            $context->quotations->put("{$key}-quo", $quotation->refresh());

            return $quotation->refresh();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quotationLines(string $vendorKey, int $total): array
    {
        return [
            ['position' => 1, 'description' => "{$vendorKey} ergonomic chair", 'quantity' => 50, 'unit' => 'each', 'unit_price' => 400, 'subtotal_amount' => 20000, 'total_amount' => 20000, 'lead_time_days' => 14, 'compliance_status' => $vendorKey === 'atlas' ? 'exception' : 'compliant'],
            ['position' => 2, 'description' => "{$vendorKey} adjustable desk", 'quantity' => 50, 'unit' => 'each', 'unit_price' => 900, 'subtotal_amount' => 45000, 'total_amount' => 45000, 'lead_time_days' => 14, 'compliance_status' => 'compliant'],
            ['position' => 3, 'description' => "{$vendorKey} desk lamp package", 'quantity' => 50, 'unit' => 'each', 'unit_price' => max(1, ($total - 65000) / 50), 'subtotal_amount' => $total - 65000, 'total_amount' => $total - 65000, 'lead_time_days' => 14, 'compliance_status' => 'compliant'],
        ];
    }

    /**
     * @param  Collection<string, Quotation>  $quotations
     * @return Collection<string, QuotationNormalization>
     */
    private function seedNormalizations(DemoSeedContext $context, Tenant $tenant, Collection $quotations, User $buyer): Collection
    {
        $statuses = [
            'greenline' => QuotationNormalizationStatus::Approved,
            'northstar' => QuotationNormalizationStatus::ApprovedWithWarnings,
            'atlas' => QuotationNormalizationStatus::NeedsReview,
        ];

        $normalizations = collect();
        foreach ($statuses as $key => $status) {
            $quotation = $quotations->get($key);
            $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();
            $normalization = $this->normalizationFor($tenant, $quotation, $version, $status, $buyer);
            $context->quotationNormalizations->put("{$key}-normalization", $normalization->refresh());
            $normalizations->put($key, $normalization->refresh());
        }

        $oldNorthstarVersion = QuotationVersion::query()
            ->where('quotation_id', $quotations->get('northstar')->id)
            ->where('version_number', 1)
            ->firstOrFail();
        $failed = $this->normalizationFor($tenant, $quotations->get('northstar'), $oldNorthstarVersion, QuotationNormalizationStatus::Failed, $buyer);
        $context->quotationNormalizations->put('northstar-failed-normalization', $failed->refresh());
        $normalizations->put('northstar-failed', $failed->refresh());

        return $normalizations;
    }

    private function normalizationFor(
        Tenant $tenant,
        Quotation $quotation,
        QuotationVersion $version,
        QuotationNormalizationStatus $status,
        User $buyer,
    ): QuotationNormalization {
        $normalization = QuotationNormalization::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'quotation_version_id' => $version->id, 'normalization_revision' => 1],
            [
                'quotation_id' => $quotation->id,
                'status' => $status,
                'is_current_for_version' => $status !== QuotationNormalizationStatus::Failed,
                'normalized_at' => '2026-05-24 12:00:00',
                'approved_at' => in_array($status, [QuotationNormalizationStatus::Approved, QuotationNormalizationStatus::ApprovedWithWarnings], true) ? '2026-05-24 14:00:00' : null,
                'approved_by_user_id' => in_array($status, [QuotationNormalizationStatus::Approved, QuotationNormalizationStatus::ApprovedWithWarnings], true) ? $buyer->id : null,
                'approval_note' => $status === QuotationNormalizationStatus::ApprovedWithWarnings ? 'Approved with delivery-term warning for demo comparison.' : null,
                'algorithm_version' => 'deterministic-v1',
                'job_attempt_count' => $status === QuotationNormalizationStatus::Failed ? 2 : 1,
                'last_job_error' => $status === QuotationNormalizationStatus::Failed ? 'Seeded failed normalization state.' : null,
                'metadata' => ['demo' => true],
            ],
        );

        $normalization->fields()->delete();
        $normalization->lineGroups()->delete();
        $normalization->issues()->delete();

        foreach ([
            ['totalAmount', $version->total_amount, 'money', $version->currency],
            ['paymentTerms', $version->payment_terms, 'string', null],
            ['leadTimeDays', $version->lead_time_days, 'integer', null],
        ] as [$path, $value, $type, $currency]) {
            QuotationNormalizationField::query()->create([
                'tenant_id' => $tenant->id,
                'normalization_id' => $normalization->id,
                'field_path' => $path,
                'raw_value' => ['value' => $value],
                'normalized_value' => ['value' => $value],
                'data_type' => $type,
                'currency' => $currency,
                'confidence' => $status === QuotationNormalizationStatus::NeedsReview ? 0.6200 : 0.9600,
                'source' => 'seeded-demo',
                'provenance' => ['quotationVersionId' => (string) $version->id],
            ]);
        }

        $group = QuotationNormalizationLineGroup::query()->create([
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'group_number' => 1,
            'pricing_mode' => QuotationNormalizationPricingMode::PerLine,
            'description' => 'Main furniture bundle',
            'currency' => $version->currency,
            'notes' => 'Seeded comparison-ready line group.',
        ]);

        foreach ($version->lineItems as $lineItem) {
            $group->mappings()->create([
                'tenant_id' => $tenant->id,
                'quotation_version_line_item_id' => $lineItem->id,
                'mapping_type' => QuotationNormalizationMappingType::Full,
                'quantity' => $lineItem->quantity,
                'unit' => $lineItem->unit,
                'unit_price' => $lineItem->unit_price,
                'line_total' => $lineItem->total_amount,
                'buyer_note' => 'Seeded exact line mapping.',
            ]);
        }

        if ($status === QuotationNormalizationStatus::NeedsReview) {
            QuotationNormalizationIssue::query()->create([
                'tenant_id' => $tenant->id,
                'normalization_id' => $normalization->id,
                'severity' => QuotationNormalizationIssueSeverity::Blocking,
                'field_path' => 'complianceNotes',
                'issue_code' => 'missing_esg_certificate',
                'message' => 'Vendor did not provide recycled-material certification.',
                'status' => QuotationNormalizationIssueStatus::Open,
            ]);
        }

        if ($status === QuotationNormalizationStatus::ApprovedWithWarnings) {
            QuotationNormalizationIssue::query()->create([
                'tenant_id' => $tenant->id,
                'normalization_id' => $normalization->id,
                'severity' => QuotationNormalizationIssueSeverity::Warning,
                'field_path' => 'deliveryTerms',
                'issue_code' => 'delivery_terms_unstructured',
                'message' => 'Delivery terms were normalized from unstructured vendor notes.',
                'status' => QuotationNormalizationIssueStatus::Open,
            ]);
        }

        return $normalization->refresh();
    }

    private function seedComparisonNote(Tenant $tenant, Rfq $rfq, Quotation $quotation, User $buyer): void
    {
        QuotationComparisonNote::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'rfq_id' => $rfq->id, 'quotation_id' => $quotation->id, 'note' => 'Greenline has the strongest ESG evidence despite higher total cost.'],
            [
                'vendor_id' => $quotation->vendor_id,
                'section' => QuotationComparisonNoteSection::Overall,
                'created_by_user_id' => $buyer->id,
                'updated_by_user_id' => $buyer->id,
            ],
        );
    }

    /**
     * @param  Collection<string, Quotation>  $quotations
     */
    private function seedScoring(DemoSeedContext $context, Tenant $tenant, Rfq $rfq, Collection $quotations, User $buyer): RfqScorecard
    {
        $template = QuotationScoringTemplate::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Sustainable Furniture Evaluation'],
            [
                'description' => 'Seeded weighting for cost, sustainability, and delivery confidence.',
                'is_active' => true,
                'created_by_user_id' => $buyer->id,
                'updated_by_user_id' => $buyer->id,
            ],
        );
        $template->criteria()->delete();
        foreach ([
            [QuotationScoringCriterionCategory::Cost, 'Total commercial cost', 40, 1],
            [QuotationScoringCriterionCategory::Sustainability, 'ESG and material compliance', 40, 2],
            [QuotationScoringCriterionCategory::Delivery, 'Lead time and delivery risk', 20, 3],
        ] as [$category, $label, $weight, $order]) {
            $template->criteria()->create([
                'tenant_id' => $tenant->id,
                'category' => $category,
                'label' => $label,
                'guidance' => "Score {$label} from 0 to 5.",
                'weight' => $weight,
                'max_score' => 5,
                'is_required' => true,
                'display_order' => $order,
            ]);
        }
        $context->quotationScoringTemplates->put('sustainable-furniture', $template->refresh());

        $scorecard = RfqScorecard::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'rfq_id' => $rfq->id],
            [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'template_description' => $template->description,
                'status' => RfqScorecardStatus::Completed,
                'applied_by_user_id' => $buyer->id,
                'applied_at' => '2026-05-24 15:00:00',
                'completed_by_user_id' => $buyer->id,
                'completed_at' => '2026-05-24 16:30:00',
            ],
        );
        $scorecard->entries()->delete();
        $scorecard->criteria()->delete();

        $criteria = collect();
        foreach ($template->criteria()->orderBy('display_order')->get() as $templateCriterion) {
            $criteria->put($templateCriterion->display_order, RfqScorecardCriterion::query()->create([
                'tenant_id' => $tenant->id,
                'scorecard_id' => $scorecard->id,
                'source_template_criterion_id' => $templateCriterion->id,
                'category' => $templateCriterion->category,
                'label' => $templateCriterion->label,
                'guidance' => $templateCriterion->guidance,
                'weight' => $templateCriterion->weight,
                'max_score' => $templateCriterion->max_score,
                'is_required' => $templateCriterion->is_required,
                'display_order' => $templateCriterion->display_order,
            ]));
        }

        $scores = [
            'greenline' => [3, 5, 4],
            'northstar' => [4, 3, 3],
            'atlas' => [5, 1, 5],
        ];
        foreach ($scores as $vendorKey => $vendorScores) {
            $quotation = $quotations->get($vendorKey);
            $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();
            foreach ($vendorScores as $index => $score) {
                $scorecard->entries()->create([
                    'tenant_id' => $tenant->id,
                    'scorecard_criterion_id' => $criteria->get($index + 1)->id,
                    'vendor_id' => $quotation->vendor_id,
                    'quotation_id' => $quotation->id,
                    'quotation_version_id' => $version->id,
                    'score' => $score,
                    'note' => $vendorKey === 'greenline' ? 'Strong ESG fit.' : 'Seeded tradeoff score.',
                    'scored_by_user_id' => $buyer->id,
                    'scored_at' => '2026-05-24 16:00:00',
                ]);
            }
        }

        $context->rfqScorecards->put('sustainable-furniture', $scorecard->refresh());

        return $scorecard->refresh();
    }

    /**
     * @param  Collection<string, Rfq>  $rfqs
     * @param  Collection<string, Quotation>  $quotations
     */
    private function seedAwardRecommendations(
        DemoSeedContext $context,
        Tenant $tenant,
        Collection $rfqs,
        Collection $quotations,
        RfqScorecard $scorecard,
        ApprovalPolicyVersion $policyVersion,
        User $buyer,
        User $finance,
    ): void {
        $greenline = $quotations->get('greenline');
        $greenlineVersion = QuotationVersion::query()->whereKey($greenline->current_version_id)->firstOrFail();

        $draft = $this->recommendation($tenant, $rfqs->get('draft'), $buyer, RfqAwardRecommendationStatus::Draft);
        $pending = $this->recommendation($tenant, $rfqs->get('cancelled'), $buyer, RfqAwardRecommendationStatus::PendingApproval, $greenline, $greenlineVersion, $scorecard);
        $routed = $this->recommendation($tenant, $rfqs->get('sustainable'), $buyer, RfqAwardRecommendationStatus::ApprovalRouted, $greenline, $greenlineVersion, $scorecard);
        $approved = $this->recommendation($tenant, $rfqs->get('sustainable'), $buyer, RfqAwardRecommendationStatus::Approved, $greenline, $greenlineVersion, $scorecard, 'Approved recommendation for seeded award governance.');
        $poInReview = $this->recommendationVariant($tenant, $rfqs->get('sustainable'), $buyer, RfqAwardRecommendationStatus::Approved, 'po-in-review-source', $greenline, $greenlineVersion, $scorecard);
        $poChangesRequested = $this->recommendationVariant($tenant, $rfqs->get('sustainable'), $buyer, RfqAwardRecommendationStatus::Approved, 'po-changes-requested-source', $greenline, $greenlineVersion, $scorecard);
        $poApproved = $this->recommendationVariant($tenant, $rfqs->get('sustainable'), $buyer, RfqAwardRecommendationStatus::Approved, 'po-approved-source', $greenline, $greenlineVersion, $scorecard);
        $poIssued = $this->recommendationVariant($tenant, $rfqs->get('sustainable'), $buyer, RfqAwardRecommendationStatus::Approved, 'po-issued-source', $greenline, $greenlineVersion, $scorecard);
        $poAcknowledged = $this->recommendationVariant($tenant, $rfqs->get('sustainable'), $buyer, RfqAwardRecommendationStatus::Approved, 'po-acknowledged-source', $greenline, $greenlineVersion, $scorecard);
        $poRejected = $this->recommendationVariant($tenant, $rfqs->get('sustainable'), $buyer, RfqAwardRecommendationStatus::Approved, 'po-rejected-source', $greenline, $greenlineVersion, $scorecard);

        $routedTask = $this->seedApprovalRoute(
            tenant: $tenant,
            subject: $routed,
            policyVersion: $policyVersion,
            assignee: $finance,
            title: 'Review award recommendation for RFQ-2026-SUSTAIN',
            instanceStatus: ApprovalInstanceStatus::Active,
            taskStatus: ApprovalTaskStatus::Active,
            startedAt: '2026-05-24 17:00:00',
            dueAt: '2026-05-26 17:00:00',
        );
        $routed->forceFill(['approval_instance_id' => $routedTask->approval_instance_id])->save();

        $approvedTask = $this->seedApprovalRoute(
            tenant: $tenant,
            subject: $approved,
            policyVersion: $policyVersion,
            assignee: $finance,
            title: 'Final award approval for RFQ-2026-SUSTAIN',
            instanceStatus: ApprovalInstanceStatus::Approved,
            taskStatus: ApprovalTaskStatus::Approved,
            startedAt: '2026-05-24 18:00:00',
            dueAt: '2026-05-26 18:00:00',
            decidedAt: '2026-05-25 10:00:00',
            decision: 'approved',
            decisionReason: 'ESG evidence outweighs the price premium.',
        );
        $approved->forceFill([
            'approval_instance_id' => $approvedTask->approval_instance_id,
            'approved_by_user_id' => $finance->id,
            'approved_at' => '2026-05-25 10:00:00',
        ])->save();

        foreach ([$approved, $routed] as $recommendation) {
            $recommendation->evidenceReferences()->delete();
            $recommendation->evidenceReferences()->createMany([
                ['tenant_id' => $tenant->id, 'evidence_type' => RfqAwardRecommendationEvidenceType::QuotationVersion, 'evidence_id' => (string) $greenlineVersion->id, 'label' => 'Greenline final quotation version'],
                ['tenant_id' => $tenant->id, 'evidence_type' => RfqAwardRecommendationEvidenceType::Scorecard, 'evidence_id' => (string) $scorecard->id, 'label' => 'Sustainable Furniture Evaluation'],
            ]);
        }

        $context->rfqAwardRecommendations->put('draft', $draft->refresh());
        $context->rfqAwardRecommendations->put('pending', $pending->refresh());
        $context->rfqAwardRecommendations->put('routed', $routed->refresh());
        $context->rfqAwardRecommendations->put('approved', $approved->refresh());
        $context->rfqAwardRecommendations->put('po-in-review-source', $poInReview->refresh());
        $context->rfqAwardRecommendations->put('po-changes-requested-source', $poChangesRequested->refresh());
        $context->rfqAwardRecommendations->put('po-approved-source', $poApproved->refresh());
        $context->rfqAwardRecommendations->put('po-issued-source', $poIssued->refresh());
        $context->rfqAwardRecommendations->put('po-acknowledged-source', $poAcknowledged->refresh());
        $context->rfqAwardRecommendations->put('po-rejected-source', $poRejected->refresh());
        $context->approvalTasks->put('award-routed', $routedTask->refresh());
        $context->approvalTasks->put('award-approved', $approvedTask->refresh());
    }

    private function seedPurchaseOrders(
        DemoSeedContext $context,
        Tenant $tenant,
        User $buyer,
        User $finance,
        ApprovalPolicyVersion $policyVersion,
    ): void
    {
        $records = [
            'draft' => [
                'handoffKey' => 'ready',
                'recommendationKey' => 'approved',
                'handoffNumber' => 'POH-2026-SUSTAIN-READY',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Ready,
                'poNumber' => 'PO-2026-SUSTAIN-DRAFT',
                'poStatus' => PurchaseOrderStatus::Draft,
                'lockVersion' => 1,
            ],
            'review' => [
                'handoffKey' => 'exported',
                'recommendationKey' => 'routed',
                'handoffNumber' => 'POH-2026-SUSTAIN-EXPORTED',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Exported,
                'poNumber' => 'PO-2026-SUSTAIN-REVIEW',
                'poStatus' => PurchaseOrderStatus::ReadyForReview,
                'lockVersion' => 2,
            ],
            'in-review' => [
                'handoffKey' => 'in-review-source',
                'recommendationKey' => 'po-in-review-source',
                'handoffNumber' => 'POH-2026-SUSTAIN-IN-REVIEW',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Exported,
                'poNumber' => 'PO-2026-SUSTAIN-IN-REVIEW',
                'poStatus' => PurchaseOrderStatus::InReview,
                'lockVersion' => 3,
            ],
            'changes-requested' => [
                'handoffKey' => 'changes-requested-source',
                'recommendationKey' => 'po-changes-requested-source',
                'handoffNumber' => 'POH-2026-SUSTAIN-CHANGES',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Exported,
                'poNumber' => 'PO-2026-SUSTAIN-CHANGES',
                'poStatus' => PurchaseOrderStatus::ChangesRequested,
                'lockVersion' => 4,
            ],
            'approved' => [
                'handoffKey' => 'approved-source',
                'recommendationKey' => 'po-approved-source',
                'handoffNumber' => 'POH-2026-SUSTAIN-APPROVED',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Exported,
                'poNumber' => 'PO-2026-SUSTAIN-APPROVED',
                'poStatus' => PurchaseOrderStatus::Approved,
                'lockVersion' => 4,
            ],
            'issued' => [
                'handoffKey' => 'issued-source',
                'recommendationKey' => 'po-issued-source',
                'handoffNumber' => 'POH-2026-SUSTAIN-ISSUED',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Exported,
                'poNumber' => 'PO-2026-SUSTAIN-ISSUED',
                'poStatus' => PurchaseOrderStatus::Issued,
                'lockVersion' => 5,
            ],
            'acknowledged' => [
                'handoffKey' => 'acknowledged-source',
                'recommendationKey' => 'po-acknowledged-source',
                'handoffNumber' => 'POH-2026-SUSTAIN-ACK',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Exported,
                'poNumber' => 'PO-2026-SUSTAIN-ACK',
                'poStatus' => PurchaseOrderStatus::Acknowledged,
                'lockVersion' => 6,
            ],
            'rejected' => [
                'handoffKey' => 'rejected-source',
                'recommendationKey' => 'po-rejected-source',
                'handoffNumber' => 'POH-2026-SUSTAIN-REJECTED',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Exported,
                'poNumber' => 'PO-2026-SUSTAIN-REJECTED',
                'poStatus' => PurchaseOrderStatus::Rejected,
                'lockVersion' => 4,
            ],
            'cancelled' => [
                'handoffKey' => 'cancelled-source',
                'recommendationKey' => 'pending',
                'handoffNumber' => 'POH-2026-SUSTAIN-CANCELLED',
                'handoffStatus' => PurchaseOrderRequestHandoffStatus::Exported,
                'poNumber' => 'PO-2026-SUSTAIN-CANCELLED',
                'poStatus' => PurchaseOrderStatus::Cancelled,
                'lockVersion' => 2,
            ],
        ];

        foreach ($records as $poKey => $record) {
            $recommendation = $context->rfqAwardRecommendations->get($record['recommendationKey'])->refresh();
            $quotation = Quotation::query()->findOrFail($recommendation->recommended_quotation_id);
            $version = QuotationVersion::query()->findOrFail($recommendation->recommended_quotation_version_id);
            $rfq = Rfq::query()->findOrFail($recommendation->rfq_id);
            $lineSnapshot = $this->purchaseOrderLineSnapshot($version);
            $sourceSnapshot = [
                'rfq' => ['id' => (string) $rfq->id, 'number' => $rfq->number, 'title' => $rfq->title],
                'vendor' => ['id' => (string) $quotation->vendor_id, 'name' => $quotation->vendor?->name],
                'quotation' => ['id' => (string) $quotation->id, 'number' => $quotation->number, 'totalAmount' => (string) $quotation->total_amount, 'currency' => $quotation->currency],
                'quotationVersion' => ['id' => (string) $version->id, 'versionNumber' => $version->version_number],
                'award' => ['recommendationId' => (string) $recommendation->id, 'rationale' => $recommendation->rationale],
            ];
            $approvalSnapshot = [
                'approvalInstanceId' => $recommendation->approval_instance_id !== null ? (string) $recommendation->approval_instance_id : null,
                'status' => $record['recommendationKey'] === 'approved' ? 'approved' : 'pending_approval',
                'finalDecision' => $record['recommendationKey'] === 'approved' ? 'approved' : null,
                'approvedAt' => $recommendation->approved_at?->toJSON(),
                'approvedBy' => $record['recommendationKey'] === 'approved' ? $finance->name : null,
                'stages' => [['stage' => 'Manager review', 'actor' => $finance->name, 'status' => $record['recommendationKey'] === 'approved' ? 'approved' : 'active']],
            ];
            $evidenceSnapshot = $recommendation->evidenceReferences()
                ->get()
                ->map(fn ($evidence): array => [
                    'type' => $evidence->evidence_type->value,
                    'id' => (string) $evidence->evidence_id,
                    'label' => $evidence->label,
                    'summary' => 'Seeded evidence for purchase order demo.',
                ])
                ->values()
                ->all();
            $supplierVersion = in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true)
                ? $this->purchaseOrderSupplierVersion($record, $quotation, $lineSnapshot, $sourceSnapshot, $approvalSnapshot)
                : null;

            $handoff = PurchaseOrderRequestHandoff::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'rfq_award_recommendation_id' => $recommendation->id],
                [
                    'approval_instance_id' => $recommendation->approval_instance_id,
                    'rfq_id' => $recommendation->rfq_id,
                    'requisition_id' => $rfq->requisition_id,
                    'project_id' => $rfq->project_id,
                    'vendor_id' => $quotation->vendor_id,
                    'quotation_id' => $quotation->id,
                    'quotation_version_id' => $version->id,
                    'number' => $record['handoffNumber'],
                    'status' => $record['handoffStatus'],
                    'currency' => $quotation->currency,
                    'subtotal_amount' => $quotation->subtotal_amount,
                    'tax_amount' => $quotation->tax_amount,
                    'freight_amount' => $quotation->freight_amount,
                    'discount_amount' => $quotation->discount_amount,
                    'total_amount' => $quotation->total_amount,
                    'requested_po_date' => '2026-06-03',
                    'delivery_attention' => 'Facilities receiving dock',
                    'finance_note' => 'Seeded purchase order demo for sustainable furniture.',
                    'export_memo' => $record['handoffStatus'] === PurchaseOrderRequestHandoffStatus::Exported ? 'Exported to ERP sandbox for demo testing.' : null,
                    'requested_by_user_id' => $buyer->id,
                    'ready_by_user_id' => $buyer->id,
                    'ready_at' => '2026-05-26 09:30:00',
                    'last_exported_by_user_id' => $record['handoffStatus'] === PurchaseOrderRequestHandoffStatus::Exported ? $buyer->id : null,
                    'last_exported_at' => $record['handoffStatus'] === PurchaseOrderRequestHandoffStatus::Exported ? '2026-05-26 10:00:00' : null,
                    'last_export_format' => $record['handoffStatus'] === PurchaseOrderRequestHandoffStatus::Exported ? 'json' : null,
                    'source_snapshot' => $sourceSnapshot,
                    'line_snapshot' => $lineSnapshot,
                    'approval_snapshot' => $approvalSnapshot,
                    'evidence_snapshot' => $evidenceSnapshot,
                    'readiness_warnings' => [],
                    'lock_version' => 1,
                ],
            );

            $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'purchase_order_request_handoff_id' => $handoff->id],
                [
                    'rfq_award_recommendation_id' => $recommendation->id,
                    'approval_instance_id' => $recommendation->approval_instance_id,
                    'rfq_id' => $recommendation->rfq_id,
                    'requisition_id' => $rfq->requisition_id,
                    'project_id' => $rfq->project_id,
                    'vendor_id' => $quotation->vendor_id,
                    'quotation_id' => $quotation->id,
                    'quotation_version_id' => $version->id,
                    'number' => $record['poNumber'],
                    'status' => $record['poStatus'],
                    'currency' => $quotation->currency,
                    'subtotal_amount' => $quotation->subtotal_amount,
                    'tax_amount' => $quotation->tax_amount,
                    'freight_amount' => $quotation->freight_amount,
                    'discount_amount' => $quotation->discount_amount,
                    'total_amount' => $quotation->total_amount,
                    'requested_po_date' => '2026-06-03',
                    'expected_delivery_date' => '2026-07-15',
                    'billing_name' => 'Acme Procurement Finance',
                    'billing_address' => ['line1' => 'Level 10, Acme Tower', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
                    'shipping_name' => 'HQ East Wing Facilities',
                    'shipping_address' => ['line1' => 'HQ East Wing - Level 4', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
                    'delivery_attention' => 'Facilities receiving dock',
                    'payment_terms' => 'Net 30',
                    'delivery_terms' => 'Delivered duty paid',
                    'buyer_note' => $record['poStatus'] === PurchaseOrderStatus::Cancelled ? 'Duplicate draft cancelled during demo cleanup.' : 'Seeded PO can be edited and progressed by the buyer.',
                    'finance_note' => 'Charge to OPS-100 sustainable expansion budget.',
                    'source_snapshot' => $sourceSnapshot,
                    'approval_snapshot' => $approvalSnapshot,
                    'evidence_snapshot' => $evidenceSnapshot,
                    'created_by_user_id' => $buyer->id,
                    'ready_for_review_by_user_id' => $record['poStatus'] === PurchaseOrderStatus::ReadyForReview ? $buyer->id : null,
                    'ready_for_review_at' => $record['poStatus'] === PurchaseOrderStatus::ReadyForReview ? '2026-05-26 11:00:00' : null,
                    'cancelled_by_user_id' => $record['poStatus'] === PurchaseOrderStatus::Cancelled ? $buyer->id : null,
                    'cancelled_at' => $record['poStatus'] === PurchaseOrderStatus::Cancelled ? '2026-05-26 12:00:00' : null,
                    'cancelled_reason' => $record['poStatus'] === PurchaseOrderStatus::Cancelled ? 'Duplicate draft replaced by PO-2026-SUSTAIN-REVIEW.' : null,
                    'approval_submitted_by_user_id' => in_array($record['poStatus'], [PurchaseOrderStatus::InReview, PurchaseOrderStatus::ChangesRequested, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged, PurchaseOrderStatus::Rejected], true) ? $buyer->id : null,
                    'approval_submitted_at' => in_array($record['poStatus'], [PurchaseOrderStatus::InReview, PurchaseOrderStatus::ChangesRequested, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged, PurchaseOrderStatus::Rejected], true) ? '2026-06-09 08:00:00' : null,
                    'approved_by_user_id' => in_array($record['poStatus'], [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? $finance->id : null,
                    'approved_at' => in_array($record['poStatus'], [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? '2026-06-09 12:00:00' : null,
                    'rejected_by_user_id' => $record['poStatus'] === PurchaseOrderStatus::Rejected ? $finance->id : null,
                    'rejected_at' => $record['poStatus'] === PurchaseOrderStatus::Rejected ? '2026-06-09 12:30:00' : null,
                    'rejected_reason' => $record['poStatus'] === PurchaseOrderStatus::Rejected ? 'Tax coding does not match the approved quotation.' : null,
                    'changes_requested_by_user_id' => $record['poStatus'] === PurchaseOrderStatus::ChangesRequested ? $finance->id : null,
                    'changes_requested_at' => $record['poStatus'] === PurchaseOrderStatus::ChangesRequested ? '2026-06-09 10:00:00' : null,
                    'changes_requested_reason' => $record['poStatus'] === PurchaseOrderStatus::ChangesRequested ? 'Payment terms and tax amount require correction.' : null,
                    'changes_requested_fields' => $record['poStatus'] === PurchaseOrderStatus::ChangesRequested ? ['taxAmount', 'paymentTerms'] : [],
                    'issued_by_user_id' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? $buyer->id : null,
                    'issued_at' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? '2026-06-10 10:00:00' : null,
                    'issue_method' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? 'manual_email' : null,
                    'supplier_contact_name' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? 'Priya Supplier' : null,
                    'supplier_contact_email' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? 'priya.supplier@example.com' : null,
                    'issue_message' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? 'Please confirm receipt and planned delivery date.' : null,
                    'supplier_version' => $supplierVersion,
                    'supplier_version_number' => $supplierVersion !== null ? 1 : 0,
                    'last_supplier_exported_by_user_id' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? $buyer->id : null,
                    'last_supplier_exported_at' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? '2026-06-10 10:05:00' : null,
                    'last_supplier_export_format' => in_array($record['poStatus'], [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true) ? 'json' : null,
                    'acknowledged_by_user_id' => $record['poStatus'] === PurchaseOrderStatus::Acknowledged ? $buyer->id : null,
                    'acknowledged_at' => $record['poStatus'] === PurchaseOrderStatus::Acknowledged ? '2026-06-10 11:00:00' : null,
                    'acknowledged_contact_name' => $record['poStatus'] === PurchaseOrderStatus::Acknowledged ? 'Priya Supplier' : null,
                    'acknowledgement_reference' => $record['poStatus'] === PurchaseOrderStatus::Acknowledged ? 'ACK-PO-100' : null,
                    'acknowledgement_note' => $record['poStatus'] === PurchaseOrderStatus::Acknowledged ? 'Supplier confirmed delivery in week 29.' : null,
                    'lock_version' => $record['lockVersion'],
                ],
            );

            $purchaseOrder->lines()->delete();
            foreach ($lineSnapshot as $line) {
                PurchaseOrderLine::query()->create([
                    'tenant_id' => $tenant->id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'source_line_id' => $line['id'],
                    'line_number' => $line['lineNumber'],
                    'description' => $line['description'],
                    'unit' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unitPrice'],
                    'subtotal_amount' => $line['subtotalAmount'],
                    'tax_amount' => $line['taxAmount'],
                    'freight_amount' => $line['freightAmount'],
                    'discount_amount' => $line['discountAmount'],
                    'total_amount' => $line['totalAmount'],
                    'currency' => $quotation->currency,
                    'delivery_location' => 'HQ East Wing - Level 4',
                    'source_snapshot' => $line,
                ]);
            }

            $purchaseOrder = $this->seedPurchaseOrderApprovalRoute($context, $tenant, $purchaseOrder->refresh(), $policyVersion, $buyer, $finance);
            $this->recordPurchaseOrderAudit($tenant, $buyer, $purchaseOrder->refresh());
            $context->purchaseOrderRequestHandoffs->put($record['handoffKey'], $handoff->refresh());
            $context->purchaseOrders->put($poKey, $purchaseOrder->refresh());
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function purchaseOrderLineSnapshot(QuotationVersion $version): array
    {
        return $version->lineItems()->get()->map(fn ($line): array => [
            'id' => (string) $line->id,
            'lineNumber' => (int) $line->position,
            'description' => $line->description,
            'quantity' => (string) $line->quantity,
            'unit' => $line->unit,
            'unitOfMeasure' => $line->unit,
            'unitPrice' => (string) $line->unit_price,
            'subtotalAmount' => (string) $line->subtotal_amount,
            'taxAmount' => null,
            'freightAmount' => null,
            'discountAmount' => null,
            'totalAmount' => (string) $line->total_amount,
            'lineTotal' => (string) $line->total_amount,
            'currency' => $version->currency,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<array<string, mixed>>  $lineSnapshot
     * @param  array<string, mixed>  $sourceSnapshot
     * @param  array<string, mixed>  $approvalSnapshot
     * @return array<string, mixed>
     */
    private function purchaseOrderSupplierVersion(
        array $record,
        Quotation $quotation,
        array $lineSnapshot,
        array $sourceSnapshot,
        array $approvalSnapshot,
    ): array {
        return [
            'versionNumber' => 1,
            'issuedAt' => '2026-06-10T02:00:00.000000Z',
            'issueMethod' => 'manual_email',
            'supplierContactName' => 'Priya Supplier',
            'supplierContactEmail' => 'priya.supplier@example.com',
            'message' => 'Please confirm receipt and planned delivery date.',
            'purchaseOrder' => [
                'number' => $record['poNumber'],
                'currency' => $quotation->currency,
                'subtotalAmount' => (string) $quotation->subtotal_amount,
                'taxAmount' => $quotation->tax_amount !== null ? (string) $quotation->tax_amount : null,
                'freightAmount' => $quotation->freight_amount !== null ? (string) $quotation->freight_amount : null,
                'discountAmount' => $quotation->discount_amount !== null ? (string) $quotation->discount_amount : null,
                'totalAmount' => (string) $quotation->total_amount,
                'requestedPoDate' => '2026-06-03',
                'expectedDeliveryDate' => '2026-07-15',
                'billingName' => 'Acme Procurement Finance',
                'shippingName' => 'HQ East Wing Facilities',
                'deliveryAttention' => 'Facilities receiving dock',
                'paymentTerms' => 'Net 30',
                'deliveryTerms' => 'Delivered duty paid',
            ],
            'vendor' => $sourceSnapshot['vendor'],
            'lines' => $lineSnapshot,
            'source' => [
                'rfqId' => $sourceSnapshot['rfq']['id'],
                'recommendationId' => $sourceSnapshot['award']['recommendationId'],
            ],
            'approval' => $approvalSnapshot,
        ];
    }

    private function recordPurchaseOrderAudit(Tenant $tenant, User $buyer, PurchaseOrder $purchaseOrder): void
    {
        $action = match ($purchaseOrder->statusState()) {
            PurchaseOrderStatus::Draft => 'purchase_order.created',
            PurchaseOrderStatus::ReadyForReview => 'purchase_order.ready_for_review',
            PurchaseOrderStatus::InReview => 'purchase_order.approval_submitted',
            PurchaseOrderStatus::ChangesRequested => 'purchase_order.changes_requested',
            PurchaseOrderStatus::Approved => 'purchase_order.approved',
            PurchaseOrderStatus::Issued => 'purchase_order.issued',
            PurchaseOrderStatus::Acknowledged => 'purchase_order.acknowledged',
            PurchaseOrderStatus::Rejected => 'purchase_order.rejected',
            PurchaseOrderStatus::Cancelled => 'purchase_order.cancelled',
        };

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $buyer,
            action: $action,
            subject: $purchaseOrder,
            metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: ['demo' => true]),
            after: $purchaseOrder->toArray(),
        ));
    }

    private function seedPurchaseOrderApprovalRoute(
        DemoSeedContext $context,
        Tenant $tenant,
        PurchaseOrder $purchaseOrder,
        ApprovalPolicyVersion $policyVersion,
        User $buyer,
        User $finance,
    ): PurchaseOrder {
        $status = $purchaseOrder->statusState();

        if (! in_array($status, [PurchaseOrderStatus::InReview, PurchaseOrderStatus::ChangesRequested, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Rejected], true)) {
            return $purchaseOrder;
        }

        $task = $this->seedApprovalRoute(
            tenant: $tenant,
            subject: $purchaseOrder,
            policyVersion: $policyVersion,
            assignee: $finance,
            title: "Review {$purchaseOrder->number}",
            instanceStatus: match ($status) {
                PurchaseOrderStatus::Approved => ApprovalInstanceStatus::Approved,
                PurchaseOrderStatus::ChangesRequested => ApprovalInstanceStatus::ChangesRequested,
                PurchaseOrderStatus::Rejected => ApprovalInstanceStatus::Rejected,
                default => ApprovalInstanceStatus::Active,
            },
            taskStatus: match ($status) {
                PurchaseOrderStatus::Approved => ApprovalTaskStatus::Approved,
                PurchaseOrderStatus::ChangesRequested => ApprovalTaskStatus::ChangesRequested,
                PurchaseOrderStatus::Rejected => ApprovalTaskStatus::Rejected,
                default => ApprovalTaskStatus::Active,
            },
            startedAt: '2026-06-09 08:00:00',
            dueAt: '2026-06-11 08:00:00',
            decidedAt: match ($status) {
                PurchaseOrderStatus::Approved => '2026-06-09 12:00:00',
                PurchaseOrderStatus::ChangesRequested => '2026-06-09 10:00:00',
                PurchaseOrderStatus::Rejected => '2026-06-09 12:30:00',
                default => null,
            },
            decision: match ($status) {
                PurchaseOrderStatus::Approved => 'approved',
                PurchaseOrderStatus::ChangesRequested => 'changes_requested',
                PurchaseOrderStatus::Rejected => 'rejected',
                default => null,
            },
            decisionReason: match ($status) {
                PurchaseOrderStatus::Approved => 'Approved for supplier issue demo.',
                PurchaseOrderStatus::ChangesRequested => 'Payment terms and tax amount require correction.',
                PurchaseOrderStatus::Rejected => 'Tax coding does not match the approved quotation.',
                default => null,
            },
        );

        $purchaseOrder->forceFill([
            'approval_instance_id' => $task->approval_instance_id,
            'approval_submitted_by_user_id' => $buyer->id,
            'approval_submitted_at' => '2026-06-09 08:00:00',
            'approved_by_user_id' => $status === PurchaseOrderStatus::Approved ? $finance->id : null,
            'approved_at' => $status === PurchaseOrderStatus::Approved ? '2026-06-09 12:00:00' : null,
            'rejected_by_user_id' => $status === PurchaseOrderStatus::Rejected ? $finance->id : null,
            'rejected_at' => $status === PurchaseOrderStatus::Rejected ? '2026-06-09 12:30:00' : null,
            'rejected_reason' => $status === PurchaseOrderStatus::Rejected ? 'Tax coding does not match the approved quotation.' : null,
            'changes_requested_by_user_id' => $status === PurchaseOrderStatus::ChangesRequested ? $finance->id : null,
            'changes_requested_at' => $status === PurchaseOrderStatus::ChangesRequested ? '2026-06-09 10:00:00' : null,
            'changes_requested_reason' => $status === PurchaseOrderStatus::ChangesRequested ? 'Payment terms and tax amount require correction.' : null,
            'changes_requested_fields' => $status === PurchaseOrderStatus::ChangesRequested ? ['taxAmount', 'paymentTerms'] : [],
        ])->save();

        $context->approvalTasks->put("purchase-order-{$purchaseOrder->statusState()->value}", $task->refresh());

        return $purchaseOrder->refresh();
    }

    private function recommendation(
        Tenant $tenant,
        Rfq $rfq,
        User $buyer,
        RfqAwardRecommendationStatus $status,
        ?Quotation $quotation = null,
        ?QuotationVersion $version = null,
        ?RfqScorecard $scorecard = null,
        ?string $decisionReason = null,
    ): RfqAwardRecommendation {
        return RfqAwardRecommendation::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'rfq_id' => $rfq->id, 'status' => $status],
            [
                'recommended_vendor_id' => $quotation?->vendor_id,
                'recommended_quotation_id' => $quotation?->id,
                'recommended_quotation_version_id' => $version?->id,
                'scorecard_id' => $scorecard?->id,
                'rationale' => 'Greenline provides the best sustainability evidence while meeting delivery requirements.',
                'tradeoff_summary' => 'Higher total cost accepted for stronger ESG compliance.',
                'risk_summary' => 'Primary risk is delivery coordination during the office expansion.',
                'exception_summary' => $status === RfqAwardRecommendationStatus::Draft ? null : 'Atlas is cheaper but lacks ESG evidence.',
                'created_by_user_id' => $buyer->id,
                'updated_by_user_id' => $buyer->id,
                'submitted_by_user_id' => in_array($status, [RfqAwardRecommendationStatus::PendingApproval, RfqAwardRecommendationStatus::ApprovalRouted, RfqAwardRecommendationStatus::Approved], true) ? $buyer->id : null,
                'submitted_at' => in_array($status, [RfqAwardRecommendationStatus::PendingApproval, RfqAwardRecommendationStatus::ApprovalRouted, RfqAwardRecommendationStatus::Approved], true) ? '2026-05-24 17:00:00' : null,
                'decision_reason' => $decisionReason,
            ],
        );
    }

    private function recommendationVariant(
        Tenant $tenant,
        Rfq $rfq,
        User $buyer,
        RfqAwardRecommendationStatus $status,
        string $variant,
        Quotation $quotation,
        QuotationVersion $version,
        RfqScorecard $scorecard,
    ): RfqAwardRecommendation {
        return RfqAwardRecommendation::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'rfq_id' => $rfq->id,
                'status' => $status,
                'rationale' => "Seeded {$variant} recommendation for purchase order review demos.",
            ],
            [
                'recommended_vendor_id' => $quotation->vendor_id,
                'recommended_quotation_id' => $quotation->id,
                'recommended_quotation_version_id' => $version->id,
                'scorecard_id' => $scorecard->id,
                'tradeoff_summary' => 'Higher total cost accepted for stronger ESG compliance.',
                'risk_summary' => 'Primary risk is delivery coordination during the office expansion.',
                'exception_summary' => 'Atlas is cheaper but lacks ESG evidence.',
                'created_by_user_id' => $buyer->id,
                'updated_by_user_id' => $buyer->id,
                'submitted_by_user_id' => $buyer->id,
                'submitted_at' => '2026-05-24 17:00:00',
                'decision_reason' => 'Approved recommendation for seeded purchase order review state.',
            ],
        );
    }

    private function approvalPolicy(Tenant $tenant, User $actor, string $subjectType, string $name): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $name],
            [
                'description' => "Seeded policy for {$subjectType} approvals.",
                'subject_type' => $subjectType,
                'status' => ApprovalPolicyStatus::Active,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        return ApprovalPolicyVersion::query()->updateOrCreate(
            ['approval_policy_id' => $policy->id, 'version_number' => 1],
            [
                'tenant_id' => $tenant->id,
                'subject_type' => $subjectType,
                'status' => ApprovalPolicyVersionStatus::Published,
                'priority' => 100,
                'rules' => [['field' => 'amount', 'operator' => 'gte', 'value' => 1000]],
                'route_template' => [
                    'stages' => [
                        ['name' => 'Manager review', 'completionRule' => 'all'],
                    ],
                ],
                'sla_rules' => [['stage' => 'Manager review', 'dueInHours' => 48]],
                'published_by' => $actor->id,
                'published_at' => self::SEEDED_AT,
            ],
        );
    }

    private function seedApprovalRoute(
        Tenant $tenant,
        object $subject,
        ApprovalPolicyVersion $policyVersion,
        User $assignee,
        string $title,
        ApprovalInstanceStatus $instanceStatus,
        ApprovalTaskStatus $taskStatus,
        string $startedAt,
        string $dueAt,
        ?string $decidedAt = null,
        ?string $decision = null,
        ?string $decisionReason = null,
    ): ApprovalTask {
        $instance = ApprovalInstance::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'subject_type' => $subject::class, 'subject_id' => $subject->id],
            [
                'approval_policy_version_id' => $policyVersion->id,
                'status' => $instanceStatus,
                'current_stage_sequence' => 1,
                'matched_context' => ['demo' => true],
                'matched_explanation' => ['policy' => $policyVersion->policy?->name],
                'started_at' => $startedAt,
                'completed_at' => $instanceStatus === ApprovalInstanceStatus::Active ? null : $decidedAt,
            ],
        );

        $stage = ApprovalStage::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'approval_instance_id' => $instance->id, 'sequence' => 1],
            [
                'name' => 'Manager review',
                'completion_rule' => 'all',
                'status' => $taskStatus === ApprovalTaskStatus::Active ? ApprovalStageStatus::Active : ApprovalStageStatus::Completed,
                'activated_at' => $startedAt,
                'completed_at' => $taskStatus === ApprovalTaskStatus::Active ? null : $decidedAt,
                'due_at' => $dueAt,
            ],
        );

        return ApprovalTask::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'approval_instance_id' => $instance->id, 'approval_stage_id' => $stage->id],
            [
                'subject_type' => $subject::class,
                'subject_id' => $subject->id,
                'assignee_id' => $assignee->id,
                'original_assignee_id' => $assignee->id,
                'title' => $title,
                'status' => $taskStatus,
                'decision' => $decision,
                'decision_reason' => $decisionReason,
                'requested_fields' => $taskStatus === ApprovalTaskStatus::ChangesRequested ? ['requirements'] : [],
                'decided_by_id' => $decidedAt !== null ? $assignee->id : null,
                'assigned_at' => $startedAt,
                'due_at' => $dueAt,
                'decided_at' => $decidedAt,
                'lock_version' => 0,
                'metadata' => ['demo' => true],
            ],
        );
    }
}
