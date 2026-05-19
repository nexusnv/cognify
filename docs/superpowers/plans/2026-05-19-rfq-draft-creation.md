# RFQ Draft Creation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build P1 Epic 5 slice 2: create, edit, view, and cancel draft RFQs from `ready_for_rfq` sourcing intake reviews without implementing vendor invitations.

**Architecture:** `apps/api/Domains/Quotation` owns RFQ draft state, policies, actions, requests, resources, and controllers. The Next.js sourcing feature owns RFQ workspace UI under `apps/web/features/sourcing`, using OpenAPI-generated endpoints from `@cognify/api-client`; shared packages remain primitive or generated-contract only.

**Tech Stack:** Laravel 12, Eloquent, Sanctum tenant middleware, OpenAPI/Orval, Next.js App Router, TanStack Query, React Hook Form, Zod, MSW, Vitest, shadcn/Radix primitives via `@cognify/ui`.

---

## Grounding

- Spec: `docs/superpowers/specs/2026-05-19-rfq-draft-creation-design.md`
- Release slice: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 5, slice 2
- Architecture/runbook: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Previous slice: `docs/superpowers/specs/2026-05-19-buyer-intake-sourcing-review-design.md`

## Scope Boundary

In scope:

- Draft RFQ creation/reveal from a `ready_for_rfq` sourcing intake review.
- Draft RFQ show/update/cancel endpoints.
- RFQ draft workspace.
- Buyer intake handoff button wired to real RFQ creation.
- OpenAPI and generated client sync.
- API and web tests.

Out of scope:

- Vendor invitations, publish-to-vendor behavior, vendor portal, quotations, comparison, scoring, awards, PO handoff, and AI recommendations.

## File Structure

Backend files:

- Create: `apps/api/database/migrations/2026_05_19_010000_extend_rfqs_for_draft_workflow.php`
- Create: `apps/api/Domains/Quotation/States/RfqStatus.php`
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`
- Create: `apps/api/Domains/Quotation/Policies/RfqPolicy.php`
- Create: `apps/api/Domains/Quotation/Actions/CreateOrRevealRfqDraftFromIntake.php`
- Create: `apps/api/Domains/Quotation/Actions/UpdateRfqDraft.php`
- Create: `apps/api/Domains/Quotation/Actions/CancelRfqDraft.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/RfqController.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/UpdateRfqDraftRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/CancelRfqDraftRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqResource.php`
- Modify: `apps/api/Domains/Quotation/Http/Resources/SourcingIntakeReviewResource.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Test: `apps/api/tests/Feature/RfqDraftApiTest.php`

Frontend files:

- Create: `apps/web/app/(workspace)/sourcing/rfqs/[rfqId]/page.tsx`
- Create: `apps/web/features/sourcing/api/rfq-api.ts`
- Create: `apps/web/features/sourcing/hooks/use-rfq-draft.ts`
- Create: `apps/web/features/sourcing/hooks/use-rfq-draft-actions.ts`
- Create: `apps/web/features/sourcing/schemas/rfq-draft-schema.ts`
- Create: `apps/web/features/sourcing/types/rfq-view-model.ts`
- Create: `apps/web/features/sourcing/components/rfq-status-badge.tsx`
- Create: `apps/web/features/sourcing/components/rfq-draft-form.tsx`
- Create: `apps/web/features/sourcing/components/rfq-line-items-table.tsx`
- Create: `apps/web/features/sourcing/components/rfq-required-documents-editor.tsx`
- Create: `apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx`
- Create: `apps/web/features/sourcing/mocks/rfq-fixtures.ts`
- Create: `apps/web/features/sourcing/mocks/rfq-handlers.ts`
- Modify: `apps/web/features/sourcing/mocks/sourcing-handlers.ts`
- Modify: `apps/web/tests/msw/handlers.ts`
- Modify: `apps/web/features/sourcing/workflows/sourcing-intake-detail-page.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Test: `apps/web/features/sourcing/tests/rfq-draft-workflow.test.tsx`
- Modify: `apps/web/features/sourcing/tests/sourcing-intake-workflow.test.tsx`

Generated files:

- Update: `packages/api-client/src/generated/**`

---

### Task 1: Backend Contract Tests For RFQ Draft Workflow

**Files:**
- Create: `apps/api/tests/Feature/RfqDraftApiTest.php`

- [ ] **Step 1: Write failing API tests**

Create `apps/api/tests/Feature/RfqDraftApiTest.php` with tests that define the expected workflow before implementation:

```php
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
use Domains\Requisition\Models\RequisitionLineItem;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}", ['title' => 'Should not change'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
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
            'status' => RequisitionStatus::Approved,
            'currency' => 'MYR',
            'department' => 'Operations',
            'needed_by_date' => '2026-07-15',
        ]);

        RequisitionLineItem::query()->create([
            'requisition_id' => $requisition->id,
            'description' => 'Laptop',
            'quantity' => 10,
            'unit' => 'each',
            'estimated_unit_price' => 4500,
            'category' => 'IT Hardware',
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
            'scope_summary' => 'Draft scope',
            'required_documents' => [],
            'line_items' => [],
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
cd apps/api && php artisan test tests/Feature/RfqDraftApiTest.php
```

Expected: failures for missing columns such as `sourcing_intake_review_id`, missing routes/controllers, or missing classes.

---

### Task 2: Backend Data Model, State, Policy, And Actions

**Files:**
- Create: `apps/api/database/migrations/2026_05_19_010000_extend_rfqs_for_draft_workflow.php`
- Create: `apps/api/Domains/Quotation/States/RfqStatus.php`
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`
- Create: `apps/api/Domains/Quotation/Policies/RfqPolicy.php`
- Create: `apps/api/Domains/Quotation/Actions/CreateOrRevealRfqDraftFromIntake.php`
- Create: `apps/api/Domains/Quotation/Actions/UpdateRfqDraft.php`
- Create: `apps/api/Domains/Quotation/Actions/CancelRfqDraft.php`

- [ ] **Step 1: Add RFQ draft migration**

Create `apps/api/database/migrations/2026_05_19_010000_extend_rfqs_for_draft_workflow.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            $table->foreignId('sourcing_intake_review_id')->nullable()->after('tenant_id')->constrained('sourcing_intake_reviews')->nullOnDelete();
            $table->text('scope_summary')->nullable()->after('status');
            $table->timestamp('response_due_at')->nullable()->after('scope_summary');
            $table->text('response_instructions')->nullable()->after('response_due_at');
            $table->json('required_documents')->nullable()->after('response_instructions');
            $table->json('line_items')->nullable()->after('required_documents');
            $table->text('evaluation_notes')->nullable()->after('line_items');
            $table->text('internal_notes')->nullable()->after('evaluation_notes');
            $table->text('cancel_reason')->nullable()->after('internal_notes');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            $table->index(['tenant_id', 'sourcing_intake_review_id']);
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'sourcing_intake_review_id']);
            $table->dropConstrainedForeignId('sourcing_intake_review_id');
            $table->dropColumn([
                'scope_summary',
                'response_due_at',
                'response_instructions',
                'required_documents',
                'line_items',
                'evaluation_notes',
                'internal_notes',
                'cancel_reason',
                'cancelled_at',
            ]);
        });
    }
};
```

- [ ] **Step 2: Add RFQ status enum**

Create `apps/api/Domains/Quotation/States/RfqStatus.php`:

```php
<?php

namespace Domains\Quotation\States;

enum RfqStatus: string
{
    case Draft = 'draft';
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
```

- [ ] **Step 3: Update RFQ model**

Modify `apps/api/Domains/Quotation/Models/Rfq.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\States\RfqStatus;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Rfq extends Model
{
    protected $table = 'rfqs';

    protected $fillable = [
        'tenant_id',
        'sourcing_intake_review_id',
        'project_id',
        'requisition_id',
        'number',
        'title',
        'status',
        'due_at',
        'response_due_at',
        'scope_summary',
        'response_instructions',
        'required_documents',
        'line_items',
        'evaluation_notes',
        'internal_notes',
        'cancel_reason',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqStatus::class,
            'due_at' => 'datetime',
            'response_due_at' => 'datetime',
            'required_documents' => 'array',
            'line_items' => 'array',
            'metadata' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rfq): void {
            DB::transaction(function () use ($rfq): void {
                if ($rfq->sourcing_intake_review_id !== null && ($rfq->isDirty('sourcing_intake_review_id') || $rfq->isDirty('tenant_id'))) {
                    $review = SourcingIntakeReview::query()->whereKey($rfq->sourcing_intake_review_id)->lockForUpdate()->first();

                    if ($review !== null && (int) $review->tenant_id !== (int) $rfq->tenant_id) {
                        throw new InvalidArgumentException('RFQ sourcing intake review must belong to the same tenant.');
                    }
                }

                if ($rfq->project_id !== null && ($rfq->isDirty('project_id') || $rfq->isDirty('tenant_id'))) {
                    $project = ProcurementProject::query()->whereKey($rfq->project_id)->lockForUpdate()->first();

                    if ($project !== null && (int) $project->tenant_id !== (int) $rfq->tenant_id) {
                        throw new InvalidArgumentException('RFQ project must belong to the same tenant.');
                    }
                }

                if ($rfq->requisition_id !== null && ($rfq->isDirty('requisition_id') || $rfq->isDirty('tenant_id'))) {
                    $requisition = Requisition::query()->whereKey($rfq->requisition_id)->lockForUpdate()->first();

                    if ($requisition !== null && (int) $requisition->tenant_id !== (int) $rfq->tenant_id) {
                        throw new InvalidArgumentException('RFQ requisition must belong to the same tenant.');
                    }
                }
            });
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourcingIntakeReview(): BelongsTo
    {
        return $this->belongsTo(SourcingIntakeReview::class, 'sourcing_intake_review_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'project_id');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }
}
```

- [ ] **Step 4: Add policy and register if needed**

Create `apps/api/Domains/Quotation/Policies/RfqPolicy.php`:

```php
<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use Domains\Quotation\Models\Rfq;

class RfqPolicy
{
    public function view(User $user, Rfq $rfq): bool
    {
        return $this->canManageSourcing($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageSourcing($user);
    }

    public function update(User $user, Rfq $rfq): bool
    {
        return $this->canManageSourcing($user);
    }

    public function cancel(User $user, Rfq $rfq): bool
    {
        return $this->canManageSourcing($user);
    }

    private function canManageSourcing(User $user): bool
    {
        $role = $user->activeTenantRole();

        return in_array($role, [TenantRole::Buyer, TenantRole::Admin], true);
    }
}
```

If this repo's policy auto-discovery does not find domain policies during test execution, register `Rfq::class => RfqPolicy::class` in the existing auth service provider.

- [ ] **Step 5: Add create/reveal action**

Create `apps/api/Domains/Quotation/Actions/CreateOrRevealRfqDraftFromIntake.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\RfqStatus;
use Domains\Quotation\States\SourcingIntakeStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrRevealRfqDraftFromIntake
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @return array{rfq:Rfq, created:bool}
     */
    public function handle(Tenant $tenant, User $actor, SourcingIntakeReview $review): array
    {
        if ($review->status !== SourcingIntakeStatus::ReadyForRfq) {
            throw new ConflictHttpException('Only RFQ-ready sourcing intake reviews can create draft RFQs.');
        }

        return DB::transaction(function () use ($tenant, $actor, $review): array {
            $existing = Rfq::query()
                ->where('tenant_id', $tenant->id)
                ->where('sourcing_intake_review_id', $review->id)
                ->where('status', RfqStatus::Draft)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return ['rfq' => $this->loadRfq($existing), 'created' => false];
            }

            $requisition = $review->requisition()->with('lineItems')->firstOrFail();

            $rfq = Rfq::query()->create([
                'tenant_id' => $tenant->id,
                'sourcing_intake_review_id' => $review->id,
                'project_id' => $review->project_id,
                'requisition_id' => $review->requisition_id,
                'number' => $this->nextNumber($tenant),
                'title' => $requisition->title . ' RFQ',
                'status' => RfqStatus::Draft,
                'scope_summary' => $review->decision_reason,
                'required_documents' => [],
                'line_items' => $requisition->lineItems->map(fn ($lineItem): array => [
                    'description' => $lineItem->description,
                    'quantity' => (float) $lineItem->quantity,
                    'unit' => $lineItem->unit,
                    'notes' => $lineItem->category,
                ])->values()->all(),
            ]);

            $this->audit->record(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq.draft_created',
                subject: $rfq,
                metadata: ['sourcingIntakeReviewId' => (string) $review->id],
            );

            return ['rfq' => $this->loadRfq($rfq), 'created' => true];
        });
    }

    private function nextNumber(Tenant $tenant): string
    {
        $next = Rfq::query()->where('tenant_id', $tenant->id)->lockForUpdate()->count() + 1;

        return 'RFQ-2026-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function loadRfq(Rfq $rfq): Rfq
    {
        return $rfq->load(['sourcingIntakeReview.assignedBuyer', 'requisition.requester', 'requisition.lineItems', 'project']);
    }
}
```

- [ ] **Step 6: Add update and cancel actions**

Create `apps/api/Domains/Quotation/Actions/UpdateRfqDraft.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateRfqDraft
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): Rfq
    {
        if (! $rfq->status->isEditable()) {
            throw new ConflictHttpException('Cancelled RFQs cannot be edited.');
        }

        $rfq->fill([
            'title' => $data['title'] ?? $rfq->title,
            'scope_summary' => $data['scopeSummary'] ?? $rfq->scope_summary,
            'response_due_at' => $data['responseDueAt'] ?? $rfq->response_due_at,
            'response_instructions' => $data['responseInstructions'] ?? $rfq->response_instructions,
            'required_documents' => $data['requiredDocuments'] ?? $rfq->required_documents,
            'line_items' => $data['lineItems'] ?? $rfq->line_items,
            'evaluation_notes' => $data['evaluationNotes'] ?? $rfq->evaluation_notes,
            'internal_notes' => $data['internalNotes'] ?? $rfq->internal_notes,
        ]);
        $rfq->save();

        $this->audit->record(tenant: $tenant, actor: $actor, action: 'rfq.draft_updated', subject: $rfq);

        return $rfq->load(['sourcingIntakeReview.assignedBuyer', 'requisition.requester', 'requisition.lineItems', 'project']);
    }
}
```

Create `apps/api/Domains/Quotation/Actions/CancelRfqDraft.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\States\RfqStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelRfqDraft
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): Rfq
    {
        if (! $rfq->status->isEditable()) {
            throw new ConflictHttpException('This RFQ cannot be cancelled.');
        }

        $rfq->forceFill([
            'status' => RfqStatus::Cancelled,
            'cancel_reason' => $data['cancelReason'],
            'cancelled_at' => now(),
        ])->save();

        $this->audit->record(tenant: $tenant, actor: $actor, action: 'rfq.draft_cancelled', subject: $rfq);

        return $rfq->load(['sourcingIntakeReview.assignedBuyer', 'requisition.requester', 'requisition.lineItems', 'project']);
    }
}
```

- [ ] **Step 7: Run backend test and iterate**

Run:

```bash
cd apps/api && php artisan test tests/Feature/RfqDraftApiTest.php
```

Expected: failures now move from model/schema errors to missing HTTP layer classes/routes. Do not commit yet.

---

### Task 3: Backend HTTP Layer And Intake Permission Handoff

**Files:**
- Create: `apps/api/Domains/Quotation/Http/Requests/UpdateRfqDraftRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/CancelRfqDraftRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqResource.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/RfqController.php`
- Modify: `apps/api/Domains/Quotation/Http/Resources/SourcingIntakeReviewResource.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add request validation**

Create `apps/api/Domains/Quotation/Http/Requests/UpdateRfqDraftRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRfqDraftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'scopeSummary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'responseDueAt' => ['sometimes', 'nullable', 'date'],
            'responseInstructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'requiredDocuments' => ['sometimes', 'array', 'max:20'],
            'requiredDocuments.*.key' => ['required_with:requiredDocuments', 'string', 'max:80'],
            'requiredDocuments.*.label' => ['required_with:requiredDocuments', 'string', 'max:160'],
            'requiredDocuments.*.required' => ['required_with:requiredDocuments', 'boolean'],
            'lineItems' => ['sometimes', 'array', 'max:100'],
            'lineItems.*.description' => ['required_with:lineItems', 'string', 'max:255'],
            'lineItems.*.quantity' => ['required_with:lineItems', 'numeric', 'min:0.01'],
            'lineItems.*.unit' => ['required_with:lineItems', 'string', 'max:40'],
            'lineItems.*.notes' => ['nullable', 'string', 'max:1000'],
            'evaluationNotes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'internalNotes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
```

Create `apps/api/Domains/Quotation/Http/Requests/CancelRfqDraftRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelRfqDraftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cancelReason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 2: Add RFQ resource**

Create `apps/api/Domains/Quotation/Http/Resources/RfqResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class RfqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status?->value ?? $this->status;
        $canEdit = $status === 'draft';

        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'number' => $this->number,
            'title' => $this->title,
            'status' => $status,
            'scopeSummary' => $this->scope_summary,
            'responseDueAt' => $this->response_due_at?->toISOString(),
            'responseInstructions' => $this->response_instructions,
            'requiredDocuments' => $this->required_documents ?? [],
            'lineItems' => $this->line_items ?? [],
            'evaluationNotes' => $this->evaluation_notes,
            'internalNotes' => $this->internal_notes,
            'cancelReason' => $this->cancel_reason,
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'intakeReview' => $this->intakeSummary($this->whenLoaded('sourcingIntakeReview')),
            'requisition' => $this->requisitionSummary($this->whenLoaded('requisition')),
            'project' => $this->projectSummary($this->whenLoaded('project')),
            'permissions' => [
                'canUpdate' => $canEdit,
                'canCancel' => $canEdit,
                'canInviteVendors' => false,
            ],
        ];
    }

    private function intakeSummary(mixed $review): ?array
    {
        if ($review instanceof MissingValue || $review === null) {
            return null;
        }

        return [
            'id' => (string) $review->id,
            'status' => $review->status?->value ?? $review->status,
            'sourcingPath' => $review->sourcing_path?->value ?? $review->sourcing_path,
            'decisionReason' => $review->decision_reason,
            'assignedBuyer' => $this->userSummary($review->relationLoaded('assignedBuyer') ? $review->assignedBuyer : null),
        ];
    }

    private function requisitionSummary(mixed $requisition): ?array
    {
        if ($requisition instanceof MissingValue || $requisition === null) {
            return null;
        }

        return [
            'id' => (string) $requisition->id,
            'number' => $requisition->number,
            'title' => $requisition->title,
            'status' => $requisition->status?->value ?? $requisition->status,
            'department' => $requisition->department,
            'neededByDate' => $requisition->needed_by_date?->toDateString(),
            'currency' => $requisition->currency,
            'requester' => $this->userSummary($requisition->relationLoaded('requester') ? $requisition->requester : null),
        ];
    }

    private function projectSummary(mixed $project): ?array
    {
        if ($project instanceof MissingValue || $project === null) {
            return null;
        }

        return [
            'id' => (string) $project->id,
            'number' => $project->number,
            'name' => $project->name,
            'status' => $project->status?->value ?? $project->status,
        ];
    }

    private function userSummary(mixed $user): ?array
    {
        if ($user instanceof MissingValue || $user === null) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
        ];
    }
}
```

- [ ] **Step 3: Add controller and routes**

Create `apps/api/Domains/Quotation/Http/Controllers/RfqController.php`:

```php
<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\CancelRfqDraft;
use Domains\Quotation\Actions\CreateOrRevealRfqDraftFromIntake;
use Domains\Quotation\Actions\UpdateRfqDraft;
use Domains\Quotation\Http\Requests\CancelRfqDraftRequest;
use Domains\Quotation\Http\Requests\UpdateRfqDraftRequest;
use Domains\Quotation\Http\Resources\RfqResource;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\SourcingIntakeReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfqController extends Controller
{
    public function storeForIntake(Request $request, CurrentTenant $currentTenant, int $review, CreateOrRevealRfqDraftFromIntake $action): JsonResponse
    {
        $tenant = $currentTenant->get();
        $this->authorize('create', Rfq::class);
        $model = $this->findTenantReview($tenant, $review);
        $result = $action->handle($tenant, $request->user(), $model);

        return (new RfqResource($result['rfq']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function show(CurrentTenant $currentTenant, int $rfq): RfqResource
    {
        $model = $this->findTenantRfq($currentTenant->get(), $rfq);
        $this->authorize('view', $model);

        return new RfqResource($model);
    }

    public function update(UpdateRfqDraftRequest $request, CurrentTenant $currentTenant, int $rfq, UpdateRfqDraft $action): RfqResource
    {
        $tenant = $currentTenant->get();
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('update', $model);

        return new RfqResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    public function cancel(CancelRfqDraftRequest $request, CurrentTenant $currentTenant, int $rfq, CancelRfqDraft $action): RfqResource
    {
        $tenant = $currentTenant->get();
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('cancel', $model);

        return new RfqResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    private function findTenantReview(Tenant $tenant, int $id): SourcingIntakeReview
    {
        return SourcingIntakeReview::query()
            ->with(['assignedBuyer', 'requisition.lineItems', 'requisition.requester', 'project'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()
            ->with(['sourcingIntakeReview.assignedBuyer', 'requisition.requester', 'requisition.lineItems', 'project'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }
}
```

Modify `apps/api/routes/api.php`:

```php
use Domains\Quotation\Http\Controllers\RfqController;
```

Inside the tenant-resolved route group near the sourcing routes:

```php
Route::post('/sourcing/intake-reviews/{review}/rfq', [RfqController::class, 'storeForIntake']);
Route::get('/rfqs/{rfq}', [RfqController::class, 'show']);
Route::patch('/rfqs/{rfq}', [RfqController::class, 'update']);
Route::post('/rfqs/{rfq}/cancel', [RfqController::class, 'cancel']);
```

- [ ] **Step 4: Update intake resource RFQ permission**

Modify `apps/api/Domains/Quotation/Http/Resources/SourcingIntakeReviewResource.php` permissions:

```php
'canCreateRfq' => $status === SourcingIntakeStatus::ReadyForRfq,
```

- [ ] **Step 5: Run and commit backend behavior**

Run:

```bash
cd apps/api && php artisan test tests/Feature/RfqDraftApiTest.php tests/Feature/SourcingIntakeApiTest.php
```

Expected: all tests pass.

Commit:

```bash
git add apps/api/database/migrations/2026_05_19_010000_extend_rfqs_for_draft_workflow.php apps/api/Domains/Quotation apps/api/routes/api.php apps/api/tests/Feature/RfqDraftApiTest.php
git commit -m "Add RFQ draft backend workflow"
```

---

### Task 4: OpenAPI Contract And Generated Client

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`
- Update: `packages/api-client/src/generated/**`

- [ ] **Step 1: Add OpenAPI paths**

In `apps/api/storage/openapi/openapi.json`, add paths matching the controller:

```json
"/api/sourcing/intake-reviews/{review}/rfq": {
  "post": {
    "operationId": "createSourcingIntakeRfq",
    "tags": ["sourcing"],
    "parameters": [
      {
        "name": "review",
        "in": "path",
        "required": true,
        "schema": { "type": "string" }
      }
    ],
    "responses": {
      "200": { "description": "Existing RFQ draft", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/RfqResponse" } } } },
      "201": { "description": "Created RFQ draft", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/RfqResponse" } } } },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/Conflict" },
      "422": { "$ref": "#/components/responses/ValidationError" }
    }
  }
},
"/api/rfqs/{rfq}": {
  "get": {
    "operationId": "getRfq",
    "tags": ["rfqs"],
    "parameters": [{ "name": "rfq", "in": "path", "required": true, "schema": { "type": "string" } }],
    "responses": { "200": { "description": "RFQ", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/RfqResponse" } } } } }
  },
  "patch": {
    "operationId": "updateRfqDraft",
    "tags": ["rfqs"],
    "parameters": [{ "name": "rfq", "in": "path", "required": true, "schema": { "type": "string" } }],
    "requestBody": { "required": true, "content": { "application/json": { "schema": { "$ref": "#/components/schemas/RfqUpdateRequest" } } } },
    "responses": { "200": { "description": "Updated RFQ", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/RfqResponse" } } } } }
  }
},
"/api/rfqs/{rfq}/cancel": {
  "post": {
    "operationId": "cancelRfqDraft",
    "tags": ["rfqs"],
    "parameters": [{ "name": "rfq", "in": "path", "required": true, "schema": { "type": "string" } }],
    "requestBody": { "required": true, "content": { "application/json": { "schema": { "$ref": "#/components/schemas/RfqCancelRequest" } } } },
    "responses": { "200": { "description": "Cancelled RFQ", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/RfqResponse" } } } } }
  }
}
```

Use the existing OpenAPI style for common error response objects if the exact component names differ.

- [ ] **Step 2: Add OpenAPI schemas**

Add schemas:

```json
"RfqRequiredDocument": {
  "type": "object",
  "required": ["key", "label", "required"],
  "properties": {
    "key": { "type": "string" },
    "label": { "type": "string" },
    "required": { "type": "boolean" }
  }
},
"RfqLineItem": {
  "type": "object",
  "required": ["description", "quantity", "unit"],
  "properties": {
    "description": { "type": "string" },
    "quantity": { "type": "number" },
    "unit": { "type": "string" },
    "notes": { "type": ["string", "null"] }
  }
},
"RfqPermissions": {
  "type": "object",
  "required": ["canUpdate", "canCancel", "canInviteVendors"],
  "properties": {
    "canUpdate": { "type": "boolean" },
    "canCancel": { "type": "boolean" },
    "canInviteVendors": { "type": "boolean" }
  }
},
"Rfq": {
  "type": "object",
  "required": ["id", "tenantId", "number", "title", "status", "requiredDocuments", "lineItems", "permissions"],
  "properties": {
    "id": { "type": "string" },
    "tenantId": { "type": "string" },
    "number": { "type": "string" },
    "title": { "type": "string" },
    "status": { "type": "string", "enum": ["draft", "cancelled"] },
    "scopeSummary": { "type": ["string", "null"] },
    "responseDueAt": { "type": ["string", "null"], "format": "date-time" },
    "responseInstructions": { "type": ["string", "null"] },
    "requiredDocuments": { "type": "array", "items": { "$ref": "#/components/schemas/RfqRequiredDocument" } },
    "lineItems": { "type": "array", "items": { "$ref": "#/components/schemas/RfqLineItem" } },
    "evaluationNotes": { "type": ["string", "null"] },
    "internalNotes": { "type": ["string", "null"] },
    "cancelReason": { "type": ["string", "null"] },
    "cancelledAt": { "type": ["string", "null"], "format": "date-time" },
    "createdAt": { "type": ["string", "null"], "format": "date-time" },
    "updatedAt": { "type": ["string", "null"], "format": "date-time" },
    "intakeReview": { "$ref": "#/components/schemas/RfqIntakeReviewSummary" },
    "requisition": { "$ref": "#/components/schemas/RfqRequisitionSummary" },
    "project": { "oneOf": [{ "$ref": "#/components/schemas/RfqProjectSummary" }, { "type": "null" }] },
    "permissions": { "$ref": "#/components/schemas/RfqPermissions" }
  }
},
"RfqResponse": {
  "type": "object",
  "required": ["data"],
  "properties": { "data": { "$ref": "#/components/schemas/Rfq" } }
},
"RfqUpdateRequest": {
  "type": "object",
  "properties": {
    "title": { "type": "string" },
    "scopeSummary": { "type": ["string", "null"] },
    "responseDueAt": { "type": ["string", "null"], "format": "date-time" },
    "responseInstructions": { "type": ["string", "null"] },
    "requiredDocuments": { "type": "array", "items": { "$ref": "#/components/schemas/RfqRequiredDocument" } },
    "lineItems": { "type": "array", "items": { "$ref": "#/components/schemas/RfqLineItem" } },
    "evaluationNotes": { "type": ["string", "null"] },
    "internalNotes": { "type": ["string", "null"] }
  }
},
"RfqCancelRequest": {
  "type": "object",
  "required": ["cancelReason"],
  "properties": { "cancelReason": { "type": "string" } }
}
```

Also add summary schemas referenced above, reusing existing user/project summary shapes where available.

- [ ] **Step 3: Generate and verify contract**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated endpoint functions include `createSourcingIntakeRfq`, `getRfq`, `updateRfqDraft`, and `cancelRfqDraft`; generated schemas include `Rfq`, `RfqUpdateRequest`, and `RfqCancelRequest`.

- [ ] **Step 4: Commit contract**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "Add RFQ draft API contract"
```

---

### Task 5: Frontend RFQ API, Types, Hooks, And Mocks

**Files:**
- Create: `apps/web/features/sourcing/api/rfq-api.ts`
- Create: `apps/web/features/sourcing/hooks/use-rfq-draft.ts`
- Create: `apps/web/features/sourcing/hooks/use-rfq-draft-actions.ts`
- Create: `apps/web/features/sourcing/schemas/rfq-draft-schema.ts`
- Create: `apps/web/features/sourcing/types/rfq-view-model.ts`
- Create: `apps/web/features/sourcing/mocks/rfq-fixtures.ts`
- Create: `apps/web/features/sourcing/mocks/rfq-handlers.ts`
- Modify: `apps/web/tests/msw/handlers.ts`

- [ ] **Step 1: Add schemas and view model types**

Create `apps/web/features/sourcing/schemas/rfq-draft-schema.ts`:

```ts
import { z } from "zod";

export const rfqRequiredDocumentSchema = z.object({
  key: z.string().min(1),
  label: z.string().min(1),
  required: z.boolean(),
});

export const rfqLineItemSchema = z.object({
  description: z.string().min(1),
  quantity: z.coerce.number().positive(),
  unit: z.string().min(1),
  notes: z.string().nullable().optional(),
});

export const rfqDraftFormSchema = z.object({
  title: z.string().min(3),
  scopeSummary: z.string().nullable(),
  responseDueAt: z.string().nullable(),
  responseInstructions: z.string().nullable(),
  requiredDocuments: z.array(rfqRequiredDocumentSchema),
  lineItems: z.array(rfqLineItemSchema),
  evaluationNotes: z.string().nullable(),
  internalNotes: z.string().nullable(),
});

export const rfqCancelSchema = z.object({
  cancelReason: z.string().min(5),
});

export type RfqDraftFormValues = z.infer<typeof rfqDraftFormSchema>;
export type RfqCancelValues = z.infer<typeof rfqCancelSchema>;
```

Create `apps/web/features/sourcing/types/rfq-view-model.ts`:

```ts
import type { Rfq } from "@cognify/api-client/schemas";

export type RfqDraft = Rfq;
export type RfqStatus = Rfq["status"];
```

- [ ] **Step 2: Add RFQ API wrapper**

Create `apps/web/features/sourcing/api/rfq-api.ts`:

```ts
import {
  cancelRfqDraft as cancelRfqDraftEndpoint,
  createSourcingIntakeRfq as createSourcingIntakeRfqEndpoint,
  getRfq as getRfqEndpoint,
  updateRfqDraft as updateRfqDraftEndpoint,
} from "@cognify/api-client/endpoints";
import type { RfqCancelRequest, RfqUpdateRequest } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import type { RfqCancelValues, RfqDraftFormValues } from "../schemas/rfq-draft-schema";

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;

  return { headers: { "X-Tenant-Id": tenantId } };
}

export async function fetchRfqDraft(rfqId: string) {
  const response = await getRfqEndpoint(rfqId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data;
}

export async function createRfqDraftFromIntake(reviewId: string) {
  const response = await createSourcingIntakeRfqEndpoint(reviewId, withActiveTenantHeader());
  if (response.status !== 200 && response.status !== 201) throw response.data;
  return response.data.data;
}

export async function saveRfqDraft(rfqId: string, values: RfqDraftFormValues) {
  const request = values satisfies RfqUpdateRequest;
  const response = await updateRfqDraftEndpoint(rfqId, request, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data;
}

export async function cancelRfqDraft(rfqId: string, values: RfqCancelValues) {
  const request = values satisfies RfqCancelRequest;
  const response = await cancelRfqDraftEndpoint(rfqId, request, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data;
}
```

- [ ] **Step 3: Add hooks**

Create `apps/web/features/sourcing/hooks/use-rfq-draft.ts`:

```ts
import { useQuery } from "@tanstack/react-query";
import { fetchRfqDraft } from "../api/rfq-api";

export const rfqDraftKeys = {
  detail: (rfqId: string) => ["sourcing", "rfq", rfqId] as const,
};

export function useRfqDraft(rfqId: string) {
  return useQuery({
    queryKey: rfqDraftKeys.detail(rfqId),
    queryFn: () => fetchRfqDraft(rfqId),
  });
}
```

Create `apps/web/features/sourcing/hooks/use-rfq-draft-actions.ts`:

```ts
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { cancelRfqDraft, createRfqDraftFromIntake, saveRfqDraft } from "../api/rfq-api";
import type { RfqCancelValues, RfqDraftFormValues } from "../schemas/rfq-draft-schema";
import { rfqDraftKeys } from "./use-rfq-draft";
import { sourcingIntakeKeys } from "./use-sourcing-intake-review";

export function useCreateRfqDraftFromIntake(reviewId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => createRfqDraftFromIntake(reviewId),
    onSuccess: (rfq) => {
      queryClient.setQueryData(rfqDraftKeys.detail(rfq.id), rfq);
      void queryClient.invalidateQueries({ queryKey: sourcingIntakeKeys.detail(reviewId) });
    },
  });
}

export function useSaveRfqDraft(rfqId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: RfqDraftFormValues) => saveRfqDraft(rfqId, values),
    onSuccess: (rfq) => {
      queryClient.setQueryData(rfqDraftKeys.detail(rfq.id), rfq);
    },
  });
}

export function useCancelRfqDraft(rfqId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: RfqCancelValues) => cancelRfqDraft(rfqId, values),
    onSuccess: (rfq) => {
      queryClient.setQueryData(rfqDraftKeys.detail(rfq.id), rfq);
    },
  });
}
```

- [ ] **Step 4: Add MSW fixtures and handlers**

Create `apps/web/features/sourcing/mocks/rfq-fixtures.ts`:

```ts
import type { Rfq } from "@cognify/api-client/schemas";

export const rfqDraftFixture: Rfq = {
  id: "rfq-1",
  tenantId: "tenant-1",
  number: "RFQ-2026-0001",
  title: "Field laptop refresh RFQ",
  status: "draft",
  scopeSummary: "Competitive sourcing required.",
  responseDueAt: "2026-06-30T17:00:00.000000Z",
  responseInstructions: "Submit pricing, warranty, and delivery terms.",
  requiredDocuments: [{ key: "company_profile", label: "Company profile", required: true }],
  lineItems: [{ description: "Laptop", quantity: 10, unit: "each", notes: "16GB RAM minimum" }],
  evaluationNotes: "Compare warranty and lead time.",
  internalNotes: "Invite vendors in the next slice.",
  cancelReason: null,
  cancelledAt: null,
  createdAt: "2026-05-19T09:00:00.000000Z",
  updatedAt: "2026-05-19T09:00:00.000000Z",
  intakeReview: {
    id: "sourcing-3",
    status: "ready_for_rfq",
    sourcingPath: "needs_rfq",
    decisionReason: "Competitive sourcing required.",
    assignedBuyer: { id: "buyer-1", name: "Priya Buyer", email: "priya@example.test" },
  },
  requisition: {
    id: "req-1",
    number: "REQ-2026-0001",
    title: "Field laptop refresh",
    status: "approved",
    department: "Operations",
    neededByDate: "2026-07-15",
    currency: "MYR",
    requester: { id: "user-1", name: "Rafi Requester", email: "rafi@example.test" },
  },
  project: { id: "project-1", number: "PRJ-2026-0001", name: "Field enablement", status: "active" },
  permissions: { canUpdate: true, canCancel: true, canInviteVendors: false },
};
```

Create `apps/web/features/sourcing/mocks/rfq-handlers.ts`:

```ts
import { http, HttpResponse } from "msw";
import type { Rfq } from "@cognify/api-client/schemas";
import { rfqDraftFixture } from "./rfq-fixtures";

let rfqs = new Map<string, Rfq>([[rfqDraftFixture.id, rfqDraftFixture]]);

export function resetRfqMockState() {
  rfqs = new Map([[rfqDraftFixture.id, rfqDraftFixture]]);
}

export const rfqHandlers = [
  http.post("/api/sourcing/intake-reviews/:reviewId/rfq", ({ params }) => {
    if (params.reviewId !== "sourcing-3") {
      return HttpResponse.json({ error: { code: "conflict", message: "Review is not ready for RFQ." } }, { status: 409 });
    }

    return HttpResponse.json({ data: rfqDraftFixture }, { status: 201 });
  }),
  http.get("/api/rfqs/:rfqId", ({ params }) => {
    const rfq = rfqs.get(String(params.rfqId));
    if (!rfq) return HttpResponse.json({ error: { code: "not_found", message: "RFQ not found." } }, { status: 404 });
    return HttpResponse.json({ data: rfq });
  }),
  http.patch("/api/rfqs/:rfqId", async ({ params, request }) => {
    const existing = rfqs.get(String(params.rfqId));
    if (!existing) return HttpResponse.json({ error: { code: "not_found", message: "RFQ not found." } }, { status: 404 });
    if (existing.status === "cancelled") {
      return HttpResponse.json({ error: { code: "conflict", message: "Cancelled RFQs cannot be edited." } }, { status: 409 });
    }
    const body = await request.json() as Partial<Rfq>;
    const updated = { ...existing, ...body, updatedAt: "2026-05-19T10:00:00.000000Z" };
    rfqs.set(existing.id, updated);
    return HttpResponse.json({ data: updated });
  }),
  http.post("/api/rfqs/:rfqId/cancel", async ({ params, request }) => {
    const existing = rfqs.get(String(params.rfqId));
    if (!existing) return HttpResponse.json({ error: { code: "not_found", message: "RFQ not found." } }, { status: 404 });
    const body = await request.json() as { cancelReason?: string };
    if (!body.cancelReason) {
      return HttpResponse.json({ error: { code: "validation_failed", message: "Cancel reason is required." } }, { status: 422 });
    }
    const cancelled = { ...existing, status: "cancelled" as const, cancelReason: body.cancelReason, cancelledAt: "2026-05-19T11:00:00.000000Z", permissions: { ...existing.permissions, canUpdate: false, canCancel: false } };
    rfqs.set(existing.id, cancelled);
    return HttpResponse.json({ data: cancelled });
  }),
];
```

Modify `apps/web/tests/msw/handlers.ts`:

```ts
import { rfqHandlers } from "@/features/sourcing/mocks/rfq-handlers";

export const handlers = [
  // existing handlers...
  ...rfqHandlers,
];
```

- [ ] **Step 5: Run typecheck for new web API layer**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: type errors may exist if generated schemas need naming adjustments; fix wrapper imports to match generated names before continuing.

---

### Task 6: RFQ Draft Workspace UI

**Files:**
- Create: `apps/web/app/(workspace)/sourcing/rfqs/[rfqId]/page.tsx`
- Create: `apps/web/features/sourcing/components/rfq-status-badge.tsx`
- Create: `apps/web/features/sourcing/components/rfq-line-items-table.tsx`
- Create: `apps/web/features/sourcing/components/rfq-required-documents-editor.tsx`
- Create: `apps/web/features/sourcing/components/rfq-draft-form.tsx`
- Create: `apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`

- [ ] **Step 1: Add route page**

Create `apps/web/app/(workspace)/sourcing/rfqs/[rfqId]/page.tsx`:

```tsx
import { RfqDraftWorkspace } from "@/features/sourcing/workflows/rfq-draft-workspace";

export default function RfqDraftPage({ params }: { params: { rfqId: string } }) {
  return <RfqDraftWorkspace rfqId={params.rfqId} />;
}
```

- [ ] **Step 2: Add focused display components**

Create `apps/web/features/sourcing/components/rfq-status-badge.tsx`:

```tsx
import { Badge } from "@cognify/ui";
import type { RfqStatus } from "../types/rfq-view-model";

const labels: Record<RfqStatus, string> = {
  draft: "Draft",
  cancelled: "Cancelled",
};

export function RfqStatusBadge({ status }: { status: RfqStatus }) {
  return <Badge variant={status === "cancelled" ? "secondary" : "default"}>{labels[status]}</Badge>;
}
```

Create `apps/web/features/sourcing/components/rfq-line-items-table.tsx`:

```tsx
import type { RfqLineItem } from "@cognify/api-client/schemas";

export function RfqLineItemsTable({ items }: { items: RfqLineItem[] }) {
  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 text-left">
          <tr>
            <th className="px-3 py-2">Description</th>
            <th className="px-3 py-2">Quantity</th>
            <th className="px-3 py-2">Unit</th>
            <th className="px-3 py-2">Notes</th>
          </tr>
        </thead>
        <tbody>
          {items.map((item, index) => (
            <tr key={`${item.description}-${index}`} className="border-t">
              <td className="px-3 py-2">{item.description}</td>
              <td className="px-3 py-2">{item.quantity}</td>
              <td className="px-3 py-2">{item.unit}</td>
              <td className="px-3 py-2">{item.notes ?? "-"}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
```

Create `apps/web/features/sourcing/components/rfq-required-documents-editor.tsx`:

```tsx
import type { RfqRequiredDocument } from "@cognify/api-client/schemas";

export function RfqRequiredDocumentsEditor({ documents }: { documents: RfqRequiredDocument[] }) {
  return (
    <ul className="space-y-2">
      {documents.length === 0 ? <li className="text-sm text-muted-foreground">No required documents added yet.</li> : null}
      {documents.map((document) => (
        <li key={document.key} className="rounded-md border p-3 text-sm">
          <span className="font-medium">{document.label}</span>
          <span className="ml-2 text-muted-foreground">{document.required ? "Required" : "Optional"}</span>
        </li>
      ))}
    </ul>
  );
}
```

- [ ] **Step 3: Add draft form**

Create `apps/web/features/sourcing/components/rfq-draft-form.tsx`:

```tsx
"use client";

import { useForm } from "react-hook-form";
import { Button, Input, Textarea } from "@cognify/ui";
import { useSaveRfqDraft } from "../hooks/use-rfq-draft-actions";
import type { RfqDraftFormValues } from "../schemas/rfq-draft-schema";
import type { RfqDraft } from "../types/rfq-view-model";

export function RfqDraftForm({ rfq }: { rfq: RfqDraft }) {
  const mutation = useSaveRfqDraft(rfq.id);
  const form = useForm<RfqDraftFormValues>({
    defaultValues: {
      title: rfq.title,
      scopeSummary: rfq.scopeSummary ?? "",
      responseDueAt: rfq.responseDueAt ?? "",
      responseInstructions: rfq.responseInstructions ?? "",
      requiredDocuments: rfq.requiredDocuments,
      lineItems: rfq.lineItems,
      evaluationNotes: rfq.evaluationNotes ?? "",
      internalNotes: rfq.internalNotes ?? "",
    },
  });
  const readOnly = !rfq.permissions.canUpdate;

  return (
    <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
      <label className="block space-y-1.5 text-sm font-medium">
        Title
        <Input disabled={readOnly} {...form.register("title")} />
      </label>
      <label className="block space-y-1.5 text-sm font-medium">
        Scope summary
        <Textarea disabled={readOnly} {...form.register("scopeSummary")} />
      </label>
      <label className="block space-y-1.5 text-sm font-medium">
        Response due
        <Input disabled={readOnly} type="datetime-local" {...form.register("responseDueAt")} />
      </label>
      <label className="block space-y-1.5 text-sm font-medium">
        Response instructions
        <Textarea disabled={readOnly} {...form.register("responseInstructions")} />
      </label>
      <label className="block space-y-1.5 text-sm font-medium">
        Evaluation notes
        <Textarea disabled={readOnly} {...form.register("evaluationNotes")} />
      </label>
      <label className="block space-y-1.5 text-sm font-medium">
        Internal notes
        <Textarea disabled={readOnly} {...form.register("internalNotes")} />
      </label>
      {mutation.isError ? <p className="text-sm text-red-700">RFQ draft could not be saved. Refresh and try again.</p> : null}
      <Button type="submit" disabled={readOnly || mutation.isPending}>{mutation.isPending ? "Saving" : "Save RFQ draft"}</Button>
    </form>
  );
}
```

- [ ] **Step 4: Add workspace**

Create `apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx`:

```tsx
"use client";

import Link from "next/link";
import { Button } from "@cognify/ui";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { useCancelRfqDraft } from "../hooks/use-rfq-draft-actions";
import { useRfqDraft } from "../hooks/use-rfq-draft";
import { RfqDraftForm } from "../components/rfq-draft-form";
import { RfqLineItemsTable } from "../components/rfq-line-items-table";
import { RfqRequiredDocumentsEditor } from "../components/rfq-required-documents-editor";
import { RfqStatusBadge } from "../components/rfq-status-badge";

export function RfqDraftWorkspace({ rfqId }: { rfqId: string }) {
  const rfqQuery = useRfqDraft(rfqId);
  const cancelMutation = useCancelRfqDraft(rfqId);
  const rfq = rfqQuery.data;

  if (rfqQuery.isLoading) {
    return <div aria-label="Loading RFQ" className="rounded-md border p-4 text-sm text-muted-foreground">Loading RFQ</div>;
  }

  if (rfqQuery.isError || !rfq) {
    return <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">Unable to load RFQ draft.</div>;
  }

  return (
    <RecordWorkspaceLayout
      backHref={`/sourcing/intake/${rfq.intakeReview.id}`}
      backLabel="Back to intake review"
      eyebrow={rfq.number}
      title={rfq.title}
      status={<RfqStatusBadge status={rfq.status} />}
      metadata={[
        { id: "requisition", label: "Requisition", value: rfq.requisition.number },
        { id: "requester", label: "Requester", value: rfq.requisition.requester?.name ?? "Unknown" },
        { id: "project", label: "Project", value: rfq.project?.name ?? "No project" },
      ]}
      sections={[
        { id: "summary", label: "Summary" },
        { id: "line-items", label: "Line items" },
        { id: "documents", label: "Documents" },
        { id: "vendors", label: "Vendors" },
      ]}
      primaryActions={
        rfq.permissions.canCancel ? (
          <Button variant="outline" disabled={cancelMutation.isPending} onClick={() => cancelMutation.mutate({ cancelReason: "Cancelled by buyer before vendor invitation." })}>
            {cancelMutation.isPending ? "Cancelling" : "Cancel draft"}
          </Button>
        ) : null
      }
      sidebar={<RfqDraftForm rfq={rfq} />}
    >
      <section id="summary" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Source summary</h2>
        <dl className="mt-3 grid gap-3 sm:grid-cols-2">
          <div><dt className="text-sm text-muted-foreground">Requisition</dt><dd>{rfq.requisition.title}</dd></div>
          <div><dt className="text-sm text-muted-foreground">Department</dt><dd>{rfq.requisition.department ?? "Not set"}</dd></div>
          <div><dt className="text-sm text-muted-foreground">Needed by</dt><dd>{rfq.requisition.neededByDate ?? "Not set"}</dd></div>
          <div><dt className="text-sm text-muted-foreground">Intake review</dt><dd><Link className="underline" href={`/sourcing/intake/${rfq.intakeReview.id}`}>{rfq.intakeReview.status.replaceAll("_", " ")}</Link></dd></div>
        </dl>
      </section>

      <section id="line-items" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">RFQ line items</h2>
        <div className="mt-3"><RfqLineItemsTable items={rfq.lineItems} /></div>
      </section>

      <section id="documents" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Required documents</h2>
        <div className="mt-3"><RfqRequiredDocumentsEditor documents={rfq.requiredDocuments} /></div>
      </section>

      <section id="vendors" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Vendor invitations</h2>
        <p className="mt-2 text-sm text-muted-foreground">Vendor invitations are handled in the next Epic 5 slice.</p>
      </section>
    </RecordWorkspaceLayout>
  );
}
```

- [ ] **Step 5: Add shell breadcrumbs**

Modify `apps/web/components/shell/shell-route-config.ts`:

```ts
if (/^\/sourcing\/rfqs\/[^/]+$/.test(normalizedPathname)) {
  return [{ label: "Sourcing intake", href: "/sourcing/intake" }, { label: "RFQ draft" }];
}
```

- [ ] **Step 6: Run web typecheck**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: passes after matching component imports to local `@cognify/ui` exports.

---

### Task 7: Wire Intake Handoff And Web Tests

**Files:**
- Modify: `apps/web/features/sourcing/workflows/sourcing-intake-detail-page.tsx`
- Modify: `apps/web/features/sourcing/mocks/sourcing-handlers.ts`
- Test: `apps/web/features/sourcing/tests/rfq-draft-workflow.test.tsx`
- Modify: `apps/web/features/sourcing/tests/sourcing-intake-workflow.test.tsx`

- [ ] **Step 1: Replace disabled Create RFQ handoff**

Modify `apps/web/features/sourcing/workflows/sourcing-intake-detail-page.tsx`:

```tsx
import { useRouter } from "next/navigation";
import { useCreateRfqDraftFromIntake } from "../hooks/use-rfq-draft-actions";
```

Inside the component:

```tsx
const router = useRouter();
const createRfqMutation = useCreateRfqDraftFromIntake(reviewId);

async function handleCreateRfq() {
  const rfq = await createRfqMutation.mutateAsync();
  router.push(`/sourcing/rfqs/${rfq.id}`);
}
```

Replace the disabled button:

```tsx
{review.permissions.canCreateRfq ? (
  <Button onClick={handleCreateRfq} disabled={createRfqMutation.isPending}>
    {createRfqMutation.isPending ? "Creating RFQ" : "Create RFQ"}
  </Button>
) : null}
```

In the handoff section, replace the old copy with:

```tsx
<p className="mt-2 text-sm text-muted-foreground">This review is ready for RFQ drafting. Create or reveal the draft RFQ to shape the sourcing package before vendor invitations.</p>
```

- [ ] **Step 2: Ensure mocks have a ready review**

In `apps/web/features/sourcing/mocks/sourcing-handlers.ts`, ensure one fixture has:

```ts
status: "ready_for_rfq",
sourcingPath: "needs_rfq",
permissions: {
  canClaim: false,
  canReassign: true,
  canUpdate: false,
  canRecordDecision: false,
  canClose: true,
  canCreateRfq: true,
},
```

- [ ] **Step 3: Add RFQ workflow tests**

Create `apps/web/features/sourcing/tests/rfq-draft-workflow.test.tsx`:

```tsx
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
import { resetIdentityMockState } from "@/features/identity/mocks/identity-handlers";
import { resetRfqMockState } from "../mocks/rfq-handlers";
import { resetSourcingMockState } from "../mocks/sourcing-handlers";
import { RfqDraftWorkspace } from "../workflows/rfq-draft-workspace";
import { SourcingIntakeDetailPage } from "../workflows/sourcing-intake-detail-page";

const push = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
}));

function TestAppProviders({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });

  return (
    <QueryClientProvider client={queryClient}>
      <RightPanelProvider>
        {children}
        <RightPanelRoot />
      </RightPanelProvider>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  resetIdentityMockState();
  resetSourcingMockState();
  resetRfqMockState();
  window.localStorage.clear();
  push.mockClear();
});

describe("RFQ draft workflow", () => {
  it("creates RFQ draft from a ready intake review", async () => {
    const user = userEvent.setup();
    render(<SourcingIntakeDetailPage reviewId="sourcing-3" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: /Field laptop refresh/ })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Create RFQ" }));

    await waitFor(() => expect(push).toHaveBeenCalledWith("/sourcing/rfqs/rfq-1"));
  });

  it("renders RFQ draft workspace and saves edits", async () => {
    const user = userEvent.setup();
    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
    await user.clear(screen.getByLabelText("Title"));
    await user.type(screen.getByLabelText("Title"), "Updated laptop RFQ");
    await user.click(screen.getByRole("button", { name: "Save RFQ draft" }));

    await waitFor(() => expect(screen.getByDisplayValue("Updated laptop RFQ")).toBeInTheDocument());
  });

  it("cancels a draft and renders cancelled status", async () => {
    const user = userEvent.setup();
    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Cancel draft" }));

    await waitFor(() => expect(screen.getByText("Cancelled")).toBeInTheDocument());
  });
});
```

- [ ] **Step 4: Update prior intake test expectation**

Modify the existing test `marks review ready for RFQ without creating an RFQ` in `apps/web/features/sourcing/tests/sourcing-intake-workflow.test.tsx` so it expects an enabled create button for ready reviews:

```tsx
expect(screen.getByRole("button", { name: "Create RFQ" })).toBeEnabled();
```

If that test uses a review that is just transitioned by MSW and does not have `canCreateRfq: true`, update the mock transition response to set `permissions.canCreateRfq` to true when status becomes `ready_for_rfq`.

- [ ] **Step 5: Run web tests and commit frontend**

Run:

```bash
pnpm --filter @cognify/web test -- sourcing
pnpm --filter @cognify/web typecheck
```

Expected: sourcing tests and typecheck pass.

Commit:

```bash
git add apps/web/app/(workspace)/sourcing/rfqs apps/web/features/sourcing apps/web/tests/msw/handlers.ts apps/web/components/shell/shell-route-config.ts
git commit -m "Add RFQ draft workspace"
```

---

### Task 8: Final Verification And PR Readiness

**Files:**
- Review all changed files
- Commit any verification fixes

- [ ] **Step 1: Run API checks**

```bash
cd apps/api && php artisan test tests/Feature/RfqDraftApiTest.php tests/Feature/SourcingIntakeApiTest.php
```

Expected: all tests pass.

- [ ] **Step 2: Run contract checks**

```bash
pnpm check:api-contract
```

Expected: passes. If generated files change, inspect and commit them.

- [ ] **Step 3: Run web checks**

```bash
pnpm --filter @cognify/web test -- sourcing
pnpm --filter @cognify/web typecheck
```

Expected: all tests and typecheck pass.

- [ ] **Step 4: Run PR build check**

```bash
pnpm build
```

Expected: build passes. This is required because every PR now has an automated build workflow.

- [ ] **Step 5: Run whitespace check**

```bash
git diff --check
```

Expected: no output.

- [ ] **Step 6: Review scope boundary**

Run:

```bash
rg -n "invite|invitation|vendor portal|quotation upload|comparison|award|purchase order|publish" apps/api/Domains/Quotation apps/web/features/sourcing apps/api/storage/openapi/openapi.json docs/superpowers/plans/2026-05-19-rfq-draft-creation.md
```

Expected: hits are only non-actionable placeholders, explicit non-goals, future permission names such as `canInviteVendors: false`, or existing unrelated model names. No vendor invitation workflow, quotation capture workflow, award workflow, or publish behavior should be implemented.

- [ ] **Step 7: Final commit**

If verification required fixes:

```bash
git add <changed-files>
git commit -m "Harden RFQ draft creation"
```

If no fixes were needed, leave the existing task commits as-is.

- [ ] **Step 8: Prepare PR summary**

Summarize:

- Workflow slice added: RFQ draft creation from sourcing intake.
- Actors/states/transitions: buyer/admin, `ready_for_rfq`, `draft`, `cancelled`.
- API contracts changed and generated client regenerated.
- Tests/checks passed, including `pnpm build`.

---

## Self-Review Notes

- Spec coverage: The plan covers draft creation/reveal, show/update/cancel, tenant and permission rules, audit events, RFQ workspace, intake handoff, MSW tests, generated client usage, and `pnpm build`.
- Scope check: Vendor invitations and publish behavior remain out of scope. The only vendor-related UI is a non-actionable future section and `canInviteVendors: false`.
- Placeholder scan: No unresolved placeholder instructions are left. OpenAPI common response names may need alignment with the existing JSON structure during implementation, and the task explicitly instructs using the existing style if names differ.
