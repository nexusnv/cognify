<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProcurementCalendarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_list_procurement_calendar_events_for_visible_sources(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $rfq = $this->createDraftRfq($tenant, $requisition, $buyer);
        $vendor = $this->createVendor($tenant, 'Northwind Traders');
        $invitation = $this->createRfqInvitation($tenant, $rfq, $vendor);
        $quotation = $this->createQuotation($tenant, $rfq, $invitation, $vendor, $buyer);
        $version = $this->createQuotationVersion($tenant, $quotation, $buyer);
        $recommendation = $this->createAwardRecommendation($tenant, $rfq, $quotation, $version, $buyer);
        $task = $this->createApprovalTask($tenant, $requisition, $approver);
        $handoff = $this->createPurchaseOrderRequestHandoff($tenant, $recommendation, $rfq, $quotation, $version, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?start=2026-06-01&end=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.events.0.source.type', 'requisition')
            ->assertJsonPath('data.events.1.source.type', 'rfq')
            ->assertJsonPath('data.events.2.source.type', 'approval')
            ->assertJsonPath('data.events.3.source.type', 'po_handoff');

        $this->assertDatabaseHas('requisitions', ['id' => $requisition->id]);
        $this->assertDatabaseHas('rfqs', ['id' => $rfq->id]);
        $this->assertDatabaseHas('rfq_invitations', ['id' => $invitation->id]);
        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
        $this->assertDatabaseHas('quotation_versions', ['id' => $version->id]);
        $this->assertDatabaseHas('approval_tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('purchase_order_request_handoffs', ['id' => $handoff->id]);
    }

    public function test_approver_sees_only_assigned_approval_due_events(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $otherApprover] = $this->tenantUser('approver', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester);
        $assignedTask = $this->createApprovalTask($tenant, $requisition, $approver, ['due_at' => '2026-06-12 09:00:00']);
        $otherTask = $this->createApprovalTask($tenant, $requisition, $otherApprover, ['due_at' => '2026-06-13 09:00:00']);

        $this->actingAsTenant($tenant, $approver)
            ->getJson('/api/procurement-calendar/events?source=approval&status=due&start=2026-06-01&end=2026-06-30')
            ->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.assignee.id', (string) $approver->id)
            ->assertJsonPath('data.events.0.status', 'due');

        $this->assertSame($approver->id, $assignedTask->refresh()->assignee_id);
        $this->assertSame($otherApprover->id, $otherTask->refresh()->assignee_id);
    }

    public function test_calendar_filters_by_source_status_and_search(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester, ['title' => 'Warehouse forklift refresh']);
        $rfq = $this->createDraftRfq($tenant, $requisition, $buyer, ['number' => 'RFQ-2026-SEARCH']);
        $vendor = $this->createVendor($tenant, 'Searchable Supplies');
        $invitation = $this->createRfqInvitation($tenant, $rfq, $vendor);
        $quotation = $this->createQuotation($tenant, $rfq, $invitation, $vendor, $buyer, ['number' => 'Q-SEARCH-1']);
        $version = $this->createQuotationVersion($tenant, $quotation, $buyer);
        $recommendation = $this->createAwardRecommendation($tenant, $rfq, $quotation, $version, $buyer);
        $this->createApprovalTask($tenant, $requisition, $approver, ['status' => 'active']);
        $this->createPurchaseOrderRequestHandoff($tenant, $recommendation, $rfq, $quotation, $version, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?source=rfq&status=active&search=SEARCH&start=2026-06-01&end=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.filters.source', 'rfq')
            ->assertJsonPath('data.filters.status', 'active')
            ->assertJsonPath('data.filters.search', 'SEARCH')
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.source.id', (string) $rfq->id);
    }

    public function test_invalid_or_overwide_ranges_return_validation_errors(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?start=2026-06-30&end=2026-06-01')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?start=2026-01-01&end=2026-12-31')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.fields.range', 'The selected date range is too wide.');
    }

    public function test_cross_tenant_records_do_not_appear(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        [, $otherRequester] = $this->tenantUser('requester', $otherTenant);

        $otherRequisition = $this->createSubmittedRequisition($otherTenant, $otherRequester);
        $otherRfq = $this->createDraftRfq($otherTenant, $otherRequisition, $otherBuyer, ['number' => 'RFQ-OTHER']);
        $otherVendor = $this->createVendor($otherTenant, 'Other Tenant Vendor');
        $otherInvitation = $this->createRfqInvitation($otherTenant, $otherRfq, $otherVendor);
        $otherQuotation = $this->createQuotation($otherTenant, $otherRfq, $otherInvitation, $otherVendor, $otherBuyer);
        $otherVersion = $this->createQuotationVersion($otherTenant, $otherQuotation, $otherBuyer);
        $otherRecommendation = $this->createAwardRecommendation($otherTenant, $otherRfq, $otherQuotation, $otherVersion, $otherBuyer);
        $this->createApprovalTask($otherTenant, $otherRequisition, $otherBuyer);
        $this->createPurchaseOrderRequestHandoff($otherTenant, $otherRecommendation, $otherRfq, $otherQuotation, $otherVersion, $otherBuyer);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?start=2026-06-01&end=2026-06-30')
            ->assertOk()
            ->assertJsonMissingPath('data.events.0.source.tenantId')
            ->assertJsonMissingPath('data.events.0.id');
    }

    public function test_unavailable_future_sources_are_returned_as_metadata_not_fake_events(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?start=2026-06-01&end=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.metadata.unavailableFutureSources.0.key', 'manual_reminders')
            ->assertJsonPath('data.metadata.unavailableFutureSources.0.available', false)
            ->assertJsonMissingPath('data.events.0.source.type');
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

    private function createSubmittedRequisition(Tenant $tenant, User $requester, array $attributes = []): Requisition
    {
        return Requisition::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-' . Str::padLeft((string) (Requisition::query()->where('tenant_id', $tenant->id)->count() + 1), 6, '0'),
            'title' => 'Warehouse replenishment',
            'business_justification' => 'Maintain stock and replacement cadence.',
            'needed_by_date' => '2026-07-15',
            'department' => 'Operations',
            'cost_center' => 'OPS-220',
            'delivery_location' => 'Shah Alam warehouse',
            'currency' => 'MYR',
            'status' => RequisitionStatus::Submitted,
            'lock_version' => 0,
            'submitted_at' => now(),
        ], $attributes));
    }

    private function createDraftRfq(Tenant $tenant, Requisition $requisition, User $buyer, array $attributes = []): Rfq
    {
        return Rfq::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'project_id' => null,
            'sourcing_intake_review_id' => null,
            'number' => 'RFQ-2026-' . Str::padLeft((string) (Rfq::query()->where('tenant_id', $tenant->id)->count() + 1), 6, '0'),
            'title' => 'Warehouse replenishment RFQ',
            'status' => 'draft',
            'owner_id' => $buyer->id,
        ], $attributes));
    }

    private function createVendor(Tenant $tenant, string $name): Vendor
    {
        return Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createRfqInvitation(Tenant $tenant, Rfq $rfq, Vendor $vendor): RfqInvitation
    {
        return RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => Str::slug($vendor->name) . '@example.com',
        ]);
    }

    private function createQuotation(
        Tenant $tenant,
        Rfq $rfq,
        RfqInvitation $invitation,
        Vendor $vendor,
        User $buyer,
        array $attributes = [],
    ): Quotation {
        return Quotation::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-' . Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '131100.00',
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'manual_entry_complete' => true,
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ], $attributes));
    }

    private function createQuotationVersion(Tenant $tenant, Quotation $quotation, User $buyer): QuotationVersion
    {
        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '131100.00',
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);

        $quotation->forceFill(['current_version_id' => $version->id])->save();

        return $version;
    }

    private function createApprovalTask(
        Tenant $tenant,
        Requisition $requisition,
        User $assignee,
        array $attributes = [],
    ): ApprovalTask {
        return ApprovalTask::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'approval_instance_id' => null,
            'stage_id' => null,
            'subject_type' => Requisition::class,
            'subject_id' => $requisition->id,
            'assignee_id' => $assignee->id,
            'status' => 'active',
            'title' => 'Requisition approval',
            'due_at' => now()->addDays(7),
            'lock_version' => 0,
        ], $attributes));
    }

    private function createPurchaseOrderRequestHandoff(
        Tenant $tenant,
        RfqAwardRecommendation $recommendation,
        Rfq $rfq,
        Quotation $quotation,
        QuotationVersion $version,
        User $buyer,
        array $attributes = [],
    ): PurchaseOrderRequestHandoff {
        return PurchaseOrderRequestHandoff::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'approval_instance_id' => null,
            'rfq_id' => $rfq->id,
            'requisition_id' => null,
            'project_id' => null,
            'vendor_id' => $quotation->vendor_id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'POH-2026-000001',
            'status' => 'draft',
            'currency' => 'MYR',
            'subtotal_amount' => '131100.00',
            'tax_amount' => null,
            'freight_amount' => null,
            'discount_amount' => null,
            'total_amount' => '131100.00',
            'requested_po_date' => null,
            'delivery_attention' => null,
            'finance_note' => null,
            'export_memo' => null,
            'requested_by_user_id' => $buyer->id,
            'ready_by_user_id' => null,
            'ready_at' => null,
            'cancelled_by_user_id' => null,
            'cancelled_at' => null,
            'cancelled_reason' => null,
            'last_exported_by_user_id' => null,
            'last_exported_at' => null,
            'last_export_format' => null,
            'source_snapshot' => [
                'rfq' => ['number' => $rfq->number],
                'vendor' => ['name' => 'Northwind Traders'],
                'quotation' => ['number' => $quotation->number],
            ],
            'line_snapshot' => [
                [
                    'lineNumber' => 1,
                    'description' => 'Pallet rack bay',
                    'quantity' => '10.0000',
                    'unitOfMeasure' => 'set',
                    'unitPrice' => '13110.0000',
                    'lineTotal' => '131100.00',
                    'currency' => 'MYR',
                ],
            ],
            'approval_snapshot' => [
                'finalDecision' => 'approved',
                'approvalInstanceId' => null,
                'stages' => [
                    ['stage' => 'Commercial approval', 'actor' => $buyer->name],
                ],
            ],
            'evidence_snapshot' => [
                ['type' => 'comparison_note', 'summary' => 'Pallet rack bay selected for commercial and delivery fit.'],
            ],
            'readiness_warnings' => [],
            'lock_version' => 1,
        ], $attributes));
    }

    private function createAwardRecommendation(
        Tenant $tenant,
        Rfq $rfq,
        Quotation $quotation,
        QuotationVersion $version,
        User $buyer,
    ): RfqAwardRecommendation {
        return RfqAwardRecommendation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'recommended_vendor_id' => $quotation->vendor_id,
            'recommended_quotation_id' => $quotation->id,
            'recommended_quotation_version_id' => $version->id,
            'scorecard_id' => null,
            'status' => RfqAwardRecommendationStatus::Draft->value,
            'rationale' => 'Calendar visibility fixture.',
            'tradeoff_summary' => 'Calendar visibility fixture.',
            'risk_summary' => 'Calendar visibility fixture.',
            'exception_summary' => null,
            'withdrawal_reason' => null,
            'created_by_user_id' => $buyer->id,
            'updated_by_user_id' => $buyer->id,
            'submitted_by_user_id' => null,
            'submitted_at' => null,
            'withdrawn_by_user_id' => null,
            'withdrawn_at' => null,
        ]);
    }
}
