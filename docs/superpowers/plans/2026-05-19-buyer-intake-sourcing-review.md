# Buyer Intake Sourcing Review Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first P1 Epic 5 slice: a buyer/admin sourcing intake queue and intake review workspace that records buyer triage decisions without creating RFQs or vendor invitations.

**Architecture:** The Laravel `Quotation` domain owns durable sourcing intake behavior through a `SourcingIntakeReview` model, state enums, policies, requests, resources, controllers, and actions. The Next.js web app owns Cognify-specific sourcing UI under `apps/web/features/sourcing`, calling generated OpenAPI endpoints through `@cognify/api-client`; shared packages remain primitive or generated-contract only.

**Tech Stack:** Laravel 12, Sanctum, Eloquent, OpenAPI/Orval, Next.js App Router, React, TanStack Query, MSW, Vitest, Testing Library, shadcn/Radix primitives through `packages/ui`.

---

## Source Documents

- Spec: `docs/superpowers/specs/2026-05-19-buyer-intake-sourcing-review-design.md`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Architecture: `ARCHITECTURE.md`
- Runbook: `docs/05-runbooks/feature-development.md`
- Agent guidance: `AGENTS.md`

## Scope Boundaries

Implement:

- Explicit buyer/admin create-or-reveal intake review for eligible `submitted` or `approved` requisitions.
- Buyer/admin list, detail, claim, reassign, update, decision, and close flows.
- Intake states: `open`, `in_review`, `clarification_requested`, `ready_for_rfq`, `direct_award_recorded`, `closed`.
- Sourcing paths: `needs_rfq`, `needs_clarification`, `direct_award`, `no_sourcing_required`.
- Audit events and in-app notifications defined in the spec.
- OpenAPI-generated client usage.
- Buyer intake queue and detail workspace.

Do not implement:

- RFQ creation, RFQ workspace, vendor invitations, vendor portal, quotation capture, comparison, scoring, awards, or PO handoff.
- AI recommendations or policy engine behavior.
- Full vendor master management.

## File Map

Backend create:

- `apps/api/database/migrations/2026_05_19_000000_create_sourcing_intake_reviews_table.php`
- `apps/api/Domains/Quotation/States/SourcingIntakeStatus.php`
- `apps/api/Domains/Quotation/States/SourcingPath.php`
- `apps/api/Domains/Quotation/Models/SourcingIntakeReview.php`
- `apps/api/Domains/Quotation/Policies/SourcingIntakeReviewPolicy.php`
- `apps/api/Domains/Quotation/Actions/CreateOrRevealSourcingIntakeReview.php`
- `apps/api/Domains/Quotation/Actions/ClaimSourcingIntakeReview.php`
- `apps/api/Domains/Quotation/Actions/ReassignSourcingIntakeReview.php`
- `apps/api/Domains/Quotation/Actions/UpdateSourcingIntakeReview.php`
- `apps/api/Domains/Quotation/Actions/RecordSourcingIntakeDecision.php`
- `apps/api/Domains/Quotation/Actions/CloseSourcingIntakeReview.php`
- `apps/api/Domains/Quotation/Http/Controllers/SourcingIntakeReviewController.php`
- `apps/api/Domains/Quotation/Http/Requests/ListSourcingIntakeReviewsRequest.php`
- `apps/api/Domains/Quotation/Http/Requests/ReassignSourcingIntakeReviewRequest.php`
- `apps/api/Domains/Quotation/Http/Requests/UpdateSourcingIntakeReviewRequest.php`
- `apps/api/Domains/Quotation/Http/Requests/RecordSourcingIntakeDecisionRequest.php`
- `apps/api/Domains/Quotation/Http/Requests/CloseSourcingIntakeReviewRequest.php`
- `apps/api/Domains/Quotation/Http/Resources/SourcingIntakeReviewResource.php`
- `apps/api/tests/Feature/SourcingIntakeApiTest.php`

Backend modify:

- `apps/api/routes/api.php`
- `apps/api/app/Providers/AuthServiceProvider.php` or the current policy registration location if policies are auto-discovered differently.
- `apps/api/app/Auth/Permissions/TenantPermissionResolver.php`
- `apps/api/app/Notifications/NotificationPreferenceDefaults.php` if notification type defaults need registration.
- `apps/api/storage/openapi/openapi.json`
- `packages/api-client/src/generated/**` after contract generation.
- `packages/api-client/src/index.ts` or existing endpoint/schema exports if generation requires manual export glue.

Frontend create:

- `apps/web/app/(workspace)/sourcing/intake/page.tsx`
- `apps/web/app/(workspace)/sourcing/intake/[reviewId]/page.tsx`
- `apps/web/features/sourcing/api/sourcing-api.ts`
- `apps/web/features/sourcing/components/sourcing-intake-status-badge.tsx`
- `apps/web/features/sourcing/components/sourcing-intake-decision-dialog.tsx`
- `apps/web/features/sourcing/components/sourcing-intake-review-form.tsx`
- `apps/web/features/sourcing/hooks/use-sourcing-intake-reviews.ts`
- `apps/web/features/sourcing/hooks/use-sourcing-intake-review.ts`
- `apps/web/features/sourcing/hooks/use-sourcing-intake-actions.ts`
- `apps/web/features/sourcing/mocks/sourcing-fixtures.ts`
- `apps/web/features/sourcing/mocks/sourcing-handlers.ts`
- `apps/web/features/sourcing/schemas/sourcing-intake-schema.ts`
- `apps/web/features/sourcing/tables/sourcing-intake-table.tsx`
- `apps/web/features/sourcing/tests/sourcing-intake-workflow.test.tsx`
- `apps/web/features/sourcing/types/sourcing-view-model.ts`
- `apps/web/features/sourcing/workflows/sourcing-intake-list-page.tsx`
- `apps/web/features/sourcing/workflows/sourcing-intake-detail-page.tsx`

Frontend modify:

- `apps/web/components/shell/shell-route-config.ts`
- `apps/web/components/shell/shell-route-config.test.tsx`
- `apps/web/features/identity/types/identity-view-model.ts`
- `apps/web/tests/msw/handlers.ts` or the current central MSW handler registry.
- `apps/web/features/search/search-contract.ts` only if adding real sourcing links changes search behavior; keep this out unless needed.

## Task 1: Backend Failing Contract Tests

**Files:**

- Create: `apps/api/tests/Feature/SourcingIntakeApiTest.php`

- [ ] **Step 1: Create the feature test file with workflow coverage**

Create `apps/api/tests/Feature/SourcingIntakeApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SourcingIntakeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_or_reveal_intake_review_for_submitted_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->createRequisition($tenant, $requester, [
            'status' => RequisitionStatus::Submitted,
        ]);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/sourcing-intake");

        $response->assertCreated()
            ->assertJsonPath('data.requisition.id', (string) $requisition->id)
            ->assertJsonPath('data.status', SourcingIntakeStatus::Open->value)
            ->assertJsonPath('data.permissions.canClaim', true)
            ->assertJsonPath('data.permissions.canUpdate', true)
            ->assertJsonPath('data.permissions.canRecordDecision', false);

        $this->assertDatabaseHas('sourcing_intake_reviews', [
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'status' => SourcingIntakeStatus::Open->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'sourcing_intake.created',
        ]);
    }

    public function test_create_or_reveal_is_idempotent_for_active_review(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->createRequisition($tenant, $requester, [
            'status' => RequisitionStatus::Approved,
        ]);

        $first = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/sourcing-intake")
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/sourcing-intake")
            ->assertOk()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, SourcingIntakeReview::query()->where('requisition_id', $requisition->id)->count());
    }

    public function test_requester_cannot_manage_sourcing_intake(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester, [
            'status' => RequisitionStatus::Submitted,
        ]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/sourcing-intake")
            ->assertForbidden();
    }

    public function test_intake_reviews_are_tenant_scoped(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');
        [, $otherBuyer] = $this->tenantUser('buyer', $otherTenant);

        $visible = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester));
        $hidden = $this->createReview($otherTenant, $otherBuyer, $this->createRequisition($otherTenant, $otherRequester));

        $response = $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/sourcing/intake-reviews');

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $visible->id)
            ->assertJsonMissing(['id' => (string) $hidden->id]);
    }

    public function test_buyer_can_claim_update_and_record_rfq_ready_decision(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->createReview($tenant, null, $this->createRequisition($tenant, $requester));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.status', SourcingIntakeStatus::InReview->value)
            ->assertJsonPath('data.assignedBuyer.id', (string) $buyer->id);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/sourcing/intake-reviews/{$review->id}", [
                'category' => 'IT Hardware',
                'subcategory' => 'Laptops',
                'urgency' => 'standard',
                'complexity' => 'medium',
                'targetDecisionDate' => '2026-06-15',
                'checklist' => [
                    ['key' => 'specification_complete', 'label' => 'Specification complete', 'complete' => true],
                    ['key' => 'budget_clear', 'label' => 'Budget clear', 'complete' => true],
                ],
                'internalNotes' => 'Ready for competitive sourcing.',
            ])
            ->assertOk()
            ->assertJsonPath('data.category', 'IT Hardware')
            ->assertJsonPath('data.checklist.0.complete', true);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/decision", [
                'sourcingPath' => SourcingPath::NeedsRfq->value,
                'decisionReason' => 'Competitive quotes required for value and delivery comparison.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SourcingIntakeStatus::ReadyForRfq->value)
            ->assertJsonPath('data.sourcingPath', SourcingPath::NeedsRfq->value)
            ->assertJsonPath('data.permissions.canCreateRfq', false);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'sourcing_intake.ready_for_rfq',
        ]);
    }

    public function test_clarification_decision_updates_requisition_correction_flow(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/decision", [
                'sourcingPath' => SourcingPath::NeedsClarification->value,
                'decisionReason' => 'Missing technical specifications.',
                'clarificationMessage' => 'Please add device specifications and warranty requirements.',
                'clarificationFields' => ['lineItems', 'businessJustification'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SourcingIntakeStatus::ClarificationRequested->value)
            ->assertJsonPath('data.requisition.status', RequisitionStatus::ChangesRequested->value);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $requester->id,
            'actor_id' => $buyer->id,
            'type' => 'requisition.changes_requested',
        ]);
    }

    public function test_invalid_transition_returns_conflict(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester), [
            'status' => SourcingIntakeStatus::ReadyForRfq,
            'sourcing_path' => SourcingPath::NeedsRfq,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/sourcing/intake-reviews/{$review->id}", [
                'category' => 'Changed after decision',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_assigned_buyer_must_belong_to_current_tenant(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $otherTenantBuyer] = $this->tenantUser('buyer');
        $review = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/reassign", [
                'buyerId' => (string) $otherTenantBuyer->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_close_requires_reason_and_records_no_sourcing_path(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/close")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/close", [
                'sourcingPath' => SourcingPath::NoSourcingRequired->value,
                'decisionReason' => 'Request was consolidated into an existing sourcing package.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SourcingIntakeStatus::Closed->value)
            ->assertJsonPath('data.sourcingPath', SourcingPath::NoSourcingRequired->value);
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
        $user->tenants()->attach($tenant->id, ['role' => $role]);

        return [$tenant, $user];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createRequisition(Tenant $tenant, User $requester, array $overrides = []): Requisition
    {
        $projectId = $overrides['project_id'] ?? null;

        if ($projectId === 'make-project') {
            $project = ProcurementProject::query()->create([
                'tenant_id' => $tenant->id,
                'owner_id' => $requester->id,
                'number' => 'PRJ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                'name' => 'Office refresh',
                'status' => 'active',
                'budget_amount' => '25000.00',
                'currency' => 'MYR',
            ]);
            $projectId = $project->id;
        }

        return Requisition::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'project_id' => $projectId,
            'number' => 'REQ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Laptop refresh',
            'business_justification' => 'Replace aging laptops.',
            'needed_by_date' => '2026-07-15',
            'status' => RequisitionStatus::Submitted,
            'currency' => 'MYR',
            'submitted_at' => now(),
        ], array_diff_key($overrides, ['project_id' => true])));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createReview(Tenant $tenant, ?User $buyer, Requisition $requisition, array $overrides = []): SourcingIntakeReview
    {
        return SourcingIntakeReview::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'project_id' => $requisition->project_id,
            'assigned_buyer_id' => $buyer?->id,
            'status' => $buyer === null ? SourcingIntakeStatus::Open : SourcingIntakeStatus::InReview,
            'sourcing_path' => null,
            'checklist' => [],
        ], $overrides));
    }
}
```

- [ ] **Step 2: Run the new test file and verify it fails for missing classes/routes**

Run:

```bash
cd apps/api && php artisan test tests/Feature/SourcingIntakeApiTest.php
```

Expected: FAIL with missing `Domains\Quotation\Models\SourcingIntakeReview`, missing state enums, or 404 routes. Do not implement production code before seeing this failure.

## Task 2: Persistence, States, Model, Policy

**Files:**

- Create: `apps/api/database/migrations/2026_05_19_000000_create_sourcing_intake_reviews_table.php`
- Create: `apps/api/Domains/Quotation/States/SourcingIntakeStatus.php`
- Create: `apps/api/Domains/Quotation/States/SourcingPath.php`
- Create: `apps/api/Domains/Quotation/Models/SourcingIntakeReview.php`
- Create: `apps/api/Domains/Quotation/Policies/SourcingIntakeReviewPolicy.php`
- Modify: `apps/api/app/Providers/AuthServiceProvider.php` if explicit policy registration is used.

- [ ] **Step 1: Add migration**

Create `apps/api/database/migrations/2026_05_19_000000_create_sourcing_intake_reviews_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sourcing_intake_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('procurement_projects')->nullOnDelete();
            $table->foreignId('assigned_buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('sourcing_path')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('urgency')->nullable();
            $table->string('complexity')->nullable();
            $table->date('target_decision_date')->nullable();
            $table->json('checklist')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('decision_reason')->nullable();
            $table->text('clarification_message')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'requisition_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_buyer_id', 'status']);
            $table->index(['tenant_id', 'target_decision_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_intake_reviews');
    }
};
```

- [ ] **Step 2: Add state enums**

Create `apps/api/Domains/Quotation/States/SourcingIntakeStatus.php`:

```php
<?php

namespace Domains\Quotation\States;

enum SourcingIntakeStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case ClarificationRequested = 'clarification_requested';
    case ReadyForRfq = 'ready_for_rfq';
    case DirectAwardRecorded = 'direct_award_recorded';
    case Closed = 'closed';

    public function isTerminalForEditing(): bool
    {
        return in_array($this, [self::ReadyForRfq, self::DirectAwardRecorded, self::Closed], true);
    }
}
```

Create `apps/api/Domains/Quotation/States/SourcingPath.php`:

```php
<?php

namespace Domains\Quotation\States;

enum SourcingPath: string
{
    case NeedsRfq = 'needs_rfq';
    case NeedsClarification = 'needs_clarification';
    case DirectAward = 'direct_award';
    case NoSourcingRequired = 'no_sourcing_required';
}
```

- [ ] **Step 3: Add model**

Create `apps/api/Domains/Quotation/Models/SourcingIntakeReview.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SourcingIntakeReview extends Model
{
    protected $fillable = [
        'tenant_id',
        'requisition_id',
        'project_id',
        'assigned_buyer_id',
        'status',
        'sourcing_path',
        'category',
        'subcategory',
        'urgency',
        'complexity',
        'target_decision_date',
        'checklist',
        'internal_notes',
        'decision_reason',
        'clarification_message',
        'claimed_at',
        'decided_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'target_decision_date' => 'date',
            'claimed_at' => 'datetime',
            'decided_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            DB::transaction(function () use ($review): void {
                $requisition = Requisition::query()
                    ->whereKey($review->requisition_id)
                    ->lockForUpdate()
                    ->first();

                if ($requisition !== null && (int) $requisition->tenant_id !== (int) $review->tenant_id) {
                    throw new InvalidArgumentException('Sourcing intake requisition must belong to the same tenant.');
                }

                if ($review->project_id !== null) {
                    $project = ProcurementProject::query()
                        ->whereKey($review->project_id)
                        ->lockForUpdate()
                        ->first();

                    if ($project !== null && (int) $project->tenant_id !== (int) $review->tenant_id) {
                        throw new InvalidArgumentException('Sourcing intake project must belong to the same tenant.');
                    }
                }

                if ($review->assigned_buyer_id !== null) {
                    $buyerInTenant = Tenant::query()
                        ->whereKey($review->tenant_id)
                        ->whereHas('users', fn ($query) => $query->whereKey($review->assigned_buyer_id))
                        ->exists();

                    if (! $buyerInTenant) {
                        throw new InvalidArgumentException('Assigned buyer must belong to the same tenant.');
                    }
                }
            });
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Requisition, $this> */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /** @return BelongsTo<ProcurementProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'project_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBuyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_buyer_id');
    }
}
```

- [ ] **Step 4: Add policy**

Create `apps/api/Domains/Quotation/Policies/SourcingIntakeReviewPolicy.php`:

```php
<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\SourcingIntakeReview;

class SourcingIntakeReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageSourcing($user);
    }

    public function view(User $user, SourcingIntakeReview $review): bool
    {
        return $this->canManageSourcing($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageSourcing($user);
    }

    public function update(User $user, SourcingIntakeReview $review): bool
    {
        return $this->canManageSourcing($user);
    }

    public function decide(User $user, SourcingIntakeReview $review): bool
    {
        return $this->canManageSourcing($user);
    }

    public function reassign(User $user, SourcingIntakeReview $review): bool
    {
        return $this->canManageSourcing($user);
    }

    private function canManageSourcing(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }
}
```

- [ ] **Step 5: Register policy if required**

If `apps/api/app/Providers/AuthServiceProvider.php` contains a `$policies` array, add:

```php
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\Policies\SourcingIntakeReviewPolicy;

protected $policies = [
    SourcingIntakeReview::class => SourcingIntakeReviewPolicy::class,
];
```

If Laravel auto-discovery is already used and existing domain policies are not registered manually, skip this edit.

- [ ] **Step 6: Run the focused test again**

Run:

```bash
cd apps/api && php artisan test tests/Feature/SourcingIntakeApiTest.php
```

Expected: still FAIL, now because routes/controllers/actions do not exist.

## Task 3: Backend Actions, Requests, Resources, Controllers, Routes

**Files:**

- Create all backend action/request/resource/controller files listed in the File Map.
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add request validation classes**

Create `apps/api/Domains/Quotation/Http/Requests/ListSourcingIntakeReviewsRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\SourcingIntakeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSourcingIntakeReviewsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'preset' => ['sometimes', 'string', Rule::in(['unassigned', 'mine', 'needs_clarification', 'ready_for_rfq', 'closed'])],
            'status' => ['sometimes', 'string', Rule::enum(SourcingIntakeStatus::class)],
            'assignedBuyer' => ['sometimes', 'string'],
            'department' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in(['updated_desc', 'target_date_asc', 'needed_by_asc', 'amount_desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
```

Create `ReassignSourcingIntakeReviewRequest.php`, `UpdateSourcingIntakeReviewRequest.php`, `RecordSourcingIntakeDecisionRequest.php`, and `CloseSourcingIntakeReviewRequest.php` with these rule bodies:

```php
// ReassignSourcingIntakeReviewRequest::rules()
return [
    'buyerId' => ['required', 'integer', 'exists:users,id'],
];

// UpdateSourcingIntakeReviewRequest::rules()
return [
    'category' => ['sometimes', 'nullable', 'string', 'max:255'],
    'subcategory' => ['sometimes', 'nullable', 'string', 'max:255'],
    'urgency' => ['sometimes', 'nullable', Rule::in(['low', 'standard', 'urgent'])],
    'complexity' => ['sometimes', 'nullable', Rule::in(['low', 'medium', 'high'])],
    'targetDecisionDate' => ['sometimes', 'nullable', 'date'],
    'checklist' => ['sometimes', 'array'],
    'checklist.*.key' => ['required_with:checklist', 'string', 'max:100'],
    'checklist.*.label' => ['required_with:checklist', 'string', 'max:255'],
    'checklist.*.complete' => ['required_with:checklist', 'boolean'],
    'internalNotes' => ['sometimes', 'nullable', 'string', 'max:5000'],
];

// RecordSourcingIntakeDecisionRequest::rules()
return [
    'sourcingPath' => ['required', Rule::enum(SourcingPath::class)],
    'decisionReason' => ['required', 'string', 'min:10', 'max:5000'],
    'clarificationMessage' => ['required_if:sourcingPath,needs_clarification', 'nullable', 'string', 'max:5000'],
    'clarificationFields' => ['sometimes', 'array'],
    'clarificationFields.*' => ['string', 'max:100'],
];

// CloseSourcingIntakeReviewRequest::rules()
return [
    'sourcingPath' => ['required', Rule::in([SourcingPath::NoSourcingRequired->value])],
    'decisionReason' => ['required', 'string', 'min:10', 'max:5000'],
];
```

Include the correct namespace and `use Illuminate\Validation\Rule;` plus `use Domains\Quotation\States\SourcingPath;` where needed.

- [ ] **Step 2: Add resource**

Create `apps/api/Domains/Quotation/Http/Resources/SourcingIntakeReviewResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use App\Http\Resources\UserSummaryResource;
use Domains\Project\Http\Resources\ProjectRequisitionResource;
use Domains\Quotation\States\SourcingIntakeStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SourcingIntakeReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = SourcingIntakeStatus::from($this->status);
        $canEdit = ! $status->isTerminalForEditing();

        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'status' => $this->status,
            'sourcingPath' => $this->sourcing_path,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'urgency' => $this->urgency,
            'complexity' => $this->complexity,
            'targetDecisionDate' => $this->target_decision_date?->toDateString(),
            'checklist' => $this->checklist ?? [],
            'internalNotes' => $this->internal_notes,
            'decisionReason' => $this->decision_reason,
            'clarificationMessage' => $this->clarification_message,
            'claimedAt' => $this->claimed_at?->toISOString(),
            'decidedAt' => $this->decided_at?->toISOString(),
            'closedAt' => $this->closed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'assignedBuyer' => $this->whenLoaded('assignedBuyer', fn () => $this->assignedBuyer ? new UserSummaryResource($this->assignedBuyer) : null),
            'requisition' => $this->whenLoaded('requisition', fn () => new ProjectRequisitionResource($this->requisition)),
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => (string) $this->project->id,
                'number' => $this->project->number,
                'name' => $this->project->name,
                'status' => $this->project->status,
            ] : null),
            'permissions' => [
                'canClaim' => $this->assigned_buyer_id === null && $status === SourcingIntakeStatus::Open,
                'canReassign' => true,
                'canUpdate' => $canEdit,
                'canRecordDecision' => $status === SourcingIntakeStatus::InReview,
                'canClose' => in_array($status, [
                    SourcingIntakeStatus::InReview,
                    SourcingIntakeStatus::ClarificationRequested,
                    SourcingIntakeStatus::ReadyForRfq,
                    SourcingIntakeStatus::DirectAwardRecorded,
                ], true),
                'canCreateRfq' => false,
            ],
        ];
    }
}
```

If `ProjectRequisitionResource` is too narrow for this use, create a small private requisition summary method in this resource instead of expanding a shared resource.

- [ ] **Step 3: Add actions**

Each action should load `requisition`, `project`, and `assignedBuyer` before returning.

Implementation rules:

```php
// CreateOrRevealSourcingIntakeReview
// - authorize create through policy in controller before calling
// - lock the requisition by tenant
// - allow only Submitted or Approved
// - firstOrCreate by tenant_id + requisition_id
// - if existing active review exists, return it without new audit event
// - on new review, status=open, checklist=[], project_id from requisition
// - audit sourcing_intake.created

// ClaimSourcingIntakeReview
// - allow only open reviews
// - set assigned_buyer_id=current actor, claimed_at=now, status=in_review
// - audit sourcing_intake.claimed

// ReassignSourcingIntakeReview
// - validate buyer belongs to tenant in action even though request validates exists
// - reject terminal closed reviews with conflict
// - set assigned_buyer_id, claimed_at if absent, status=in_review when open
// - audit sourcing_intake.reassigned
// - notify assigned buyer with type sourcing_intake.assigned

// UpdateSourcingIntakeReview
// - reject ready_for_rfq, direct_award_recorded, closed with conflict
// - patch only provided fields
// - normalize checklist to array of key/label/complete
// - audit sourcing_intake.updated

// RecordSourcingIntakeDecision
// - allow only in_review
// - needs_rfq => status ready_for_rfq, sourcing_path needs_rfq, decided_at now, audit sourcing_intake.ready_for_rfq, notify assigned buyer
// - direct_award => status direct_award_recorded, sourcing_path direct_award, decided_at now, audit sourcing_intake.direct_award_recorded
// - needs_clarification => status clarification_requested, call Domains\Requisition\Actions\RequestRequisitionChanges with clarification message/fields, audit sourcing_intake.clarification_requested
// - no_sourcing_required is handled by close endpoint, reject it here with 422 or conflict

// CloseSourcingIntakeReview
// - allow in_review, clarification_requested, ready_for_rfq, direct_award_recorded
// - set status closed, sourcing_path no_sourcing_required only when provided, decision_reason, closed_at now
// - audit sourcing_intake.closed
```

Use `App\Audit\AuditRecorder` and `App\Notifications\NotificationRecorder` consistently with existing requisition/project actions. Use `Symfony\Component\HttpKernel\Exception\ConflictHttpException` for invalid transitions.

- [ ] **Step 4: Add controller and routes**

Create `apps/api/Domains/Quotation/Http/Controllers/SourcingIntakeReviewController.php` with methods:

```php
index(ListSourcingIntakeReviewsRequest $request)
show(Request $request, CurrentTenant $currentTenant, SourcingIntakeReview $review)
storeForRequisition(Request $request, CurrentTenant $currentTenant, Requisition $requisition, CreateOrRevealSourcingIntakeReview $action)
claim(Request $request, CurrentTenant $currentTenant, SourcingIntakeReview $review, ClaimSourcingIntakeReview $action)
reassign(ReassignSourcingIntakeReviewRequest $request, CurrentTenant $currentTenant, SourcingIntakeReview $review, ReassignSourcingIntakeReview $action)
update(UpdateSourcingIntakeReviewRequest $request, CurrentTenant $currentTenant, SourcingIntakeReview $review, UpdateSourcingIntakeReview $action)
decision(RecordSourcingIntakeDecisionRequest $request, CurrentTenant $currentTenant, SourcingIntakeReview $review, RecordSourcingIntakeDecision $action)
close(CloseSourcingIntakeReviewRequest $request, CurrentTenant $currentTenant, SourcingIntakeReview $review, CloseSourcingIntakeReview $action)
```

Route model lookup must additionally check `tenant_id === currentTenant->id`; do this through a private `findTenantReview()` helper if implicit binding does not enforce tenant.

Modify `apps/api/routes/api.php` inside the `ResolveCurrentTenant` group:

```php
use Domains\Quotation\Http\Controllers\SourcingIntakeReviewController;

Route::get('/sourcing/intake-reviews', [SourcingIntakeReviewController::class, 'index']);
Route::get('/sourcing/intake-reviews/{review}', [SourcingIntakeReviewController::class, 'show']);
Route::post('/requisitions/{requisition}/sourcing-intake', [SourcingIntakeReviewController::class, 'storeForRequisition']);
Route::post('/sourcing/intake-reviews/{review}/claim', [SourcingIntakeReviewController::class, 'claim']);
Route::post('/sourcing/intake-reviews/{review}/reassign', [SourcingIntakeReviewController::class, 'reassign']);
Route::patch('/sourcing/intake-reviews/{review}', [SourcingIntakeReviewController::class, 'update']);
Route::post('/sourcing/intake-reviews/{review}/decision', [SourcingIntakeReviewController::class, 'decision']);
Route::post('/sourcing/intake-reviews/{review}/close', [SourcingIntakeReviewController::class, 'close']);
```

- [ ] **Step 5: Add listing query behavior**

In `index`, build a tenant-scoped query:

```php
$query = SourcingIntakeReview::query()
    ->with(['requisition.requester', 'requisition.lineItems', 'project', 'assignedBuyer'])
    ->where('tenant_id', $currentTenant->id)
    ->latest('updated_at');
```

Apply presets:

```php
match ($request->query('preset')) {
    'unassigned' => $query->whereNull('assigned_buyer_id'),
    'mine' => $query->where('assigned_buyer_id', $request->user()->id),
    'needs_clarification' => $query->where('status', SourcingIntakeStatus::ClarificationRequested),
    'ready_for_rfq' => $query->where('status', SourcingIntakeStatus::ReadyForRfq),
    'closed' => $query->whereIn('status', [SourcingIntakeStatus::Closed, SourcingIntakeStatus::DirectAwardRecorded]),
    default => null,
};
```

Return:

```php
return SourcingIntakeReviewResource::collection($reviews)->additional([
    'meta' => [
        'currentPage' => $reviews->currentPage(),
        'perPage' => $reviews->perPage(),
        'total' => $reviews->total(),
        'statusCounts' => $this->statusCounts($currentTenant->id, $request->user()->id),
    ],
]);
```

- [ ] **Step 6: Run backend tests**

Run:

```bash
cd apps/api && php artisan test tests/Feature/SourcingIntakeApiTest.php
```

Expected: PASS.

Then run:

```bash
cd apps/api && php artisan test tests/Feature/RequisitionApiTest.php tests/Feature/ProcurementProjectApiTest.php
```

Expected: PASS. Fix regressions before moving to OpenAPI.

## Task 4: Permissions, OpenAPI, Generated Client

**Files:**

- Modify: `apps/api/app/Auth/Permissions/TenantPermissionResolver.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated: `packages/api-client/src/generated/**`

- [ ] **Step 1: Add sourcing permission flags**

Modify every role branch in `TenantPermissionResolver::forRole()` to include:

```php
'canManageSourcingIntake' => true,
```

for buyer and admin, and:

```php
'canManageSourcingIntake' => false,
```

for requester, approver, and default. Keep existing permission keys unchanged.

- [ ] **Step 2: Add OpenAPI schemas and paths**

Update `apps/api/storage/openapi/openapi.json` with schemas:

- `SourcingIntakeReview`
- `SourcingIntakeReviewResponse`
- `SourcingIntakeReviewListResponse`
- `SourcingIntakeReviewPermissions`
- `SourcingIntakeReviewUpdateRequest`
- `SourcingIntakeReviewDecisionRequest`
- `SourcingIntakeReviewReassignRequest`
- `SourcingIntakeReviewCloseRequest`

Required enum values:

```json
{
  "SourcingIntakeStatus": ["open", "in_review", "clarification_requested", "ready_for_rfq", "direct_award_recorded", "closed"],
  "SourcingPath": ["needs_rfq", "needs_clarification", "direct_award", "no_sourcing_required"]
}
```

Add paths:

- `GET /api/sourcing/intake-reviews`
- `GET /api/sourcing/intake-reviews/{review}`
- `POST /api/requisitions/{requisition}/sourcing-intake`
- `POST /api/sourcing/intake-reviews/{review}/claim`
- `POST /api/sourcing/intake-reviews/{review}/reassign`
- `PATCH /api/sourcing/intake-reviews/{review}`
- `POST /api/sourcing/intake-reviews/{review}/decision`
- `POST /api/sourcing/intake-reviews/{review}/close`

Use existing error response refs for validation, forbidden, not found, and conflict.

- [ ] **Step 3: Regenerate client**

Run:

```bash
pnpm generate:api
```

Expected: Orval updates `packages/api-client/src/generated/**` with sourcing intake endpoints and schemas.

- [ ] **Step 4: Verify contract drift**

Run:

```bash
pnpm check:api-contract
```

Expected: PASS with no additional unreviewed OpenAPI drift. If generated files change again, inspect and keep the generated artifacts.

## Task 5: Web API, Types, Hooks, Schemas, MSW

**Files:**

- Create: `apps/web/features/sourcing/api/sourcing-api.ts`
- Create: `apps/web/features/sourcing/types/sourcing-view-model.ts`
- Create: `apps/web/features/sourcing/hooks/use-sourcing-intake-reviews.ts`
- Create: `apps/web/features/sourcing/hooks/use-sourcing-intake-review.ts`
- Create: `apps/web/features/sourcing/hooks/use-sourcing-intake-actions.ts`
- Create: `apps/web/features/sourcing/schemas/sourcing-intake-schema.ts`
- Create: `apps/web/features/sourcing/mocks/sourcing-fixtures.ts`
- Create: `apps/web/features/sourcing/mocks/sourcing-handlers.ts`
- Modify: `apps/web/tests/msw/handlers.ts`

- [ ] **Step 1: Add view model types**

Create `apps/web/features/sourcing/types/sourcing-view-model.ts`:

```ts
export type SourcingIntakeStatus =
  | "open"
  | "in_review"
  | "clarification_requested"
  | "ready_for_rfq"
  | "direct_award_recorded"
  | "closed";

export type SourcingPath =
  | "needs_rfq"
  | "needs_clarification"
  | "direct_award"
  | "no_sourcing_required";

export type SourcingIntakeChecklistItem = {
  key: string;
  label: string;
  complete: boolean;
};

export type SourcingIntakeReview = {
  id: string;
  tenantId: string;
  status: SourcingIntakeStatus;
  sourcingPath: SourcingPath | null;
  category: string | null;
  subcategory: string | null;
  urgency: "low" | "standard" | "urgent" | null;
  complexity: "low" | "medium" | "high" | null;
  targetDecisionDate: string | null;
  checklist: SourcingIntakeChecklistItem[];
  internalNotes: string | null;
  decisionReason: string | null;
  clarificationMessage: string | null;
  assignedBuyer: { id: string; name: string; email?: string | null } | null;
  requisition: {
    id: string;
    number: string;
    title: string;
    status: string;
    requester?: { id: string; name: string; email?: string | null } | null;
    department?: string | null;
    neededByDate?: string | null;
    estimatedTotal?: number | string | null;
    currency?: string | null;
  };
  project: { id: string; number: string; name: string; status: string } | null;
  permissions: {
    canClaim: boolean;
    canReassign: boolean;
    canUpdate: boolean;
    canRecordDecision: boolean;
    canClose: boolean;
    canCreateRfq: boolean;
  };
  claimedAt: string | null;
  decidedAt: string | null;
  closedAt: string | null;
  createdAt: string;
  updatedAt: string;
};

export type SourcingIntakeListResponse = {
  data: SourcingIntakeReview[];
  meta: {
    currentPage: number;
    perPage: number;
    total: number;
    statusCounts: Record<SourcingIntakeStatus | "unassigned" | "mine", number>;
  };
};

export type SourcingIntakeQuery = {
  preset?: string;
  status?: string;
  assignedBuyer?: string;
  department?: string;
  search?: string;
  sort?: string;
};
```

- [ ] **Step 2: Add Zod schemas**

Create `apps/web/features/sourcing/schemas/sourcing-intake-schema.ts`:

```ts
import { z } from "zod";

export const sourcingIntakeChecklistItemSchema = z.object({
  key: z.string().min(1),
  label: z.string().min(1),
  complete: z.boolean(),
});

export const sourcingIntakeReviewFormSchema = z.object({
  category: z.string().max(255).optional().nullable(),
  subcategory: z.string().max(255).optional().nullable(),
  urgency: z.enum(["low", "standard", "urgent"]).optional().nullable(),
  complexity: z.enum(["low", "medium", "high"]).optional().nullable(),
  targetDecisionDate: z.string().optional().nullable(),
  checklist: z.array(sourcingIntakeChecklistItemSchema),
  internalNotes: z.string().max(5000).optional().nullable(),
});

export const sourcingIntakeDecisionSchema = z.object({
  sourcingPath: z.enum(["needs_rfq", "needs_clarification", "direct_award", "no_sourcing_required"]),
  decisionReason: z.string().min(10, "Decision reason must be at least 10 characters"),
  clarificationMessage: z.string().optional().nullable(),
  clarificationFields: z.array(z.string()).optional(),
});

export type SourcingIntakeReviewFormValues = z.infer<typeof sourcingIntakeReviewFormSchema>;
export type SourcingIntakeDecisionValues = z.infer<typeof sourcingIntakeDecisionSchema>;
```

- [ ] **Step 3: Add API wrapper**

Create `apps/web/features/sourcing/api/sourcing-api.ts` using generated endpoint names from Orval. If Orval generated names differ, use the generated names but keep the wrapper function names below:

```ts
import {
  claimSourcingIntakeReview,
  closeSourcingIntakeReview,
  createRequisitionSourcingIntake,
  getSourcingIntakeReview,
  listSourcingIntakeReviews,
  reassignSourcingIntakeReview,
  recordSourcingIntakeReviewDecision,
  updateSourcingIntakeReview,
} from "@cognify/api-client/endpoints";
import type {
  ListSourcingIntakeReviewsParams,
  SourcingIntakeReview as ApiSourcingIntakeReview,
  SourcingIntakeReviewListResponse as ApiSourcingIntakeReviewListResponse,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";
import type {
  SourcingIntakeListResponse,
  SourcingIntakeQuery,
  SourcingIntakeReview,
} from "../types/sourcing-view-model";
import type {
  SourcingIntakeDecisionValues,
  SourcingIntakeReviewFormValues,
} from "../schemas/sourcing-intake-schema";

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;
  return { headers: { "X-Tenant-Id": tenantId } };
}

export async function fetchSourcingIntakeReviews(query: SourcingIntakeQuery = {}) {
  const params = Object.fromEntries(Object.entries(query).filter(([, value]) => value !== "")) as ListSourcingIntakeReviewsParams;
  const response = await listSourcingIntakeReviews(params, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapListResponse(response.data);
}

export async function fetchSourcingIntakeReview(reviewId: string) {
  const response = await getSourcingIntakeReview(reviewId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export async function createSourcingIntakeForRequisition(requisitionId: string) {
  const response = await createRequisitionSourcingIntake(requisitionId, undefined, withActiveTenantHeader());
  if (response.status !== 200 && response.status !== 201) throw response.data;
  return mapReview(response.data.data);
}

export async function claimIntakeReview(reviewId: string) {
  const response = await claimSourcingIntakeReview(reviewId, undefined, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export async function reassignIntakeReview(reviewId: string, buyerId: string) {
  const response = await reassignSourcingIntakeReview(reviewId, { buyerId }, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export async function saveIntakeReview(reviewId: string, values: SourcingIntakeReviewFormValues) {
  const response = await updateSourcingIntakeReview(reviewId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export async function decideIntakeReview(reviewId: string, values: SourcingIntakeDecisionValues) {
  const response =
    values.sourcingPath === "no_sourcing_required"
      ? await closeSourcingIntakeReview(reviewId, values, withActiveTenantHeader())
      : await recordSourcingIntakeReviewDecision(reviewId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export function mapReview(review: ApiSourcingIntakeReview): SourcingIntakeReview {
  return {
    id: review.id,
    tenantId: review.tenantId,
    status: review.status,
    sourcingPath: review.sourcingPath ?? null,
    category: review.category ?? null,
    subcategory: review.subcategory ?? null,
    urgency: review.urgency ?? null,
    complexity: review.complexity ?? null,
    targetDecisionDate: review.targetDecisionDate ?? null,
    checklist: review.checklist ?? [],
    internalNotes: review.internalNotes ?? null,
    decisionReason: review.decisionReason ?? null,
    clarificationMessage: review.clarificationMessage ?? null,
    assignedBuyer: review.assignedBuyer ?? null,
    requisition: review.requisition,
    project: review.project ?? null,
    permissions: review.permissions,
    claimedAt: review.claimedAt ?? null,
    decidedAt: review.decidedAt ?? null,
    closedAt: review.closedAt ?? null,
    createdAt: review.createdAt,
    updatedAt: review.updatedAt,
  };
}

export function mapListResponse(response: ApiSourcingIntakeReviewListResponse): SourcingIntakeListResponse {
  return {
    data: response.data.map(mapReview),
    meta: response.meta,
  };
}
```

- [ ] **Step 4: Add hooks**

Create hooks using TanStack Query:

```ts
// use-sourcing-intake-reviews.ts
import { useQuery } from "@tanstack/react-query";
import { fetchSourcingIntakeReviews } from "../api/sourcing-api";
import type { SourcingIntakeQuery } from "../types/sourcing-view-model";

export function useSourcingIntakeReviews(query: SourcingIntakeQuery = {}) {
  return useQuery({
    queryKey: ["sourcing-intake-reviews", query],
    queryFn: () => fetchSourcingIntakeReviews(query),
  });
}

// use-sourcing-intake-review.ts
import { useQuery } from "@tanstack/react-query";
import { fetchSourcingIntakeReview } from "../api/sourcing-api";

export function useSourcingIntakeReview(reviewId: string) {
  return useQuery({
    queryKey: ["sourcing-intake-review", reviewId],
    queryFn: () => fetchSourcingIntakeReview(reviewId),
    enabled: Boolean(reviewId),
  });
}

// use-sourcing-intake-actions.ts
import { useMutation, useQueryClient } from "@tanstack/react-query";
import {
  claimIntakeReview,
  decideIntakeReview,
  reassignIntakeReview,
  saveIntakeReview,
} from "../api/sourcing-api";

function invalidateReview(queryClient: ReturnType<typeof useQueryClient>, reviewId: string) {
  void queryClient.invalidateQueries({ queryKey: ["sourcing-intake-review", reviewId] });
  void queryClient.invalidateQueries({ queryKey: ["sourcing-intake-reviews"] });
}

export function useClaimSourcingIntakeReview(reviewId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => claimIntakeReview(reviewId),
    onSuccess: () => invalidateReview(queryClient, reviewId),
  });
}

export function useSaveSourcingIntakeReview(reviewId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (values: Parameters<typeof saveIntakeReview>[1]) => saveIntakeReview(reviewId, values),
    onSuccess: () => invalidateReview(queryClient, reviewId),
  });
}

export function useDecideSourcingIntakeReview(reviewId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (values: Parameters<typeof decideIntakeReview>[1]) => decideIntakeReview(reviewId, values),
    onSuccess: () => invalidateReview(queryClient, reviewId),
  });
}

export function useReassignSourcingIntakeReview(reviewId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (buyerId: string) => reassignIntakeReview(reviewId, buyerId),
    onSuccess: () => invalidateReview(queryClient, reviewId),
  });
}
```

- [ ] **Step 5: Add MSW fixtures and handlers**

Create fixtures for at least:

- unassigned open review;
- claimed in-review review;
- clarification requested review;
- ready for RFQ review;
- direct award recorded review.

Create handlers with concrete state transitions:

```ts
import { http, HttpResponse } from "msw";
import type { SourcingIntakeReview } from "../types/sourcing-view-model";
import { sourcingIntakeFixtures } from "./sourcing-fixtures";

let reviews: SourcingIntakeReview[] = structuredClone(sourcingIntakeFixtures);

function findReview(reviewId: string) {
  return reviews.find((review) => review.id === reviewId);
}

function notFound() {
  return HttpResponse.json({ error: { code: "not_found", message: "Sourcing intake review was not found." } }, { status: 404 });
}

export function resetSourcingMockState() {
  reviews = structuredClone(sourcingIntakeFixtures);
}

export const sourcingHandlers = [
  http.get("/api/sourcing/intake-reviews", ({ request }) => {
    const url = new URL(request.url);
    const preset = url.searchParams.get("preset");
    const data = reviews.filter((review) => {
      if (preset === "unassigned") return review.assignedBuyer === null;
      if (preset === "mine") return review.assignedBuyer?.id === "buyer-1";
      if (preset === "needs_clarification") return review.status === "clarification_requested";
      if (preset === "ready_for_rfq") return review.status === "ready_for_rfq";
      if (preset === "closed") return review.status === "closed" || review.status === "direct_award_recorded";
      return true;
    });

    return HttpResponse.json({
      data,
      meta: {
        currentPage: 1,
        perPage: 25,
        total: data.length,
        statusCounts: {
          open: reviews.filter((review) => review.status === "open").length,
          in_review: reviews.filter((review) => review.status === "in_review").length,
          clarification_requested: reviews.filter((review) => review.status === "clarification_requested").length,
          ready_for_rfq: reviews.filter((review) => review.status === "ready_for_rfq").length,
          direct_award_recorded: reviews.filter((review) => review.status === "direct_award_recorded").length,
          closed: reviews.filter((review) => review.status === "closed").length,
          unassigned: reviews.filter((review) => review.assignedBuyer === null).length,
          mine: reviews.filter((review) => review.assignedBuyer?.id === "buyer-1").length,
        },
      },
    });
  }),
  http.get("/api/sourcing/intake-reviews/:reviewId", ({ params }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    return HttpResponse.json({ data: review });
  }),
  http.post("/api/sourcing/intake-reviews/:reviewId/claim", ({ params }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    review.status = "in_review";
    review.assignedBuyer = { id: "buyer-1", name: "Priya Buyer", email: "priya.buyer@acme.test" };
    review.claimedAt = "2026-05-19T08:00:00.000Z";
    review.permissions.canClaim = false;
    review.permissions.canRecordDecision = true;
    return HttpResponse.json({ data: review });
  }),
  http.patch("/api/sourcing/intake-reviews/:reviewId", async ({ params, request }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    if (["ready_for_rfq", "direct_award_recorded", "closed"].includes(review.status)) {
      return HttpResponse.json({ error: { code: "conflict", message: "Decided intake reviews cannot be edited." } }, { status: 409 });
    }
    const payload = (await request.json()) as Partial<SourcingIntakeReview>;
    Object.assign(review, payload, { updatedAt: "2026-05-19T08:05:00.000Z" });
    return HttpResponse.json({ data: review });
  }),
  http.post("/api/sourcing/intake-reviews/:reviewId/decision", async ({ params, request }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    const payload = (await request.json()) as { sourcingPath: string; decisionReason: string; clarificationMessage?: string };
    review.sourcingPath = payload.sourcingPath as SourcingIntakeReview["sourcingPath"];
    review.decisionReason = payload.decisionReason;
    review.clarificationMessage = payload.clarificationMessage ?? null;
    review.status =
      payload.sourcingPath === "needs_rfq"
        ? "ready_for_rfq"
        : payload.sourcingPath === "needs_clarification"
          ? "clarification_requested"
          : "direct_award_recorded";
    review.permissions.canUpdate = false;
    review.permissions.canRecordDecision = false;
    review.permissions.canCreateRfq = false;
    review.decidedAt = "2026-05-19T08:10:00.000Z";
    return HttpResponse.json({ data: review });
  }),
  http.post("/api/sourcing/intake-reviews/:reviewId/close", async ({ params, request }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    const payload = (await request.json()) as { sourcingPath: "no_sourcing_required"; decisionReason: string };
    review.status = "closed";
    review.sourcingPath = payload.sourcingPath;
    review.decisionReason = payload.decisionReason;
    review.closedAt = "2026-05-19T08:15:00.000Z";
    review.permissions.canUpdate = false;
    review.permissions.canRecordDecision = false;
    review.permissions.canClose = false;
    return HttpResponse.json({ data: review });
  }),
];
```

Register the handler array in the central MSW handler list using the existing spread pattern.

- [ ] **Step 6: Run focused web typecheck**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: PASS or fail only on files that will be completed by UI tasks. Fix generated-client import names immediately if they differ from the plan.

## Task 6: Queue Route, Navigation, Table

**Files:**

- Create: `apps/web/app/(workspace)/sourcing/intake/page.tsx`
- Create: `apps/web/features/sourcing/workflows/sourcing-intake-list-page.tsx`
- Create: `apps/web/features/sourcing/tables/sourcing-intake-table.tsx`
- Create: `apps/web/features/sourcing/components/sourcing-intake-status-badge.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`
- Modify: `apps/web/features/identity/types/identity-view-model.ts`

- [ ] **Step 1: Add identity permission type**

Modify `IdentityPermissions` in `apps/web/features/identity/types/identity-view-model.ts` to include:

```ts
canManageSourcingIntake: boolean;
```

Update identity fixtures so requester/approver are `false`, buyer/admin are `true`.

- [ ] **Step 2: Add shell navigation and breadcrumbs**

In `shell-route-config.ts`, add a sourcing nav item visible when `permissions.canManageSourcingIntake` is true:

```ts
{
  label: "Sourcing",
  href: "/sourcing/intake",
  icon: ClipboardCheck,
  permission: (permissions) => permissions.canManageSourcingIntake,
}
```

Add breadcrumbs:

```ts
if (normalizedPathname === "/sourcing/intake") {
  return [{ label: "Sourcing intake" }];
}

const sourcingIntakeMatch = normalizedPathname.match(/^\/sourcing\/intake\/[^/]+$/);
if (sourcingIntakeMatch) {
  return [{ label: "Sourcing intake", href: "/sourcing/intake" }, { label: "Intake review" }];
}
```

Update `shell-route-config.test.tsx` to assert buyers see Sourcing and requesters do not.

- [ ] **Step 3: Add route page**

Create `apps/web/app/(workspace)/sourcing/intake/page.tsx`:

```tsx
import { SourcingIntakeListPage } from "@/features/sourcing/workflows/sourcing-intake-list-page";

export default function Page() {
  return <SourcingIntakeListPage />;
}
```

- [ ] **Step 4: Add status badge**

Create `sourcing-intake-status-badge.tsx` using existing `StatusBadge`:

```tsx
import { StatusBadge } from "@/components/workflow/status-badge";
import type { SourcingIntakeStatus } from "../types/sourcing-view-model";

const labels: Record<SourcingIntakeStatus, string> = {
  open: "Open",
  in_review: "In review",
  clarification_requested: "Clarification requested",
  ready_for_rfq: "Ready for RFQ",
  direct_award_recorded: "Direct award recorded",
  closed: "Closed",
};

export function SourcingIntakeStatusBadge({ status }: { status: SourcingIntakeStatus }) {
  return <StatusBadge label={labels[status]} tone={status === "ready_for_rfq" ? "success" : status === "clarification_requested" ? "warning" : "neutral"} />;
}
```

If `StatusBadge` uses different props, adapt to the existing component API without changing `packages/ui`.

- [ ] **Step 5: Add table**

Create `sourcing-intake-table.tsx` using `DataTable` with columns:

- requisition;
- requester;
- department;
- estimated total;
- needed by;
- intake status;
- assigned buyer;
- target decision;
- updated.

Each row should link to `/sourcing/intake/${review.id}` and mobile rows must show requisition title, status, buyer, amount, and target date.

- [ ] **Step 6: Add list workflow**

Create `sourcing-intake-list-page.tsx`:

```tsx
"use client";

import { useState } from "react";
import { Button } from "@cognify/ui/components/button";
import { useSourcingIntakeReviews } from "../hooks/use-sourcing-intake-reviews";
import { SourcingIntakeTable } from "../tables/sourcing-intake-table";

export function SourcingIntakeListPage() {
  const [preset, setPreset] = useState("unassigned");
  const query = useSourcingIntakeReviews({ preset });

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-normal">Sourcing intake</h1>
          <p className="text-sm text-muted-foreground">Review requisitions before RFQ creation or sourcing closeout.</p>
        </div>
        <div className="flex flex-wrap gap-2" aria-label="Intake presets">
          {["unassigned", "mine", "needs_clarification", "ready_for_rfq", "closed"].map((value) => (
            <Button key={value} type="button" variant={preset === value ? "default" : "outline"} onClick={() => setPreset(value)}>
              {value.replaceAll("_", " ")}
            </Button>
          ))}
        </div>
      </div>
      <SourcingIntakeTable
        reviews={query.data?.data ?? []}
        isLoading={query.isLoading}
        error={query.error}
      />
    </div>
  );
}
```

Use existing local button imports; if `@cognify/ui/components/button` is not the local convention, match current feature imports.

- [ ] **Step 7: Run queue tests/typecheck**

Run:

```bash
pnpm --filter @cognify/web test -- sourcing-intake-workflow
pnpm --filter @cognify/web typecheck
```

Expected: tests may still fail until Task 7 creates the detail flow, but typecheck should pass for queue files.

## Task 7: Detail Workspace, Forms, Actions

**Files:**

- Create: `apps/web/app/(workspace)/sourcing/intake/[reviewId]/page.tsx`
- Create: `apps/web/features/sourcing/workflows/sourcing-intake-detail-page.tsx`
- Create: `apps/web/features/sourcing/components/sourcing-intake-review-form.tsx`
- Create: `apps/web/features/sourcing/components/sourcing-intake-decision-dialog.tsx`
- Modify: `apps/web/features/sourcing/tests/sourcing-intake-workflow.test.tsx`

- [ ] **Step 1: Add route page**

Create `apps/web/app/(workspace)/sourcing/intake/[reviewId]/page.tsx`:

```tsx
import { SourcingIntakeDetailPage } from "@/features/sourcing/workflows/sourcing-intake-detail-page";

export default function Page({ params }: { params: { reviewId: string } }) {
  return <SourcingIntakeDetailPage reviewId={params.reviewId} />;
}
```

- [ ] **Step 2: Add review form**

Create a client component with controlled fields for category, subcategory, urgency, complexity, target decision date, checklist checkboxes, and internal notes. Use `sourcingIntakeReviewFormSchema` for validation. Submit through `useSaveSourcingIntakeReview(review.id)`.

Checklist defaults:

```ts
const defaultChecklist = [
  { key: "specification_complete", label: "Specification complete", complete: false },
  { key: "budget_clear", label: "Budget clear", complete: false },
  { key: "line_items_complete", label: "Line items complete", complete: false },
  { key: "needed_by_feasible", label: "Needed-by date feasible", complete: false },
  { key: "evidence_sufficient", label: "Evidence sufficient", complete: false },
];
```

Disable all fields when `review.permissions.canUpdate` is false.

- [ ] **Step 3: Add decision dialog**

Create a dialog with:

- radio/select for `needs_rfq`, `needs_clarification`, `direct_award`, `no_sourcing_required`;
- decision reason textarea;
- clarification message textarea visible and required for `needs_clarification`;
- submit button disabled while mutation is pending.

Submit through `useDecideSourcingIntakeReview(review.id)`.

User-facing button labels:

- "Mark ready for RFQ"
- "Request clarification"
- "Record direct award path"
- "Close without sourcing"

- [ ] **Step 4: Add detail workspace**

Create `sourcing-intake-detail-page.tsx` using `RecordWorkspaceLayout`:

```tsx
"use client";

import Link from "next/link";
import { Button } from "@cognify/ui/components/button";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { useSourcingIntakeReview } from "../hooks/use-sourcing-intake-review";
import { useClaimSourcingIntakeReview } from "../hooks/use-sourcing-intake-actions";
import { SourcingIntakeStatusBadge } from "../components/sourcing-intake-status-badge";
import { SourcingIntakeReviewForm } from "../components/sourcing-intake-review-form";
import { SourcingIntakeDecisionDialog } from "../components/sourcing-intake-decision-dialog";

export function SourcingIntakeDetailPage({ reviewId }: { reviewId: string }) {
  const reviewQuery = useSourcingIntakeReview(reviewId);
  const claimMutation = useClaimSourcingIntakeReview(reviewId);
  const review = reviewQuery.data;

  if (reviewQuery.isLoading) return <div aria-label="Loading sourcing intake">Loading sourcing intake</div>;
  if (reviewQuery.isError || !review) return <div role="alert">Unable to load sourcing intake review.</div>;

  return (
    <RecordWorkspaceLayout
      backHref="/sourcing/intake"
      backLabel="Back to sourcing intake"
      title={review.requisition.title}
      eyebrow={review.requisition.number}
      status={<SourcingIntakeStatusBadge status={review.status} />}
      actions={
        <div className="flex flex-wrap gap-2">
          {review.permissions.canClaim ? (
            <Button onClick={() => claimMutation.mutate()} disabled={claimMutation.isPending}>
              Claim
            </Button>
          ) : null}
          {review.permissions.canRecordDecision ? <SourcingIntakeDecisionDialog review={review} /> : null}
          {review.status === "ready_for_rfq" ? <Button disabled>Create RFQ</Button> : null}
        </div>
      }
    >
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section className="space-y-4">
          <div>
            <h2 className="text-lg font-semibold">Requisition summary</h2>
            <dl className="mt-3 grid gap-3 sm:grid-cols-2">
              <div><dt className="text-sm text-muted-foreground">Requester</dt><dd>{review.requisition.requester?.name ?? "Unknown"}</dd></div>
              <div><dt className="text-sm text-muted-foreground">Department</dt><dd>{review.requisition.department ?? "Not set"}</dd></div>
              <div><dt className="text-sm text-muted-foreground">Needed by</dt><dd>{review.requisition.neededByDate ?? "Not set"}</dd></div>
              <div><dt className="text-sm text-muted-foreground">Project</dt><dd>{review.project ? <Link href={`/projects/${review.project.id}`}>{review.project.name}</Link> : "No project"}</dd></div>
            </dl>
          </div>
          <div>
            <h2 className="text-lg font-semibold">Sourcing handoff</h2>
            {review.status === "ready_for_rfq" ? (
              <p className="text-sm text-muted-foreground">This review is ready for the RFQ creation slice. RFQ creation is intentionally disabled in this slice.</p>
            ) : (
              <p className="text-sm text-muted-foreground">Record the buyer intake decision before RFQ or closeout work starts.</p>
            )}
          </div>
        </section>
        <SourcingIntakeReviewForm review={review} />
      </div>
    </RecordWorkspaceLayout>
  );
}
```

Adjust `RecordWorkspaceLayout` prop names to match the existing component.

- [ ] **Step 5: Add workflow tests**

Create `sourcing-intake-workflow.test.tsx` with tests:

```tsx
it("renders buyer intake queue and links to detail")
it("claims an unassigned review")
it("saves checklist and classification")
it("marks review ready for RFQ without creating an RFQ")
it("shows permission denied or load error states")
it("recovers from stale decision conflict by refetching")
```

Use `QueryClientProvider`, `RightPanelProvider`, `server`, and `resetSourcingMockState()` following `projects-workflow.test.tsx`.

- [ ] **Step 6: Run focused web tests**

Run:

```bash
pnpm --filter @cognify/web test -- sourcing-intake-workflow
pnpm --filter @cognify/web typecheck
```

Expected: PASS.

## Task 8: Demo Data, Search Links, Hardening

**Files:**

- Modify: `apps/api/database/seeders/Demo/DemoRoadmapPreviewSeeder.php`
- Modify: `apps/api/Domains/Search/Providers/RfqSearchProvider.php` only if RFQ links should point to sourcing intake when a review exists.
- Modify: `apps/web/features/system-readiness/mocks/system-readiness-fixtures.ts` only if demo counts change.

- [ ] **Step 1: Seed sourcing intake reviews for demo requisitions**

Add two deterministic reviews:

- one unassigned `open` review for a submitted requisition;
- one `ready_for_rfq` review assigned to the demo buyer.

Use stable timestamps and tenant IDs from the existing demo seeder. Do not create RFQs from these reviews.

- [ ] **Step 2: Keep search behavior conservative**

Do not add a new global search result type for sourcing intake in this slice unless product search already supports arbitrary internal workflow records. Existing RFQ search can remain unchanged.

- [ ] **Step 3: Run demo seeder test**

Run:

```bash
cd apps/api && php artisan test tests/Feature/DemoSeederTest.php
```

Expected: PASS.

## Task 9: Final Verification

**Files:**

- Verify all touched files.

- [ ] **Step 1: Inspect git diff**

Run:

```bash
git diff --stat
git diff --check
```

Expected: no whitespace errors; changed files are limited to the spec-approved backend, contract, generated client, and web sourcing surfaces.

- [ ] **Step 2: Run API verification**

Run:

```bash
cd apps/api && php artisan test tests/Feature/SourcingIntakeApiTest.php tests/Feature/RequisitionApiTest.php tests/Feature/ProcurementProjectApiTest.php tests/Feature/DemoSeederTest.php
```

Expected: PASS.

Run:

```bash
cd apps/api && php artisan route:list --path=api
```

Expected: sourcing intake routes appear under `/api/sourcing/intake-reviews` and `/api/requisitions/{requisition}/sourcing-intake`.

- [ ] **Step 3: Run contract verification**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: PASS. Generated API client files are committed with the contract.

- [ ] **Step 4: Run web verification**

Run:

```bash
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test -- sourcing-intake-workflow
pnpm --filter @cognify/web test
```

Expected: PASS.

- [ ] **Step 5: Run root final build**

Run:

```bash
pnpm build
```

Expected: PASS.

- [ ] **Step 6: Final status summary**

Summarize:

- implemented workflow slice;
- RFQ creation and vendor invitations still out of scope;
- tests and commands that passed;
- any command that could not run and the exact blocker.

Do not claim completion until this verification evidence exists.
