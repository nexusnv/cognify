<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RfqDraftApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_or_reveal_draft_rfq_from_ready_intake_review(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->readyReview($tenant, $requester, $buyer);

        $first = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/rfq")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.intakeReview.id', (string) $review->id)
            ->assertJsonPath('data.requisition.id', (string) $review->requisition_id)
            ->assertJsonPath('data.permissions.canUpdate', true)
            ->assertJsonPath('data.permissions.canCancel', true)
            ->json('data.id');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$first}")
            ->assertOk()
            ->assertJsonPath('data.auditSummary.0.action', 'rfq.draft_created');

        $second = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/rfq")
            ->assertOk()
            ->assertJsonPath('data.id', $first)
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Rfq::query()->where('sourcing_intake_review_id', $review->id)->count());
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq.draft_created',
        ]);
    }

    public function test_create_requires_ready_for_rfq_intake_review(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->review($tenant, $requester, $buyer, SourcingIntakeStatus::InReview);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/rfq")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_requester_cannot_create_edit_or_cancel_rfq_drafts(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->readyReview($tenant, $requester, $buyer);
        $rfq = $this->createDraftRfq($tenant, $review);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/rfq")
            ->assertForbidden();

        $this->actingAsTenant($tenant, $requester)
            ->patchJson("/api/rfqs/{$rfq->id}", ['title' => 'Requester edit'])
            ->assertForbidden();

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/rfqs/{$rfq->id}/cancel", ['cancelReason' => 'Not allowed'])
            ->assertForbidden();
    }

    public function test_rfq_drafts_are_tenant_scoped(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');
        [, $otherBuyer] = $this->tenantUser('buyer', $otherTenant);
        $otherReview = $this->readyReview($otherTenant, $otherRequester, $otherBuyer);
        $otherRfq = $this->createDraftRfq($otherTenant, $otherReview);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$otherRfq->id}")
            ->assertNotFound();

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$otherRfq->id}", [
                'title' => 'Cross-tenant edit attempt',
            ])
            ->assertNotFound();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$otherRfq->id}/cancel", [
                'cancelReason' => 'Cross-tenant cancel attempt',
            ])
            ->assertNotFound();
    }

    public function test_buyer_can_show_update_and_cancel_draft_rfq(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->readyReview($tenant, $requester, $buyer);
        $rfq = $this->createDraftRfq($tenant, $review);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $rfq->id)
            ->assertJsonPath('data.status', 'draft');

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}", [
                'title' => 'Updated RFQ title',
                'scopeSummary' => 'Supply and deliver laptops for field teams.',
                'responseDueAt' => '2026-06-30T17:00:00Z',
                'responseInstructions' => 'Submit pricing and warranty details.',
                'requiredDocuments' => [
                    ['key' => 'company_profile', 'label' => 'Company profile', 'required' => true],
                    ['key' => 'warranty_terms', 'label' => 'Warranty terms', 'required' => true],
                ],
                'lineItems' => [
                    ['description' => 'Laptop', 'quantity' => 10, 'unit' => 'each', 'notes' => '16GB RAM minimum'],
                ],
                'evaluationNotes' => 'Compare warranty and delivery.',
                'internalNotes' => 'Target three suppliers next slice.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated RFQ title')
            ->assertJsonPath('data.requiredDocuments.0.key', 'company_profile')
            ->assertJsonPath('data.lineItems.0.description', 'Laptop');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}")
            ->assertOk()
            ->assertJsonPath('data.auditSummary.0.action', 'rfq.draft_updated');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq.draft_updated',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/cancel", ['cancelReason' => 'Sourcing consolidated into a project RFQ.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.permissions.canUpdate', false);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}")
            ->assertOk()
            ->assertJsonPath('data.auditSummary.0.action', 'rfq.draft_cancelled');
    }

    public function test_cancel_requires_reason_and_cancelled_rfq_cannot_be_edited(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->createDraftRfq($tenant, $this->readyReview($tenant, $requester, $buyer));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/cancel", ['cancelReason' => 'No longer required.'])
            ->assertOk()
            ->assertJsonPath('data.cancelReason', 'No longer required.');

        $this->assertDatabaseHas('rfqs', [
            'id' => $rfq->id,
            'cancel_reason' => 'No longer required.',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}", ['title' => 'Should not change'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_login_and_logout_gates_rfq_endpoints_through_session_auth(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $buyer->forceFill([
            'email' => 'rfq-buyer@example.com',
            'password' => Hash::make('secret123'),
        ])->save();
        $review = $this->readyReview($tenant, $requester, $buyer);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'rfq-buyer@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $createdId = (string) $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/rfq")
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$createdId}")
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$createdId}")
            ->assertUnauthorized();
    }

    public function test_unauthenticated_requests_cannot_access_rfq_endpoints(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->readyReview($tenant, $requester, $buyer);

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/rfq")
            ->assertUnauthorized();
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

    private function readyReview(Tenant $tenant, User $requester, User $buyer): SourcingIntakeReview
    {
        return $this->review($tenant, $requester, $buyer, SourcingIntakeStatus::ReadyForRfq);
    }

    private function review(Tenant $tenant, User $requester, User $buyer, SourcingIntakeStatus $status): SourcingIntakeReview
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Field laptop refresh',
            'business_justification' => 'Replace aging laptops.',
            'needed_by_date' => '2026-07-15',
            'status' => RequisitionStatus::Approved,
            'currency' => 'MYR',
            'submitted_at' => now(),
        ]);

        $requisition->lineItems()->create([
            'name' => 'Developer laptop',
            'quantity' => '2.0000',
            'unit_of_measure' => 'each',
            'estimated_unit_price' => '1800.00',
            'currency' => 'USD',
        ]);

        $project = ProcurementProject::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $buyer->id,
            'number' => 'PRJ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'name' => 'Field enablement',
            'status' => 'active',
        ]);

        return SourcingIntakeReview::query()->create([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'project_id' => $project->id,
            'assigned_buyer_id' => $buyer->id,
            'status' => $status,
            'sourcing_path' => $status === SourcingIntakeStatus::ReadyForRfq ? SourcingPath::NeedsRfq : null,
            'decision_reason' => $status === SourcingIntakeStatus::ReadyForRfq ? 'Competitive sourcing required.' : null,
        ]);
    }

    private function createDraftRfq(Tenant $tenant, SourcingIntakeReview $review): Rfq
    {
        return Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'sourcing_intake_review_id' => $review->id,
            'project_id' => $review->project_id,
            'requisition_id' => $review->requisition_id,
            'number' => 'RFQ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Field laptop refresh RFQ',
            'status' => 'draft',
        ]);
    }
}
