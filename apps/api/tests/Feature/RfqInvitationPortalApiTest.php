<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RfqInvitationPortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_regenerate_portal_link_and_normal_invitation_list_hides_raw_token(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $token = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$invitation->id}/portal-link")
            ->assertOk()
            ->assertJsonPath('data.invitationId', (string) $invitation->id)
            ->assertJsonPath('data.portalUrl', fn (string $url): bool => str_contains($url, '/vendor/rfq-invitations/'))
            ->assertJsonPath('data.expiresAt', fn (?string $value): bool => $value !== null)
            ->json('data.token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertDatabaseHas('rfq_invitations', [
            'id' => $invitation->id,
            'portal_token_hash' => hash('sha256', $token),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertOk()
            ->assertJsonMissing(['token' => $token])
            ->assertJsonPath('data.0.portalAccess.hasToken', true);
    }

    public function test_valid_portal_token_returns_vendor_safe_rfq_package_and_records_audit(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer, [
            'scope_summary' => 'Supply laptops for field teams.',
            'response_instructions' => 'Submit pricing and delivery terms.',
            'required_documents' => [
                ['key' => 'quote_pdf', 'label' => 'Quotation PDF', 'required' => true],
            ],
            'line_items' => [
                ['description' => 'Laptop', 'quantity' => 10, 'unit' => 'each', 'notes' => '16GB RAM minimum'],
            ],
            'evaluation_notes' => 'Internal scoring notes.',
            'internal_notes' => 'Buyer-only notes.',
        ]);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant, ['name' => 'Northwind Traders']));
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.id', (string) $invitation->id)
            ->assertJsonPath('data.invitation.status', RfqInvitationStatus::Sent->value)
            ->assertJsonPath('data.vendor.name', 'Northwind Traders')
            ->assertJsonPath('data.rfq.title', 'Laptop refresh RFQ')
            ->assertJsonPath('data.rfq.scopeSummary', 'Supply laptops for field teams.')
            ->assertJsonPath('data.rfq.responseInstructions', 'Submit pricing and delivery terms.')
            ->assertJsonPath('data.rfq.requiredDocuments.0.key', 'quote_pdf')
            ->assertJsonPath('data.rfq.lineItems.0.description', 'Laptop')
            ->assertJsonMissing(['evaluationNotes' => 'Internal scoring notes.'])
            ->assertJsonMissing(['internalNotes' => 'Buyer-only notes.'])
            ->assertJsonMissing(['permissions']);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => null,
            'event_type' => 'rfq_invitation.portal_viewed',
            'subject_type' => RfqInvitation::class,
            'subject_id' => $invitation->id,
        ]);
        $this->assertSame(1, $invitation->refresh()->portal_view_count);
        $this->assertNotNull($invitation->portal_last_viewed_at);
    }

    public function test_vendor_portal_package_and_quotation_routes_only_record_views_for_the_package_open_route(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}")
            ->assertOk();
        $this->assertSame(1, $invitation->refresh()->portal_view_count);

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}/quotation")
            ->assertOk();
        $this->assertSame(1, $invitation->refresh()->portal_view_count);

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions")
            ->assertOk();
        $this->assertSame(1, $invitation->refresh()->portal_view_count);

        $this->actingAsTenant($tenant, $buyer)
            ->post("/api/vendor-portal/rfq-invitations/{$token}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('buyer-quote.pdf', 128, 'application/pdf'),
            ])
            ->assertCreated();
        $this->assertSame(1, $invitation->refresh()->portal_view_count);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();
        $this->assertSame(1, $invitation->refresh()->portal_view_count);
    }

    public function test_invalid_expired_cancelled_declined_and_expired_invitation_tokens_do_not_expose_rfq_details(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);

        $this->getJson('/api/vendor-portal/rfq-invitations/not-a-real-token-not-a-real-token-1234')
            ->assertNotFound()
            ->assertJsonMissing(['Laptop refresh RFQ']);

        $expired = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $expiredToken = $this->issuePortalToken($tenant, $buyer, $expired);
        $expired->forceFill(['portal_token_expires_at' => now()->subMinute()])->save();

        $this->getJson("/api/vendor-portal/rfq-invitations/{$expiredToken}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict')
            ->assertJsonMissing(['Laptop refresh RFQ']);

        foreach ([RfqInvitationStatus::Cancelled, RfqInvitationStatus::Declined, RfqInvitationStatus::Expired] as $status) {
            $blocked = $this->invitation($tenant, $rfq, $this->vendor($tenant), ['status' => $status]);
            $blockedToken = Str::random(64);
            $blocked->forceFill([
                'portal_token_hash' => hash('sha256', $blockedToken),
                'portal_token_created_at' => now()->subHour(),
                'portal_token_expires_at' => now()->addDay(),
            ])->save();

            $this->getJson("/api/vendor-portal/rfq-invitations/{$blockedToken}")
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'conflict')
                ->assertJsonMissing(['Laptop refresh RFQ']);
        }
    }

    public function test_acknowledged_invitation_remains_portal_readable_for_future_quotation_upload(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant), [
            'status' => RfqInvitationStatus::Acknowledged,
            'acknowledged_at' => now(),
        ]);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.status', RfqInvitationStatus::Acknowledged->value);
    }

    public function test_requester_cannot_regenerate_portal_link(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/rfq-invitations/{$invitation->id}/portal-link")
            ->assertForbidden();
    }

    private function issuePortalToken(Tenant $tenant, User $buyer, RfqInvitation $invitation): string
    {
        return (string) $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$invitation->id}/portal-link")
            ->assertOk()
            ->json('data.token');
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
