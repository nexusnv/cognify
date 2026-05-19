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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RfqInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_list_resend_and_cancel_rfq_invitation(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant, [
            'name' => 'Northwind Traders',
        ]);

        $created = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
                'message' => 'Please respond with pricing and delivery details.',
                'responseDueAt' => '2026-06-30T17:00:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.vendor.id', (string) $vendor->id)
            ->assertJsonPath('data.0.status', RfqInvitationStatus::Sent->value)
            ->assertJsonPath('data.0.permissions.canResend', true)
            ->json('data.0.id');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created)
            ->assertJsonPath('data.0.vendor.name', 'Northwind Traders');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$created}/resend")
            ->assertOk()
            ->assertJsonPath('data.status', RfqInvitationStatus::Sent->value);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$created}/cancel", [
                'cancelReason' => 'Vendor no longer in scope.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', RfqInvitationStatus::Cancelled->value)
            ->assertJsonPath('data.cancelReason', 'Vendor no longer in scope.');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq_invitation.created',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq_invitation.resent',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq_invitation.cancelled',
        ]);
    }

    public function test_duplicate_active_invitation_returns_conflict(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
            ])
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_requester_cannot_manage_rfq_invitations(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
            ])
            ->assertForbidden();

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertForbidden();

        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/rfq-invitations/{$invitation->id}/resend")
            ->assertForbidden();
    }

    public function test_invitation_actions_are_tenant_scoped(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');
        [, $otherBuyer] = $this->tenantUser('buyer', $otherTenant);
        $otherRfq = $this->draftRfq($otherTenant, $otherRequester, $otherBuyer);
        $otherInvitation = $this->invitation($otherTenant, $otherRfq, $this->vendor($otherTenant));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$otherInvitation->id}/resend")
            ->assertNotFound();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$otherInvitation->id}/cancel", [
                'cancelReason' => 'Cross-tenant cancel attempt.',
            ])
            ->assertNotFound();

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfq-invitations/{$otherInvitation->id}/status", [
                'status' => RfqInvitationStatus::Acknowledged->value,
            ])
            ->assertNotFound();
    }

    public function test_cannot_invite_inactive_or_cross_tenant_vendor_or_non_draft_rfq(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $inactive = $this->vendor($tenant, ['status' => 'inactive']);
        [$otherTenant] = $this->tenantUser('buyer');
        $otherVendor = $this->vendor($otherTenant);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $inactive->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $otherVendor->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $rfq->forceFill(['status' => RfqStatus::Cancelled->value])->save();
        $active = $this->vendor($tenant, ['name' => 'Active Vendor']);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $active->id],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_status_update_supports_acknowledged_handoff_state(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $invitation = $this->invitation(
            $tenant,
            $this->draftRfq($tenant, $requester, $buyer),
            $this->vendor($tenant)
        );

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfq-invitations/{$invitation->id}/status", [
                'status' => RfqInvitationStatus::Acknowledged->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', RfqInvitationStatus::Acknowledged->value);
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
        $tenant ??= Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function vendor(Tenant $tenant, array $overrides = []): Vendor
    {
        return Vendor::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => fake()->unique()->company(),
            'status' => 'active',
            'category' => 'IT Hardware',
            'risk_rating' => 'low',
            'metadata' => [
                'contactName' => 'Vendor Contact',
                'contactEmail' => fake()->unique()->safeEmail(),
            ],
        ], $overrides));
    }

    private function draftRfq(Tenant $tenant, User $requester, User $buyer): Rfq
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-' . fake()->unique()->numerify('####'),
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

        return Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'sourcing_intake_review_id' => $review->id,
            'requisition_id' => $requisition->id,
            'number' => 'RFQ-' . fake()->unique()->numerify('####'),
            'title' => 'Laptop refresh RFQ',
            'status' => RfqStatus::Draft,
            'required_documents' => [],
            'line_items' => [],
        ]);
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
