<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationUploadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_portal_upload_creates_received_quotation_and_attachment(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant, ['name' => 'Northwind Traders']);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $response = $this->post(
            "/api/vendor-portal/rfq-invitations/{$token}/quotation/attachments",
            ['file' => UploadedFile::fake()->create('northwind-quote.pdf', 128, 'application/pdf')],
        );

        $response->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.submissionSource', 'vendor_portal')
            ->assertJsonPath('data.fileCount', 1)
            ->assertJsonPath('data.attachments.0.parentType', 'quotation')
            ->assertJsonPath('data.attachments.0.filename', 'northwind-quote.pdf');

        $quotationId = (string) $response->json('data.id');
        $quotation = Quotation::query()->findOrFail($quotationId);

        $this->assertDatabaseHas('quotations', [
            'id' => $quotationId,
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'rfq_invitation_id' => $invitation->id,
            'status' => 'received',
            'submission_source' => 'vendor_portal',
            'file_count' => 1,
        ]);

        $attachment = Attachment::query()->where('attachable_type', Quotation::class)->firstOrFail();
        Storage::disk('attachments')->assertExists($attachment->storage_path);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.created',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.attachment_uploaded',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'rfq_invitation.quotation_received',
        ]);
    }

    public function test_buyer_upload_creates_received_quotation_and_records_submitter(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $response = $this->actingAsTenant($tenant, $buyer)
            ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('buyer-quote.pdf', 128, 'application/pdf'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.submissionSource', 'buyer_upload')
            ->assertJsonPath('data.submittedByUser.id', (string) $buyer->id)
            ->assertJsonPath('data.fileCount', 1);

        $quotationId = (string) $response->json('data.id');

        $this->assertDatabaseHas('quotations', [
            'id' => $quotationId,
            'tenant_id' => $tenant->id,
            'rfq_invitation_id' => $invitation->id,
            'submission_source' => 'buyer_upload',
            'submitted_by_user_id' => $buyer->id,
            'file_count' => 1,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'quotation.created',
        ]);
    }

    public function test_buyer_can_login_and_access_protected_endpoints(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $buyer->forceFill([
            'email' => 'quotation-buyer@example.com',
            'password' => Hash::make('secret123'),
        ])->save();
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'quotation-buyer@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $upload = $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('session-buyer-quote.pdf', 128, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.submittedByUser.id', (string) $buyer->id);

        $quotationId = (string) $upload->json('data.id');

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfq-invitations/{$invitation->id}/quotation")
            ->assertOk()
            ->assertJsonPath('data.id', $quotationId);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/quotations/{$quotationId}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'session-buyer-quote.pdf');
    }

    public function test_protected_endpoints_return_401_when_unauthenticated(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfq-invitations/{$invitation->id}/quotation")
            ->assertUnauthorized();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->withHeader('Accept', 'application/json')
            ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('unauthenticated.pdf', 128, 'application/pdf'),
            ])
            ->assertUnauthorized();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/quotations/' . Str::uuid() . '/attachments')
            ->assertUnauthorized();
    }

    public function test_session_failure_results_in_401(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $buyer->forceFill([
            'email' => 'quotation-session-failure@example.com',
            'password' => Hash::make('secret123'),
        ])->save();
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'quotation-session-failure@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfq-invitations/{$invitation->id}/quotation")
            ->assertUnauthorized();
    }

    public function test_buyer_upload_rejects_invitations_that_are_not_accepting_quotations(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);

        foreach ([RfqInvitationStatus::Cancelled, RfqInvitationStatus::Declined, RfqInvitationStatus::Expired] as $status) {
            $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant), ['status' => $status]);

            $this->actingAsTenant($tenant, $buyer)
                ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                    'file' => UploadedFile::fake()->create('buyer-quote.pdf', 128, 'application/pdf'),
                ])
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'conflict');
        }

        $this->assertSame(0, Quotation::query()->count());
        $this->assertSame(0, Attachment::query()->where('attachable_type', Quotation::class)->count());
    }

    public function test_repeated_uploads_append_to_the_same_quotation_for_the_same_invitation(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $first = $this->post(
            "/api/vendor-portal/rfq-invitations/{$token}/quotation/attachments",
            ['file' => UploadedFile::fake()->create('northwind-quote-v1.pdf', 128, 'application/pdf')],
        )->assertCreated();

        $second = $this->post(
            "/api/vendor-portal/rfq-invitations/{$token}/quotation/attachments",
            ['file' => UploadedFile::fake()->create('northwind-quote-v2.pdf', 128, 'application/pdf')],
        )->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(2, $second->json('data.fileCount'));
        $this->assertSame(2, Attachment::query()->where('attachable_type', Quotation::class)->count());
        $this->assertDatabaseHas('quotations', [
            'id' => $first->json('data.id'),
            'rfq_invitation_id' => $invitation->id,
            'file_count' => 2,
        ]);
    }

    public function test_vendor_portal_quotation_lookup_returns_null_before_upload_and_quotation_after_upload(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}/quotation")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->post(
            "/api/vendor-portal/rfq-invitations/{$token}/quotation/attachments",
            ['file' => UploadedFile::fake()->create('northwind-quote.pdf', 128, 'application/pdf')],
        )->assertCreated();

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}/quotation")
            ->assertOk()
            ->assertJsonPath('data.rfqInvitationId', (string) $invitation->id)
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.attachments.0.parentType', 'quotation');
    }

    public function test_vendor_portal_quotation_view_redacts_internal_buyer_identity(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $quotationId = $this->actingAsTenant($tenant, $buyer)
            ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('buyer-quote.pdf', 128, 'application/pdf'),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}/quotation")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $quotationId)
            ->assertJsonPath('data.submittedByUser', null)
            ->assertJsonPath('data.attachments.0.uploadedBy', null);
    }

    public function test_buyer_can_lookup_the_tenant_scoped_quotation_and_its_attachments(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $upload = $this->actingAsTenant($tenant, $buyer)
            ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('buyer-quote.pdf', 128, 'application/pdf'),
            ])
            ->assertCreated();

        $quotationId = (string) $upload->json('data.id');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfq-invitations/{$invitation->id}/quotation")
            ->assertOk()
            ->assertJsonPath('data.id', $quotationId)
            ->assertJsonPath('data.submissionSource', 'buyer_upload');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/quotations/{$quotationId}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.parentType', 'quotation')
            ->assertJsonPath('data.0.filename', 'buyer-quote.pdf');
    }

    public function test_invalid_expired_cancelled_declined_and_expired_status_tokens_are_safe(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);

        $this->getJson('/api/vendor-portal/rfq-invitations/not-a-real-token-not-a-real-token-1234/quotation')
            ->assertNotFound()
            ->assertJsonMissing(['Laptop refresh RFQ']);

        $expired = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $expiredToken = $this->issuePortalToken($tenant, $buyer, $expired);
        $expired->forceFill(['portal_token_expires_at' => now()->subMinute()])->save();

        $this->getJson("/api/vendor-portal/rfq-invitations/{$expiredToken}/quotation")
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

            $this->getJson("/api/vendor-portal/rfq-invitations/{$blockedToken}/quotation")
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'conflict')
                ->assertJsonMissing(['Laptop refresh RFQ']);
        }
    }

    public function test_cross_tenant_buyer_access_cannot_reach_the_quotation_or_attachments(): void
    {
        Storage::fake('attachments');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $quotationId = $this->actingAsTenant($tenant, $buyer)
            ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('buyer-quote.pdf', 128, 'application/pdf'),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/rfq-invitations/{$invitation->id}/quotation")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/quotations/{$quotationId}/attachments")
            ->assertNotFound();
    }

    public function test_upload_validation_rejects_empty_files_and_disallowed_extensions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->post("/api/vendor-portal/rfq-invitations/{$token}/quotation/attachments", [
            'file' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->post("/api/vendor-portal/rfq-invitations/{$token}/quotation/attachments", [
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
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
}
