<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Domains\Approval\Models\ApprovalTask;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProcurementCalendarApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-01 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_buyer_can_list_procurement_calendar_events_for_visible_sources(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester, [
            'needed_by_date' => '2026-06-24',
        ]);
        $rfq = $this->createDraftRfq($tenant, $requisition, $buyer, ['response_due_at' => '2026-06-18 17:00:00']);
        $vendor = $this->createVendor($tenant, 'Northwind Traders');
        $invitation = $this->createRfqInvitation($tenant, $rfq, $vendor, ['response_due_at' => '2026-06-19 12:00:00']);
        $quotation = $this->createQuotation($tenant, $rfq, $invitation, $vendor, $buyer, ['valid_until' => '2026-06-25']);
        $version = $this->createQuotationVersion($tenant, $quotation, $buyer, ['valid_until' => '2026-06-25']);
        $recommendation = $this->createAwardRecommendation($tenant, $rfq, $quotation, $version, $buyer);
        $this->createApprovalTask($tenant, $requisition, $approver, [
            'due_at' => '2026-06-20 09:00:00',
        ]);
        $this->createPurchaseOrderRequestHandoff($tenant, $recommendation, $rfq, $quotation, $version, $buyer, [
            'requested_po_date' => '2026-06-21',
        ]);
        $expectedScheduledEvents = 5;

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.range.from', '2026-06-01')
            ->assertJsonPath('data.range.to', '2026-06-30')
            ->assertJsonPath('data.summary.byStatus.scheduled', $expectedScheduledEvents)
            ->assertJsonPath('data.summary.byStatus.informational', 1)
            ->assertJsonPath('data.summary.bySourceType.rfqDeadline', 2)
            ->assertJsonPath('data.summary.bySourceType.approvalDue', 1)
            ->assertJsonPath('data.summary.bySourceType.requisitionNeededBy', 1)
            ->assertJsonPath('data.summary.bySourceType.poHandoff', 1)
            ->assertJsonPath('data.summary.bySourceType.quotationValidity', 1)
            ->assertJsonPath('data.availableSources', fn (array $sources): bool => collect($sources)->contains(
                fn (array $source): bool => $source['sourceType'] === 'vendorDocumentExpiry' && $source['available'] === false,
            ))
            ->assertJsonPath('data.availableSources', fn (array $sources): bool => collect($sources)->contains(
                fn (array $source): bool => $source['sourceType'] === 'contractRenewal' && $source['available'] === false,
            ))
            ->assertJsonFragment(['title' => 'Warehouse replenishment'])
            ->assertJsonFragment(['href' => "/requisitions/{$requisition->id}"])
            ->assertJsonFragment(['sourceType' => 'requisitionNeededBy'])
            ->assertJsonFragment(['sourceType' => 'rfqDeadline'])
            ->assertJsonFragment(['sourceType' => 'quotationValidity'])
            ->assertJsonFragment(['sourceType' => 'approvalDue'])
            ->assertJsonFragment(['sourceType' => 'poHandoff']);
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
            ->getJson('/api/procurement-calendar/events?sourceTypes=approvalDue&statuses=scheduled&from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.sourceType', 'approvalDue')
            ->assertJsonPath('data.events.0.sourceId', (string) $assignedTask->id)
            ->assertJsonPath('data.events.0.title', 'Requisition approval')
            ->assertJsonPath('data.events.0.status', 'scheduled')
            ->assertJsonPath('data.events.0.record.href', "/approvals/tasks/{$assignedTask->id}")
            ->assertJsonMissing(['sourceId' => (string) $otherTask->id]);

        $this->assertSame($approver->id, $assignedTask->refresh()->assignee_id);
        $this->assertSame($otherApprover->id, $otherTask->refresh()->assignee_id);
    }

    public function test_calendar_filters_by_source_status_and_search(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester, ['title' => 'Warehouse forklift refresh']);
        $rfq = $this->createDraftRfq($tenant, $requisition, $buyer, [
            'number' => 'RFQ-2026-SEARCH',
            'title' => 'Searchable RFQ deadline',
            'response_due_at' => '2026-06-18 11:00:00',
        ]);
        $vendor = $this->createVendor($tenant, 'Searchable Supplies');
        $invitation = $this->createRfqInvitation($tenant, $rfq, $vendor, ['response_due_at' => '2026-06-19 11:00:00']);
        $quotation = $this->createQuotation($tenant, $rfq, $invitation, $vendor, $buyer, ['number' => 'Q-SEARCH-1', 'valid_until' => '2026-06-24']);
        $version = $this->createQuotationVersion($tenant, $quotation, $buyer, ['valid_until' => '2026-06-24']);
        $recommendation = $this->createAwardRecommendation($tenant, $rfq, $quotation, $version, $buyer);
        $this->createApprovalTask($tenant, $requisition, $approver, ['status' => 'active', 'due_at' => '2026-06-21 09:00:00']);
        $this->createPurchaseOrderRequestHandoff($tenant, $recommendation, $rfq, $quotation, $version, $buyer, [
            'requested_po_date' => '2026-06-23',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?sourceTypes[]=rfqDeadline&statuses[]=scheduled&q=Searchable&from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.sourceType', 'rfqDeadline')
            ->assertJsonPath('data.events.0.sourceId', (string) $rfq->id)
            ->assertJsonPath('data.events.0.title', 'Searchable RFQ deadline')
            ->assertJsonPath('data.events.0.record.href', "/sourcing/rfqs/{$rfq->id}")
            ->assertJsonPath('data.events.0.status', 'scheduled');
    }

    public function test_invalid_or_overwide_ranges_return_validation_errors(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?from=not-a-date&to=2026-06-01')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?from=2026-06-30&to=2026-06-01')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?from=2026-01-01&to=2026-12-31')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_cross_tenant_records_do_not_appear(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        [, $otherRequester] = $this->tenantUser('requester', $otherTenant);
        [, $requester] = $this->tenantUser('requester', $tenant);

        $requisition = $this->createSubmittedRequisition($tenant, $requester, [
            'title' => 'Same tenant calendar item',
            'needed_by_date' => '2026-06-19',
        ]);
        $rfq = $this->createDraftRfq($tenant, $requisition, $buyer, [
            'title' => 'Same tenant visible rfq',
            'response_due_at' => '2026-06-20 10:00:00',
        ]);
        $vendor = $this->createVendor($tenant, 'Same Tenant Vendor');
        $invitation = $this->createRfqInvitation($tenant, $rfq, $vendor, ['response_due_at' => '2026-06-21 10:00:00']);
        $quotation = $this->createQuotation($tenant, $rfq, $invitation, $vendor, $buyer, [
            'number' => 'Q-SAME-TENANT',
            'valid_until' => '2026-06-26',
        ]);
        $version = $this->createQuotationVersion($tenant, $quotation, $buyer, [
            'valid_until' => '2026-06-26',
        ]);
        $recommendation = $this->createAwardRecommendation($tenant, $rfq, $quotation, $version, $buyer);
        $this->createApprovalTask($tenant, $requisition, $buyer, [
            'title' => 'Same tenant approval',
            'due_at' => '2026-06-22 09:00:00',
        ]);
        $this->createPurchaseOrderRequestHandoff($tenant, $recommendation, $rfq, $quotation, $version, $buyer, [
            'number' => 'POH-SAME-TENANT',
            'requested_po_date' => '2026-06-23',
        ]);

        $otherRequisition = $this->createSubmittedRequisition($otherTenant, $otherRequester, [
            'title' => 'Other tenant exclusive requisition',
            'needed_by_date' => '2026-06-19',
        ]);
        $otherRfq = $this->createDraftRfq($otherTenant, $otherRequisition, $otherBuyer, [
            'number' => 'RFQ-OTHER',
            'title' => 'Other tenant exclusive rfq',
            'response_due_at' => '2026-06-20 10:00:00',
        ]);
        $otherVendor = $this->createVendor($otherTenant, 'Other Tenant Vendor');
        $otherInvitation = $this->createRfqInvitation($otherTenant, $otherRfq, $otherVendor, ['response_due_at' => '2026-06-22 10:00:00']);
        $otherQuotation = $this->createQuotation($otherTenant, $otherRfq, $otherInvitation, $otherVendor, $otherBuyer, [
            'number' => 'Q-OTHER-EXCLUSIVE',
            'valid_until' => '2026-06-27',
        ]);
        $otherVersion = $this->createQuotationVersion($otherTenant, $otherQuotation, $otherBuyer, [
            'valid_until' => '2026-06-27',
        ]);
        $otherRecommendation = $this->createAwardRecommendation($otherTenant, $otherRfq, $otherQuotation, $otherVersion, $otherBuyer);
        $this->createApprovalTask($otherTenant, $otherRequisition, $otherBuyer, ['title' => 'Other tenant approval']);
        $this->createPurchaseOrderRequestHandoff($otherTenant, $otherRecommendation, $otherRfq, $otherQuotation, $otherVersion, $otherBuyer, [
            'number' => 'POH-OTHER-EXCLUSIVE',
            'requested_po_date' => '2026-06-24',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Same tenant calendar item'])
            ->assertJsonFragment(['title' => 'Same tenant visible rfq'])
            ->assertJsonMissing(['title' => 'Other tenant exclusive rfq'])
            ->assertJsonMissing(['title' => 'POH-OTHER-EXCLUSIVE']);
    }

    public function test_unavailable_future_sources_are_returned_as_metadata_not_fake_events(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $requester] = $this->tenantUser('requester', $tenant);
        $requisition = $this->createSubmittedRequisition($tenant, $requester, [
            'title' => 'Future source range anchor',
        ]);
        $this->createDraftRfq($tenant, $requisition, $buyer, [
            'title' => 'Future source range rfq',
            'response_due_at' => '2026-08-03 09:00:00',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/procurement-calendar/events?sourceTypes[]=vendorDocumentExpiry&from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonCount(0, 'data.events')
            ->assertJsonPath('data.availableSources', fn (array $sources): bool => collect($sources)->contains(
                fn (array $source): bool => $source['sourceType'] === 'vendorDocumentExpiry' && $source['available'] === false,
            ))
            ->assertJsonPath('data.availableSources', fn (array $sources): bool => collect($sources)->contains(
                fn (array $source): bool => $source['sourceType'] === 'contractRenewal' && $source['available'] === false,
            ))
            ->assertJsonPath('data.events', [])
            ->assertJsonMissing(['title' => 'Vendor document expiry event']);
    }

    public function test_calendar_requires_authentication(): void
    {
        $this->withHeader('X-Tenant-Id', '1')
            ->getJson('/api/procurement-calendar/events?from=2026-06-01&to=2026-06-30')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_calendar_stateful_request_without_session_is_unauthenticated(): void
    {
        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', '1')
            ->getJson('/api/procurement-calendar/events?from=2026-06-01&to=2026-06-30')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_calendar_route_supports_real_session_login_logout(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');

        $buyer->forceFill([
            'email' => 'procurement-calendar-session@example.com',
            'password' => Hash::make('secret123'),
        ])->save();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'procurement-calendar-session@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/procurement-calendar/events?from=2026-06-01&to=2026-06-30')
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/procurement-calendar/events?from=2026-06-01&to=2026-06-30')
            ->assertUnauthorized();
    }

    public function test_same_day_all_day_sources_are_not_overdue_until_after_the_day(): void
    {
        $today = CarbonImmutable::today()->toDateString();
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $requester] = $this->tenantUser('requester', $tenant);
        $requisition = $this->createSubmittedRequisition($tenant, $requester, [
            'title' => 'Same day needed by',
            'needed_by_date' => $today,
        ]);
        $rfq = $this->createDraftRfq($tenant, $requisition, $buyer);
        $vendor = $this->createVendor($tenant, 'Same Day Vendor');
        $invitation = $this->createRfqInvitation($tenant, $rfq, $vendor);
        $quotation = $this->createQuotation($tenant, $rfq, $invitation, $vendor, $buyer);
        $version = $this->createQuotationVersion($tenant, $quotation, $buyer);
        $recommendation = $this->createAwardRecommendation($tenant, $rfq, $quotation, $version, $buyer);
        $this->createPurchaseOrderRequestHandoff($tenant, $recommendation, $rfq, $quotation, $version, $buyer, [
            'number' => 'POH-SAME-DAY',
            'requested_po_date' => $today,
        ]);
        $tomorrow = CarbonImmutable::today()->addDay()->toDateString();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/procurement-calendar/events?sourceTypes[]=requisitionNeededBy&sourceTypes[]=poHandoff&from={$today}&to={$tomorrow}")
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.byStatus.overdue', 0);
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
            'number' => 'REQ-2026-'.Str::padLeft((string) (Requisition::query()->where('tenant_id', $tenant->id)->count() + 1), 6, '0'),
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
            'number' => 'RFQ-2026-'.Str::padLeft((string) (Rfq::query()->where('tenant_id', $tenant->id)->count() + 1), 6, '0'),
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

    private function createRfqInvitation(Tenant $tenant, Rfq $rfq, Vendor $vendor, array $attributes = []): RfqInvitation
    {
        return RfqInvitation::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => Str::slug($vendor->name).'@example.com',
        ], $attributes));
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
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '131100.00',
            'valid_until' => '2026-06-25',
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

    private function createQuotationVersion(Tenant $tenant, Quotation $quotation, User $buyer, array $attributes = []): QuotationVersion
    {
        $version = QuotationVersion::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '131100.00',
            'valid_until' => '2026-06-25',
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ], $attributes));

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
            'requested_po_date' => '2026-06-21',
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
                'vendor' => ['name' => $quotation->vendor?->name],
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
