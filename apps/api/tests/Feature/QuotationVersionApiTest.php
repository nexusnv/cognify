<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationVersionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_manual_entry_creates_version_one(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload([
                'quotationReference' => 'NW-Q-2026-041',
                'totalAmount' => '12470.00',
                'vendorNotes' => 'Initial submitted quotation.',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.currentVersion.versionNumber', 1)
            ->assertJsonPath('data.currentVersion.isCurrent', true)
            ->assertJsonPath('data.versionCount', 1);

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $this->assertDatabaseHas('quotation_versions', [
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'quotation_reference' => 'NW-Q-2026-041',
            'total_amount' => '12470.00',
            'vendor_notes' => 'Initial submitted quotation.',
        ]);
        $this->assertDatabaseHas('quotation_version_line_items', [
            'tenant_id' => $tenant->id,
            'quotation_version_id' => $quotation->current_version_id,
            'description' => 'Developer laptop',
            'quantity' => '10.0000',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.version_created',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.current_version_changed',
        ]);
    }

    public function test_upload_creates_attachment_snapshot_on_current_version(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('vendor-quotation.pdf', 24, 'application/pdf'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.currentVersion.versionNumber', 1)
            ->assertJsonPath('data.currentVersion.attachmentCount', 1);

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->assertSame('vendor-quotation.pdf', $version->attachment_snapshots[0]['filename']);
        $this->assertSame('application/pdf', $version->attachment_snapshots[0]['mimeType']);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.version_created',
        ]);
    }

    public function test_buyer_revision_creates_next_current_version_and_supersedes_previous(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload([
                'quotationReference' => 'NW-Q-2026-041',
                'totalAmount' => '12470.00',
            ]))
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $firstVersionId = $quotation->current_version_id;

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload([
                'quotationReference' => 'NW-Q-2026-041-R2',
                'totalAmount' => '11990.00',
                'buyerNotes' => 'Buyer corrected totals from revised quote.',
                'vendorNotes' => 'Vendor revised pricing after stock check.',
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.versionNumber', 2)
            ->assertJsonPath('data.isCurrent', true)
            ->assertJsonPath('data.manualEntry.quotationReference', 'NW-Q-2026-041-R2')
            ->assertJsonPath('data.manualEntry.totalAmount', '11990.00')
            ->assertJsonPath('data.previousVersionId', (string) $firstVersionId);

        $this->assertDatabaseHas('quotation_versions', [
            'id' => $firstVersionId,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('quotation_versions', [
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 2,
            'is_current' => true,
            'total_amount' => '11990.00',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.version_superseded',
        ]);
    }

    public function test_buyer_can_list_and_show_read_only_versions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload([
                'quotationReference' => 'NW-Q-2026-041',
                'totalAmount' => '12470.00',
            ]))
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload([
                'quotationReference' => 'NW-Q-2026-041-R2',
                'totalAmount' => '11990.00',
            ]))
            ->assertCreated();

        $versions = $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/quotations/{$quotation->id}/versions");

        $versions->assertOk()
            ->assertJsonPath('data.0.versionNumber', 2)
            ->assertJsonPath('data.0.isCurrent', true)
            ->assertJsonPath('data.1.versionNumber', 1)
            ->assertJsonPath('data.1.isCurrent', false);

        $firstVersion = QuotationVersion::query()
            ->where('quotation_id', $quotation->id)
            ->where('version_number', 1)
            ->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/quotations/{$quotation->id}/versions/{$firstVersion->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $firstVersion->id)
            ->assertJsonPath('data.isCurrent', false)
            ->assertJsonPath('data.permissions.canEdit', false)
            ->assertJsonPath('data.manualEntry.totalAmount', '12470.00');
    }

    public function test_vendor_portal_revision_creates_vendor_safe_current_version(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload([
                'buyerNotes' => 'Internal buyer note.',
                'totalAmount' => '12470.00',
            ]))
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();

        $response = $this->postJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions", $this->validRevisionPayload([
            'buyerNotes' => 'Attempted hidden note.',
            'vendorNotes' => 'Revised from vendor portal.',
            'totalAmount' => '11990.00',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.versionNumber', 2)
            ->assertJsonPath('data.source', 'vendor_portal')
            ->assertJsonPath('data.manualEntry.buyerNotes', null)
            ->assertJsonPath('data.manualEntry.vendorNotes', 'Revised from vendor portal.');

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions")
            ->assertOk()
            ->assertJsonPath('data.0.versionNumber', 2)
            ->assertJsonPath('data.0.manualEntry.buyerNotes', null);

        $this->assertDatabaseHas('quotation_versions', [
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 2,
            'vendor_notes' => 'Revised from vendor portal.',
            'buyer_notes' => 'Internal buyer note.',
        ]);
    }

    public function test_cross_tenant_buyer_cannot_read_or_create_versions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/quotations/{$quotation->id}/versions")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/quotations/{$quotation->id}/versions/{$version->id}")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload())
            ->assertNotFound();
    }

    public function test_missing_tenant_header_blocks_version_endpoints_for_ambiguous_user(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $otherTenant = Tenant::query()->create(['name' => 'Second tenant']);
        $otherTenant->users()->attach($buyer->id, ['role' => 'buyer']);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();

        Sanctum::actingAs($buyer);

        $this->withoutHeader('X-Tenant-Id')
            ->getJson("/api/quotations/{$quotation->id}/versions")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ambiguous_tenant');

        $this->withoutHeader('X-Tenant-Id')
            ->getJson("/api/quotations/{$quotation->id}/versions/{$version->id}")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ambiguous_tenant');

        $this->withoutHeader('X-Tenant-Id')
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload())
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ambiguous_tenant');
    }

    public function test_mismatched_tenant_header_blocks_version_endpoints(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $wrongTenant = Tenant::query()->create(['name' => 'Wrong tenant']);

        Sanctum::actingAs($buyer);

        $this->withHeader('X-Tenant-Id', (string) $wrongTenant->id)
            ->getJson("/api/quotations/{$quotation->id}/versions")
            ->assertForbidden()
            ->assertJsonPath('error.message', 'Tenant membership is required.');

        $this->withHeader('X-Tenant-Id', (string) $wrongTenant->id)
            ->getJson("/api/quotations/{$quotation->id}/versions/{$version->id}")
            ->assertForbidden()
            ->assertJsonPath('error.message', 'Tenant membership is required.');

        $this->withHeader('X-Tenant-Id', (string) $wrongTenant->id)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload())
            ->assertForbidden()
            ->assertJsonPath('error.message', 'Tenant membership is required.');
    }

    public function test_buyer_session_login_can_create_version_and_logout_revokes_access(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $buyer->forceFill([
            'email' => 'quotation-version-buyer@example.com',
            'password' => Hash::make('secret123'),
        ])->save();
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'quotation-version-buyer@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk()
            ->assertJsonPath('data.currentVersion.versionNumber', 1);

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload([
                'quotationReference' => 'NW-Q-2026-041-R2',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.versionNumber', 2);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/quotations/{$quotation->id}/versions")
            ->assertUnauthorized();
    }

    public function test_version_endpoints_return_401_when_unauthenticated(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();

        Auth::forgetGuards();
        $this->flushSession();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/quotations/{$quotation->id}/versions")
            ->assertUnauthorized();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/quotations/{$quotation->id}/versions/{$version->id}")
            ->assertUnauthorized();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload())
            ->assertUnauthorized();
    }

    public function test_non_editable_rfq_blocks_buyer_revision(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $rfq->forceFill(['status' => RfqStatus::Open->value])->save();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload())
            ->assertForbidden();
    }

    public function test_terminal_invitation_blocks_vendor_revision(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor, [
            'status' => RfqInvitationStatus::Declined->value,
        ]);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->postJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions", $this->validRevisionPayload())
            ->assertConflict();
    }

    public function test_revision_requires_existing_quotation(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->postJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions", $this->validRevisionPayload())
            ->assertNotFound();
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
            'number' => 'QUO-'.Str::random(8),
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
