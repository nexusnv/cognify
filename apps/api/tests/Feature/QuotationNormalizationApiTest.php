<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Jobs\NormalizeQuotationVersion;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationNormalizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_current_quotation_version_dispatches_normalization(): void
    {
        Bus::fake();

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        Bus::assertDispatched(NormalizeQuotationVersion::class, function (NormalizeQuotationVersion $job) use ($tenant, $version): bool {
            return $job->tenantId === $tenant->id && $job->quotationVersionId === $version->id;
        });
    }

    public function test_buyer_can_review_correct_map_and_approve_normalization(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $normalization = $this->normalizationNeedingReview($tenant, $requester, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/quotation-normalizations/{$normalization->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_review')
            ->assertJsonPath('data.permissions.canApprove', false)
            ->assertJsonPath('data.issues.0.severity', 'blocking');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotation-normalizations/{$normalization->id}/corrections", [
                'corrections' => [[
                    'issueId' => (string) $normalization->issues()->where('severity', 'blocking')->firstOrFail()->id,
                    'fieldPath' => 'manualEntry.currency',
                    'correctedValue' => 'USD',
                    'correctionNote' => 'Supplier quote is USD.',
                    'resolutionNote' => 'Currency confirmed against submitted quotation.',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_review');

        $versionLineId = (string) $normalization->quotationVersion->lineItems()->firstOrFail()->id;

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotation-normalizations/{$normalization->id}/line-mappings", [
                'lineGroups' => [[
                    'groupNumber' => 1,
                    'pricingMode' => 'bundle',
                    'description' => 'Laptop bundle',
                    'currency' => 'USD',
                    'bundleTotalAmount' => '12470.00',
                    'notes' => 'Vendor bundled laptops and freight.',
                    'mappings' => [[
                        'rfqLineItemId' => 'rfq-line-1',
                        'quotationVersionLineItemId' => $versionLineId,
                        'mappingType' => 'bundled',
                        'quantity' => '10',
                        'unit' => 'each',
                        'unitPrice' => null,
                        'lineTotal' => null,
                        'buyerNote' => 'Covered by bundle total.',
                    ]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_approval');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotation-normalizations/{$normalization->id}/approve", [
                'approvalNote' => 'Normalization reviewed for comparison.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.permissions.canEdit', false);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation_normalization.approved',
        ]);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $buyer->id,
            'type' => 'quotation_normalization.approved',
        ]);
    }

    public function test_requester_approver_vendor_and_cross_tenant_users_cannot_access_normalization(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);
        $normalization = $this->normalizationNeedingReview($tenant, $requester, $buyer);
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/quotation-normalizations')
            ->assertForbidden();

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/quotation-normalizations/{$normalization->id}")
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/quotation-normalizations/{$normalization->id}")
            ->assertNotFound();

        $token = $this->issuePortalToken($tenant, $buyer, $normalization->quotationVersion->quotation->rfqInvitation);

        $this->postJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/normalizations")
            ->assertNotFound();
    }

    private function normalizationNeedingReview(Tenant $tenant, User $requester, User $buyer): QuotationNormalization
    {
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $normalization = QuotationNormalization::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 1,
            'status' => 'needs_review',
        ]);

        $normalization->issues()->create([
            'tenant_id' => $tenant->id,
            'severity' => 'blocking',
            'status' => 'open',
            'issue_code' => 'currency_mismatch',
            'field_path' => 'manualEntry.currency',
            'message' => 'Currency must be reviewed.',
        ]);

        return $normalization->refresh()->load([
            'quotationVersion.lineItems',
            'issues',
        ]);
    }

    private function validRevisionPayload(array $overrides = []): array
    {
        return array_replace_recursive($this->validManualEntryPayload(), [
            'attachmentIds' => [],
        ], $overrides);
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
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function vendor(Tenant $tenant, array $overrides = []): Vendor
    {
        return Vendor::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Vendor '.Str::uuid(),
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
            'number' => 'REQ-'.Str::random(8),
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
            'number' => 'RFQ-'.Str::random(8),
            'title' => 'Laptop refresh RFQ',
            'status' => RfqStatus::Draft,
            'required_documents' => [],
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
