<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Domains\Quotation\States\QuotationNormalizationPricingMode;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationComparisonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_view_rfq_comparison_from_approved_normalizations(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer, 'Acme Supply', 'USD', '12500.00');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.rfq.id', (string) $rfq->id)
            ->assertJsonPath('data.readiness.responseCount', 1)
            ->assertJsonPath('data.readiness.approvedNormalizationCount', 1)
            ->assertJsonPath('data.readiness.pendingNormalizationCount', 0)
            ->assertJsonPath('data.readiness.missingResponseCount', 0)
            ->assertJsonPath('data.readiness.mixedCurrency', false)
            ->assertJsonPath('data.vendors.0.vendorName', 'Acme Supply')
            ->assertJsonPath('data.vendors.0.totalAmount', '12500.00')
            ->assertJsonPath('data.commercialTerms.0.id', 'subtotalAmount')
            ->assertJsonPath('data.commercialTerms.1.id', 'taxAmount')
            ->assertJsonPath('data.commercialTerms.2.id', 'freightAmount')
            ->assertJsonPath('data.commercialTerms.3.id', 'discountAmount')
            ->assertJsonPath('data.commercialTerms.4.id', 'totalAmount')
            ->assertJsonPath('data.commercialTerms.5.id', 'validUntil')
            ->assertJsonPath('data.commercialTerms.6.id', 'leadTimeDays')
            ->assertJsonPath('data.commercialTerms.7.id', 'paymentTerms')
            ->assertJsonPath('data.commercialTerms.8.id', 'deliveryTerms')
            ->assertJsonPath('data.commercialTerms.9.id', 'warrantyTerms')
            ->assertJsonPath('data.commercialTerms.10.id', 'exclusions')
            ->assertJsonPath('data.commercialTerms.11.id', 'complianceNotes')
            ->assertJsonPath('data.permissions.canViewComparison', true)
            ->assertJsonPath('data.permissions.canManageQuotationComparisonNotes', true);
    }

    public function test_requester_approver_and_cross_tenant_buyers_cannot_view_comparison(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertForbidden();

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertNotFound();
    }

    public function test_comparison_marks_missing_approved_normalization_without_falling_back_to_raw_totals(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithQuotation($tenant, $buyer, 'Raw Vendor', 'USD', '9999.99', false);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.readiness.responseCount', 1)
            ->assertJsonPath('data.readiness.approvedNormalizationCount', 0)
            ->assertJsonPath('data.vendors.0.readiness', 'normalization_required')
            ->assertJsonPath('data.vendors.0.totalAmount', null)
            ->assertJsonPath('data.commercialTerms.4.vendorValues.0.value', null);
    }

    public function test_comparison_flags_mixed_currencies_and_preserves_bundle_pricing(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer, 'Acme Supply', 'USD', '12500.00', 'bundle');
        $this->addApprovedQuotation($tenant, $buyer, $rfq, 'Beta Trading', 'MYR', '57000.00', 'per_line');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.readiness.mixedCurrency', true)
            ->assertJsonPath('data.lineRows.0.vendorCells.0.pricingMode', 'bundle')
            ->assertJsonPath('data.lineRows.0.vendorCells.0.bundleTotalAmount', '12500.00');
    }

    public function test_buyer_can_create_update_and_soft_delete_comparison_notes_with_audit_events(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $create = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'overall',
                'note' => 'Acme is cheaper but delivery is longer.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.section', 'overall')
            ->assertJsonPath('data.note', 'Acme is cheaper but delivery is longer.');

        $noteId = $create->json('data.id');

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/comparison/notes/{$noteId}", [
                'section' => 'delivery',
                'note' => 'Delivery risk needs buyer follow-up.',
            ])
            ->assertOk()
            ->assertJsonPath('data.section', 'delivery')
            ->assertJsonPath('data.note', 'Delivery risk needs buyer follow-up.');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.noteGroups.0.section', 'delivery')
            ->assertJsonPath('data.noteGroups.0.notes.0.note', 'Delivery risk needs buyer follow-up.');

        $this->actingAsTenant($tenant, $buyer)
            ->deleteJson("/api/rfqs/{$rfq->id}/comparison/notes/{$noteId}")
            ->assertNoContent();

        $this->assertSoftDeleted('quotation_comparison_notes', [
            'id' => $noteId,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation_comparison.note_created',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation_comparison.note_updated',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation_comparison.note_deleted',
        ]);
    }

    public function test_note_targets_must_belong_to_same_rfq_and_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $otherRfq = $this->rfqWithApprovedQuotation($tenant, $buyer, 'Other Vendor');
        [$foreignTenant, $foreignBuyer] = $this->tenantUser('buyer');
        $foreignRfq = $this->rfqWithApprovedQuotation($foreignTenant, $foreignBuyer, 'Foreign Vendor');
        $otherQuotation = Quotation::query()->where('rfq_id', $otherRfq->id)->firstOrFail();
        $foreignQuotation = Quotation::query()->where('rfq_id', $foreignRfq->id)->firstOrFail();
        $rfqQuotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $this->addApprovedQuotation($tenant, $buyer, $rfq, 'Second Same RFQ Vendor', 'USD', '14000.00', 'per_line');
        $sameRfqOtherQuotation = Quotation::query()
            ->where('rfq_id', $rfq->id)
            ->whereKeyNot($rfqQuotation->id)
            ->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'price',
                'quotationId' => (string) $otherQuotation->id,
                'note' => 'Cross-RFQ target should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.quotationId.0', 'The selected quotation must belong to this RFQ.');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'price',
                'quotationId' => (string) $foreignQuotation->id,
                'note' => 'Cross-tenant target should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.quotationId.0', 'The selected quotation must belong to this RFQ.');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'delivery',
                'vendorId' => (string) $otherQuotation->vendor_id,
                'note' => 'Cross-RFQ vendor target should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.vendorId.0', 'The selected vendor must belong to this RFQ.');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'delivery',
                'vendorId' => (string) $foreignQuotation->vendor_id,
                'note' => 'Cross-tenant vendor target should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.vendorId.0', 'The selected vendor must belong to this RFQ.');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'terms',
                'rfqLineItemId' => 'not-on-this-rfq',
                'note' => 'Cross-RFQ line target should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.rfqLineItemId.0', 'The selected RFQ line item must belong to this RFQ.');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'overall',
                'quotationId' => (string) $rfqQuotation->id,
                'vendorId' => (string) $sameRfqOtherQuotation->vendor_id,
                'note' => 'Mismatched quotation and vendor should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.vendorId.0', 'The selected vendor must match the selected quotation.');
    }

    public function test_note_actions_do_not_change_rfq_quotation_or_normalization_state(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $normalization = QuotationNormalization::query()->where('quotation_id', $quotation->id)->firstOrFail();

        $originalRfqStatus = $rfq->status;
        $originalQuotationStatus = $quotation->status;
        $originalNormalizationStatus = $normalization->status;

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'overall',
                'note' => 'Non-decision annotation.',
            ])
            ->assertCreated();

        $this->assertSame($originalRfqStatus, $rfq->refresh()->status);
        $this->assertSame($originalQuotationStatus, $quotation->refresh()->status);
        $this->assertSame($originalNormalizationStatus->value, $normalization->refresh()->status->value);
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
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }

    private function rfqWithApprovedQuotation(
        Tenant $tenant,
        User $buyer,
        string $vendorName = 'Acme Supply',
        string $currency = 'USD',
        string $total = '12500.00',
        string $pricingMode = 'per_line',
    ): Rfq {
        return $this->rfqWithQuotation($tenant, $buyer, $vendorName, $currency, $total, true, $pricingMode);
    }

    private function rfqWithQuotation(
        Tenant $tenant,
        User $buyer,
        string $vendorName,
        string $currency,
        string $total,
        bool $approved,
        string $pricingMode = 'per_line',
    ): Rfq {
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::upper(Str::random(6)),
            'title' => 'Laptop refresh',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Purchase laptops',
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Laptop',
                'description' => 'Business laptop',
                'quantity' => '10',
                'unit_of_measure' => 'each',
                'currency' => $currency,
            ]],
        ]);

        $this->createQuotationForRfq($tenant, $buyer, $rfq, $vendorName, $currency, $total, $pricingMode);

        if (! $approved) {
            return $rfq;
        }

        $this->approveQuotationForComparison($tenant, $buyer, $rfq, $currency, $total, $pricingMode);

        return $rfq;
    }

    private function addApprovedQuotation(
        Tenant $tenant,
        User $buyer,
        Rfq $rfq,
        string $vendorName,
        string $currency,
        string $total,
        string $pricingMode,
    ): void {
        $this->createQuotationForRfq($tenant, $buyer, $rfq, $vendorName, $currency, $total, $pricingMode);
        $this->approveQuotationForComparison($tenant, $buyer, $rfq, $currency, $total, $pricingMode);
    }

    private function createQuotationForRfq(
        Tenant $tenant,
        User $buyer,
        Rfq $rfq,
        string $vendorName,
        string $currency,
        string $total,
        string $pricingMode,
    ): Quotation {
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $vendorName,
            'status' => 'active',
        ]);

        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => Str::slug($vendorName).'@example.com',
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => $currency,
            'total_amount' => $total,
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'manual_entry_complete' => true,
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'source' => 'buyer_manual_entry',
            'status' => QuotationStatus::submitted->value,
            'currency' => $currency,
            'total_amount' => $total,
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);

        $quotation->forceFill(['current_version_id' => $version->id])->save();

        $version->lineItems()->create([
            'tenant_id' => $tenant->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'description' => 'Laptop',
            'quantity' => '10.0000',
            'unit' => 'each',
            'unit_price' => $pricingMode === 'bundle' ? null : $total,
            'total_amount' => $pricingMode === 'bundle' ? null : $total,
            'position' => 1,
        ]);

        return $quotation;
    }

    private function approveQuotationForComparison(
        Tenant $tenant,
        User $buyer,
        Rfq $rfq,
        string $currency,
        string $total,
        string $pricingMode,
    ): void {
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();

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
                'normalized_value' => $currency,
                'data_type' => 'currency',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.totalAmount',
                'normalized_value' => $total,
                'data_type' => 'money',
                'currency' => $currency,
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.leadTimeDays',
                'normalized_value' => '14',
                'data_type' => 'integer',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.paymentTerms',
                'normalized_value' => 'Net 30',
                'data_type' => 'text',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.deliveryTerms',
                'normalized_value' => 'DAP',
                'data_type' => 'text',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.warrantyTerms',
                'normalized_value' => '12 months',
                'data_type' => 'text',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.complianceNotes',
                'normalized_value' => 'Compliant',
                'data_type' => 'text',
                'source' => 'manual_entry',
            ],
        ]);

        $lineGroup = $normalization->lineGroups()->create([
            'tenant_id' => $tenant->id,
            'group_number' => 1,
            'pricing_mode' => $pricingMode,
            'description' => 'Laptop',
            'currency' => $currency,
            'bundle_total_amount' => $pricingMode === QuotationNormalizationPricingMode::Bundle->value ? $total : null,
        ]);

        $lineGroup->mappings()->create([
            'tenant_id' => $tenant->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'mapping_type' => $pricingMode === QuotationNormalizationPricingMode::Bundle->value
                ? QuotationNormalizationMappingType::Bundled->value
                : QuotationNormalizationMappingType::Full->value,
            'quantity' => '10',
            'unit' => 'each',
            'line_total' => $pricingMode === QuotationNormalizationPricingMode::Bundle->value ? null : $total,
        ]);
    }
}
