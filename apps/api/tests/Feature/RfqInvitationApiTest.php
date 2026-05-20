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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        $responseDueAt = now()->addDays(14);

        $created = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
                'message' => 'Please respond with pricing and delivery details.',
                'responseDueAt' => $responseDueAt->utc()->toIso8601String(),
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

    public function test_duplicate_invitation_returns_conflict(): void
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

        $this->assertSame(
            1,
            RfqInvitation::query()
                ->where('rfq_id', $rfq->id)
                ->where('vendor_id', $vendor->id)
                ->count()
        );
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

    public function test_login_and_logout_gates_rfq_invitation_endpoints_through_session_auth(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $buyer->forceFill([
            'email' => 'rfq-invitation-buyer@example.com',
            'password' => Hash::make('secret123'),
        ])->save();

        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant, ['name' => 'Session Vendor']);
        $statusVendor = $this->vendor($tenant, ['name' => 'Status Vendor']);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'rfq-invitation-buyer@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $createdId = (string) $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
            ])
            ->assertCreated()
            ->json('data.0.id');

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $createdId);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfq-invitations/{$createdId}/resend")
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfq-invitations/{$createdId}/cancel", [
                'cancelReason' => 'No longer needed.',
            ])
            ->assertOk();

        $statusInvitation = $this->invitation($tenant, $rfq, $statusVendor);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->patchJson("/api/rfq-invitations/{$statusInvitation->id}/status", [
                'status' => RfqInvitationStatus::Acknowledged->value,
            ])
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertUnauthorized();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfq-invitations/{$createdId}/resend")
            ->assertUnauthorized();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfq-invitations/{$createdId}/cancel", [
                'cancelReason' => 'Logged out.',
            ])
            ->assertUnauthorized();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->patchJson("/api/rfq-invitations/{$statusInvitation->id}/status", [
                'status' => RfqInvitationStatus::Declined->value,
            ])
            ->assertUnauthorized();
    }

    public function test_unauthenticated_and_invalid_token_requests_cannot_access_rfq_invitation_endpoints(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertUnauthorized();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $invitation->vendor_id],
            ])
            ->assertUnauthorized();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfq-invitations/{$invitation->id}/resend")
            ->assertUnauthorized();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/rfq-invitations/{$invitation->id}/cancel", [
                'cancelReason' => 'No session.',
            ])
            ->assertUnauthorized();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->patchJson("/api/rfq-invitations/{$invitation->id}/status", [
                'status' => RfqInvitationStatus::Acknowledged->value,
            ])
            ->assertUnauthorized();

        $this->withToken('not-a-valid-token')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertUnauthorized();
    }

    public function test_invitation_actions_are_tenant_scoped(): void
    {
        [$tenant] = $this->tenantUser('requester');
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

    public function test_status_update_sets_exact_timestamp_for_each_allowed_target_state(): void
    {
        foreach ([
            RfqInvitationStatus::Acknowledged,
            RfqInvitationStatus::Declined,
            RfqInvitationStatus::Expired,
        ] as $targetStatus) {
            [$tenant, $requester] = $this->tenantUser('requester');
            [, $buyer] = $this->tenantUser('buyer', $tenant);
            $invitation = $this->invitation(
                $tenant,
                $this->draftRfq($tenant, $requester, $buyer),
                $this->vendor($tenant)
            );

            $this->actingAsTenant($tenant, $buyer)
                ->patchJson("/api/rfq-invitations/{$invitation->id}/status", [
                    'status' => $targetStatus->value,
                ])
                ->assertOk()
                ->assertJsonPath('data.status', $targetStatus->value);

            $updated = $invitation->refresh();

            $this->assertSame($targetStatus, $updated->statusState());
            $this->assertNull($updated->cancelled_at);
            $this->assertNull($updated->cancel_reason);

            $timestampFields = [
                RfqInvitationStatus::Acknowledged->value => 'acknowledged_at',
                RfqInvitationStatus::Declined->value => 'declined_at',
                RfqInvitationStatus::Expired->value => 'expired_at',
            ];

            $this->assertNotNull($updated->{$timestampFields[$targetStatus->value]});

            foreach ($timestampFields as $statusValue => $field) {
                if ($statusValue === $targetStatus->value) {
                    continue;
                }

                $this->assertNull($updated->{$field});
            }

            $this->assertDatabaseHas('audit_events', [
                'tenant_id' => $tenant->id,
                'actor_id' => $buyer->id,
                'event_type' => 'rfq_invitation.' . $targetStatus->value,
            ]);
        }
    }

    public function test_resend_is_rejected_after_acknowledged_invitation(): void
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
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$invitation->id}/resend")
            ->assertForbidden();

        $this->assertDatabaseHas('rfq_invitations', [
            'id' => $invitation->id,
            'status' => RfqInvitationStatus::Acknowledged->value,
        ]);
    }

    public function test_cancel_is_rejected_after_declined_or_expired_invitation(): void
    {
        foreach ([RfqInvitationStatus::Declined, RfqInvitationStatus::Expired] as $targetStatus) {
            [$tenant, $requester] = $this->tenantUser('requester');
            [, $buyer] = $this->tenantUser('buyer', $tenant);
            $invitation = $this->invitation(
                $tenant,
                $this->draftRfq($tenant, $requester, $buyer),
                $this->vendor($tenant)
            );

            $this->actingAsTenant($tenant, $buyer)
                ->patchJson("/api/rfq-invitations/{$invitation->id}/status", [
                    'status' => $targetStatus->value,
                ])
                ->assertOk();

            $this->actingAsTenant($tenant, $buyer)
                ->postJson("/api/rfq-invitations/{$invitation->id}/cancel", [
                    'cancelReason' => 'Vendor no longer in scope.',
                ])
                ->assertForbidden();

            $this->assertDatabaseHas('rfq_invitations', [
                'id' => $invitation->id,
                'status' => $targetStatus->value,
            ]);
        }
    }

    public function test_duplicate_create_after_terminal_invitation_returns_conflict(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);

        $invitation = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
            ])
            ->assertCreated()
            ->json('data.0.id');

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfq-invitations/{$invitation}/status", [
                'status' => RfqInvitationStatus::Declined->value,
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');

        $this->assertSame(
            1,
            RfqInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->where('vendor_id', $vendor->id)
                ->count()
        );
    }

    public function test_create_and_resend_ensure_portal_token_metadata_without_exposing_raw_token(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);

        $createdId = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.portalAccess.hasToken', true)
            ->assertJsonMissing(['token'])
            ->json('data.0.id');

        $created = RfqInvitation::query()->findOrFail((int) $createdId);
        $this->assertNotNull($created->portal_token_hash);
        $this->assertNotNull($created->portal_token_expires_at);

        $created->forceFill(['portal_token_hash' => null, 'portal_token_expires_at' => null])->save();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$created->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.portalAccess.hasToken', true)
            ->assertJsonMissing(['token']);

        $this->assertNotNull($created->refresh()->portal_token_hash);
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
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function vendor(Tenant $tenant, array $overrides = []): Vendor
    {
        $vendor = Vendor::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Vendor',
            'status' => 'active',
            'category' => 'IT Hardware',
            'risk_rating' => 'low',
            'metadata' => [
                'contactName' => 'Vendor Contact',
                'contactEmail' => 'vendor@example.test',
            ],
        ], $overrides));

        $vendor->forceFill([
            'name' => $overrides['name'] ?? 'Vendor ' . $vendor->id,
            'metadata' => array_merge([
                'contactName' => 'Vendor Contact ' . $vendor->id,
                'contactEmail' => 'vendor-' . $vendor->id . '@example.test',
            ], $overrides['metadata'] ?? []),
        ])->save();

        return $vendor->refresh();
    }

    private function draftRfq(Tenant $tenant, User $requester, User $buyer): Rfq
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ',
            'title' => 'Laptop refresh',
            'status' => RequisitionStatus::Approved,
            'currency' => 'USD',
        ]);

        $requisition->forceFill([
            'number' => 'REQ-' . $requisition->id,
        ])->save();

        $review = SourcingIntakeReview::query()->create([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'assigned_buyer_id' => $buyer->id,
            'status' => SourcingIntakeStatus::ReadyForRfq,
            'sourcing_path' => SourcingPath::NeedsRfq,
            'decision_reason' => 'Competitive sourcing required.',
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'sourcing_intake_review_id' => $review->id,
            'requisition_id' => $requisition->id,
            'number' => 'RFQ',
            'title' => 'Laptop refresh RFQ',
            'status' => RfqStatus::Draft,
            'required_documents' => [],
            'line_items' => [],
        ]);

        $rfq->forceFill([
            'number' => 'RFQ-' . $rfq->id,
        ])->save();

        return $rfq->refresh();
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
