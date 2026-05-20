<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationManualEntryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_save_structured_quotation_terms_and_line_items(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer, [
            'line_items' => [
                [
                    'id' => 'rfq-line-1',
                    'name' => 'Developer laptop',
                    'description' => 'Developer laptop',
                    'quantity' => '10.0000',
                    'unit' => 'each',
                    'estimated_unit_price' => '1100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);
        $vendor = $this->vendor($tenant, ['name' => 'Northwind Traders']);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $quotation = $this->quotation($tenant, $rfq, $vendor, $invitation);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/quotations/{$quotation->id}/manual-entry", $this->validManualEntryPayload([
                'buyerNotes' => 'Buyer confirmed totals by email.',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $quotation->id)
            ->assertJsonPath('data.manualEntry.quotationReference', 'NW-Q-2026-041')
            ->assertJsonPath('data.manualEntry.buyerNotes', 'Buyer confirmed totals by email.')
            ->assertJsonPath('data.lineItems.0.rfqLineItemId', 'rfq-line-1')
            ->assertJsonPath('data.lineItems.0.description', 'Developer laptop')
            ->assertJsonPath('data.completeness.isComplete', true)
            ->assertJsonPath('data.completeness.lineItemCount', 1)
            ->assertJsonPath('data.permissions.canEditManualEntry', true);

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'quotation_reference' => 'NW-Q-2026-041',
            'currency' => 'USD',
            'total_amount' => '12470.00',
            'buyer_notes' => 'Buyer confirmed totals by email.',
        ]);
        $this->assertDatabaseHas('quotation_line_items', [
            'quotation_id' => $quotation->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'description' => 'Developer laptop',
            'compliance_status' => 'compliant',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.manual_entry_saved',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.line_items_saved',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.completeness_changed',
        ]);
    }

    public function test_buyer_manual_entry_does_not_require_uploaded_files(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $quotation = $this->quotation($tenant, $rfq, $vendor, $invitation);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/quotations/{$quotation->id}/manual-entry", $this->validManualEntryPayload())
            ->assertOk()
            ->assertJsonPath('data.fileCount', 0)
            ->assertJsonPath('data.manualEntry.totalAmount', '12470.00');
    }

    public function test_buyer_can_create_structured_quotation_without_existing_response_record(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload());

        $response->assertOk()
            ->assertJsonPath('data.rfqInvitationId', (string) $invitation->id)
            ->assertJsonPath('data.fileCount', 0)
            ->assertJsonPath('data.manualEntry.totalAmount', '12470.00')
            ->assertJsonPath('data.completeness.isComplete', true);

        $this->assertDatabaseHas('quotations', [
            'tenant_id' => $tenant->id,
            'rfq_invitation_id' => $invitation->id,
            'submission_source' => 'buyer_upload',
            'file_count' => 0,
        ]);
    }

    public function test_vendor_can_save_structured_quotation_terms_through_portal_token(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $payload = $this->validManualEntryPayload([
            'buyerNotes' => 'This must be ignored for vendor portal saves.',
            'vendorNotes' => 'Vendor entered this through the portal.',
        ]);

        $response = $this->putJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/manual-entry", $payload);

        $response->assertOk()
            ->assertJsonPath('data.submissionSource', 'vendor_portal')
            ->assertJsonPath('data.manualEntry.vendorNotes', 'Vendor entered this through the portal.')
            ->assertJsonPath('data.manualEntry.buyerNotes', null)
            ->assertJsonPath('data.permissions.canUploadAttachment', true)
            ->assertJsonPath('data.permissions.canViewAttachments', true)
            ->assertJsonPath('data.permissions.canEditManualEntry', true)
            ->assertJsonPath('data.submittedByVendorContact.email', $invitation->contact_email);

        $quotationId = $response->json('data.id');
        $this->assertDatabaseHas('quotations', [
            'id' => $quotationId,
            'buyer_notes' => null,
            'vendor_notes' => 'Vendor entered this through the portal.',
        ]);
    }

    public function test_vendor_cannot_save_buyer_only_fields(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->putJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/manual-entry", $this->validManualEntryPayload([
            'buyerNotes' => 'Hidden internal buyer note',
        ]))->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $this->assertNull($quotation->buyer_notes);
    }

    public function test_terminal_invitation_cannot_save_vendor_manual_entry(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor, ['status' => RfqInvitationStatus::Declined->value]);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->putJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertConflict();
    }

    public function test_cross_tenant_buyer_cannot_save_manual_entry(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $quotation = $this->quotation($tenant, $rfq, $vendor, $invitation);

        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->putJson("/api/quotations/{$quotation->id}/manual-entry", $this->validManualEntryPayload())
            ->assertNotFound();
    }

    public function test_manual_entry_validation_returns_field_errors(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $quotation = $this->quotation($tenant, $rfq, $vendor, $invitation);

        $payload = $this->validManualEntryPayload([
            'currency' => 'US',
            'totalAmount' => '-1.00',
            'leadTimeDays' => -5,
            'lineItems' => [
                [
                    'description' => '',
                    'quantity' => '0',
                    'unitPrice' => '-10.00',
                    'complianceStatus' => 'unknown',
                ],
            ],
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/quotations/{$quotation->id}/manual-entry", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors([
                'currency',
                'totalAmount',
                'leadTimeDays',
                'lineItems.0.description',
                'lineItems.0.quantity',
                'lineItems.0.unitPrice',
                'lineItems.0.complianceStatus',
            ]);
    }

    private function issuePortalToken(Tenant $tenant, User $buyer, RfqInvitation $invitation): string
    {
        $this->assertSame($tenant->id, $invitation->tenant_id);
        $this->assertTrue($tenant->users()->whereKey($buyer->id)->exists());

        $token = Str::random(64);

        $invitation->forceFill([
            'portal_token_hash' => hash('sha256', $token),
            'portal_token_created_at' => now(),
            'portal_token_expires_at' => now()->addDays(14),
        ])->save();

        return $token;
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    /**
     * @return array{Tenant, User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant ' . Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function vendor(Tenant $tenant, array $overrides = []): Vendor
    {
        return Vendor::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Vendor ' . Str::uuid(),
            'status' => 'active',
            'category' => 'IT Hardware',
            'risk_rating' => 'low',
            'metadata' => [
                'contactName' => 'Vendor Contact',
                'contactEmail' => 'vendor@example.test',
            ],
        ], $overrides));
    }

    private function draftRfq(Tenant $tenant, User $requester, User $buyer, array $overrides = []): Rfq
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-' . Str::random(8),
            'title' => 'Laptop refresh',
            'status' => RequisitionStatus::Approved,
            'currency' => 'USD',
        ]);

        $review = SourcingIntakeReview::query()->create([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'assigned_buyer_id' => $buyer->id,
            'status' => SourcingIntakeStatus::ReadyForRfq,
            'sourcing_path' => SourcingPath::NeedsRfq,
            'decision_reason' => 'Competitive sourcing required.',
        ]);

        return Rfq::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'sourcing_intake_review_id' => $review->id,
            'requisition_id' => $requisition->id,
            'number' => 'RFQ-' . Str::random(8),
            'title' => 'Laptop refresh RFQ',
            'status' => RfqStatus::Draft,
            'required_documents' => [],
            'line_items' => [],
            'response_due_at' => now()->addDays(14),
            'response_instructions' => 'Submit pricing and delivery terms.',
        ], $overrides));
    }

    private function invitation(Tenant $tenant, Rfq $rfq, Vendor $vendor, array $overrides = []): RfqInvitation
    {
        return RfqInvitation::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent,
            'contact_name' => 'Vendor Contact',
            'contact_email' => 'vendor@example.test',
            'message' => 'Please respond.',
            'response_due_at' => now()->addDays(14),
            'sent_at' => now(),
        ], $overrides));
    }

    private function quotation(Tenant $tenant, Rfq $rfq, Vendor $vendor, RfqInvitation $invitation): Quotation
    {
        return Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'rfq_invitation_id' => $invitation->id,
            'number' => 'QUO-' . Str::random(8),
            'status' => 'received',
            'submission_source' => 'buyer_upload',
            'file_count' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validManualEntryPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'quotationReference' => 'NW-Q-2026-041',
            'quotedAt' => '2026-05-20',
            'validUntil' => '2026-06-20',
            'currency' => 'USD',
            'subtotalAmount' => '12000.00',
            'taxAmount' => '720.00',
            'freightAmount' => '250.00',
            'discountAmount' => '500.00',
            'totalAmount' => '12470.00',
            'paymentTerms' => 'Net 30',
            'deliveryTerms' => 'Delivered to site',
            'leadTimeDays' => 21,
            'warrantyTerms' => '3 years onsite',
            'exclusions' => 'Installation not included',
            'complianceNotes' => 'Meets requested hardware specification',
            'buyerNotes' => null,
            'vendorNotes' => 'Subject to stock availability',
            'lineItems' => [
                [
                    'rfqLineItemId' => 'rfq-line-1',
                    'description' => 'Developer laptop',
                    'quantity' => '10.0000',
                    'unit' => 'each',
                    'unitPrice' => '1200.00',
                    'subtotalAmount' => '12000.00',
                    'taxAmount' => '720.00',
                    'totalAmount' => '12720.00',
                    'leadTimeDays' => 21,
                    'manufacturer' => 'Lenovo',
                    'modelNumber' => 'ThinkPad T-series',
                    'alternateOffered' => false,
                    'complianceStatus' => 'compliant',
                    'notes' => 'Quoted as requested',
                ],
            ],
        ], $overrides);
    }
}
