# Award Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-33 so a buyer/admin can route a submitted RFQ award recommendation through the existing Approval domain, approvers can decide it from the existing approval queue/task screens, and the recommendation records approved, rejected, or changes-requested outcomes.

**Architecture:** Generalize `apps/api/Domains/Approval` around a small subject-handler contract while preserving requisition behavior through a requisition handler. Keep award recommendation state transitions in `apps/api/Domains/Quotation`, expose tenant-scoped OpenAPI endpoints, regenerate `@cognify/api-client`, and extend existing quotation and approval workspaces instead of creating a parallel award-approval system.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped Approval and Quotation domain actions, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix via `packages/ui`.

---

## Grounding

- Design spec: `docs/superpowers/specs/2026-05-26-award-approval-design.md`.
- Roadmap: `docs/01-product/feature-roadmap.md` feature `P1-33`.
- Architecture: `ARCHITECTURE.md` requires tenant isolation, backend-owned workflow behavior, OpenAPI-generated clients, and feature UI in `apps/web`.
- Runbook: `docs/05-runbooks/feature-development.md` requires workflow-first, contract-first vertical slices.
- Predecessor implementation: `docs/superpowers/plans/2026-05-26-recommendation-award-decision.md` created `RfqAwardRecommendation`, `/quotations/awards/[rfqId]`, and the draft/submit/withdraw workflow.
- Existing approval code to preserve: `apps/api/Domains/Approval/Actions/RouteRequisitionForApproval.php`, `ApproveApprovalTask.php`, `RejectApprovalTask.php`, `RequestApprovalChanges.php`, `ApprovalTaskResource.php`, and `ApprovalTaskController.php`.

## Scope Boundaries

Implement:

- Subject-aware approval routing through a new `RouteSubjectForApproval` action.
- Approval subject handlers for requisitions and RFQ award recommendations.
- `rfq_award_recommendation` policy matching, preview, route, summary, task resource, queue filtering, task detail context, audit, and notification labeling.
- Award recommendation states and approval metadata for `approval_routed`, `approved`, `rejected`, and `changes_requested`.
- Award recommendation domain actions called by Approval subject handlers.
- OpenAPI schemas/endpoints and generated client updates.
- Web route/summary/preview API wrappers and hooks.
- Award workspace approval panel and route action.
- Approval queue and task detail UI support for award recommendation tasks.
- Focused API and web regression tests, including requisition approval preservation.

Do not implement:

- RFQ awarded state, vendor award/regret notifications, purchase-order handoff, ERP export, split awards, line-level award approvals, a new standalone `Award` domain, AI-generated approval decisions, or award resubmission/reopen flows after rejection or requested changes.

## File Map

### API Approval Domain

- Create: `apps/api/Domains/Approval/Contracts/ApprovalSubjectHandler.php`
- Create: `apps/api/Domains/Approval/Services/ApprovalSubjectRegistry.php`
- Create: `apps/api/Domains/Approval/Support/ApprovalSubjectSummary.php`
- Create: `apps/api/Domains/Approval/SubjectHandlers/RequisitionApprovalSubjectHandler.php`
- Create: `apps/api/Domains/Approval/SubjectHandlers/RfqAwardRecommendationApprovalSubjectHandler.php`
- Create: `apps/api/Domains/Approval/Actions/RouteSubjectForApproval.php`
- Modify: `apps/api/Domains/Approval/Actions/RouteRequisitionForApproval.php`
- Modify: `apps/api/Domains/Approval/Actions/ApproveApprovalTask.php`
- Modify: `apps/api/Domains/Approval/Actions/RejectApprovalTask.php`
- Modify: `apps/api/Domains/Approval/Actions/RequestApprovalChanges.php`
- Modify: `apps/api/Domains/Approval/Data/ApprovalContextData.php`
- Modify: `apps/api/Domains/Approval/Http/Controllers/ApprovalTaskController.php`
- Modify: `apps/api/Domains/Approval/Http/Controllers/RequisitionApprovalController.php`
- Modify: `apps/api/Domains/Approval/Http/Resources/ApprovalTaskResource.php`
- Modify: `apps/api/Domains/Approval/Http/Resources/ApprovalPreviewResource.php`
- Modify: `apps/api/Domains/Approval/Http/Resources/ApprovalSummaryResource.php`
- Modify: `apps/api/Domains/Approval/Services/ApprovalPolicyMatcher.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php` if subject handlers need explicit singleton bindings.

### API Quotation Domain

- Create: `apps/api/database/migrations/2026_05_26_100000_add_approval_metadata_to_rfq_award_recommendations_table.php`
- Create: `apps/api/Domains/Quotation/Actions/BuildRfqAwardApprovalPreview.php`
- Create: `apps/api/Domains/Quotation/Actions/BuildRfqAwardApprovalSummary.php`
- Create: `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApprovalRouted.php`
- Create: `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApproved.php`
- Create: `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationRejected.php`
- Create: `apps/api/Domains/Quotation/Actions/RequestRfqAwardRecommendationChanges.php`
- Modify: `apps/api/Domains/Quotation/Models/RfqAwardRecommendation.php`
- Modify: `apps/api/Domains/Quotation/States/RfqAwardRecommendationStatus.php`
- Modify: `apps/api/Domains/Quotation/Http/Controllers/RfqAwardRecommendationController.php`
- Modify: `apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationContextResource.php`
- Modify: `apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationResource.php`
- Modify: `apps/api/Domains/Quotation/Policies/RfqAwardRecommendationPolicy.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`

### API Tests

- Create: `apps/api/tests/Feature/RfqAwardApprovalApiTest.php`
- Modify: `apps/api/tests/Feature/ApprovalTaskApiTest.php`
- Modify: `apps/api/tests/Feature/ApprovalPreviewApiTest.php`
- Modify: `apps/api/tests/Feature/RfqAwardRecommendationApiTest.php`

### Generated Client

- Modify generated files under `packages/api-client/src/generated/**` via `pnpm generate:api` and `pnpm check:api-contract`.

Expected new operation IDs:

- `routeRfqAwardRecommendationForApproval`
- `getRfqAwardRecommendationApprovalSummary`
- `previewRfqAwardRecommendationApproval`

Expected new or changed schemas:

- `ApprovalSubjectSummary`
- `ApprovalTaskSubject`
- `ApprovalAwardRecommendationSubjectMetadata`
- `ApprovalPreviewContext`
- `ApprovalSummary`
- `RfqAwardApprovalPreviewResponse`
- `RfqAwardApprovalRouteResponse`
- `RfqAwardRecommendationApprovalSummaryResponse`

### Web

- Modify: `apps/web/features/approvals/api/approvals-api.ts`
- Modify: `apps/web/features/approvals/hooks/use-approval-tasks.ts`
- Modify: `apps/web/features/approvals/tables/approval-tasks-table.tsx`
- Modify: `apps/web/features/approvals/types/approval-view-model.ts`
- Modify: `apps/web/features/approvals/workflows/approval-queue-page.tsx`
- Modify: `apps/web/features/approvals/workflows/approval-task-detail-page.tsx`
- Modify: `apps/web/features/approvals/mocks/approval-fixtures.ts`
- Modify: `apps/web/features/approvals/mocks/approval-handlers.ts`
- Modify: `apps/web/features/approvals/tests/approval-queue-workflow.test.tsx`
- Modify: `apps/web/features/approvals/tests/approval-task-actions.test.tsx`
- Create: `apps/web/features/approvals/tests/approval-award-task-detail.test.tsx`
- Modify: `apps/web/features/quotations/api/quotation-award-recommendation-api.ts`
- Modify: `apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts`
- Modify: `apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-approval-panel.tsx`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts`
- Modify: `apps/web/features/quotations/tests/quotation-award-recommendation-api.test.ts`
- Modify: `apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx`

### Docs

- Modify: `docs/01-product/feature-roadmap.md` to mark P1-33 implemented only after all verification passes.
- Modify this plan during execution by checking completed boxes.

---

## Task 1: API Regression Tests For Award Approval Behavior

**Files:**

- Create: `apps/api/tests/Feature/RfqAwardApprovalApiTest.php`
- Modify: `apps/api/tests/Feature/ApprovalTaskApiTest.php`
- Modify: `apps/api/tests/Feature/RfqAwardRecommendationApiTest.php`

- [ ] **Step 1: Write failing award approval feature tests**

Create `apps/api/tests/Feature/RfqAwardApprovalApiTest.php`. Reuse tenant/auth helpers and RFQ fixture patterns from `RfqAwardRecommendationApiTest` and approval policy setup patterns from `ApprovalTaskApiTest`.

The file must include these scenario names:

```php
public function test_buyer_can_preview_award_recommendation_approval_route(): void
public function test_buyer_can_route_pending_award_recommendation_for_approval(): void
public function test_routing_pending_award_recommendation_is_idempotent(): void
public function test_non_pending_award_recommendation_cannot_be_routed(): void
public function test_missing_award_approval_policy_returns_matching_error(): void
public function test_approval_summary_returns_active_and_completed_award_route_state(): void
public function test_approving_final_award_task_marks_recommendation_approved(): void
public function test_rejecting_award_task_marks_recommendation_rejected(): void
public function test_requesting_changes_marks_recommendation_changes_requested(): void
public function test_award_approval_task_resource_contains_award_subject_summary(): void
public function test_award_approval_queue_supports_subject_type_filter(): void
public function test_cross_tenant_route_summary_show_and_action_attempts_fail(): void
public function test_award_approval_records_audit_events_and_assignment_notifications(): void
public function test_award_approval_routes_require_real_session_auth_and_tenant_context(): void
```

Use assertions with this shape:

```php
$this->actingAsTenant($tenant, $buyer)
    ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-preview")
    ->assertOk()
    ->assertJsonPath('data.context.subjectType', 'rfq_award_recommendation')
    ->assertJsonPath('data.context.rfqId', (string) $rfq->id)
    ->assertJsonPath('data.context.recommendedVendorId', (string) $vendor->id)
    ->assertJsonPath('data.stages.0.name', 'Commercial approval');

$this->actingAsTenant($tenant, $buyer)
    ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
    ->assertOk()
    ->assertJsonPath('data.status', 'active')
    ->assertJsonPath('data.currentStage.name', 'Commercial approval')
    ->assertJsonPath('data.activeApprovers.0.name', $approver->name);

$this->assertDatabaseHas('rfq_award_recommendations', [
    'id' => $recommendation->id,
    'tenant_id' => $tenant->id,
    'status' => 'approval_routed',
]);

$task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

$this->actingAsTenant($tenant, $approver)
    ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
    ->assertOk()
    ->assertJsonPath('data.subject.type', 'rfq_award_recommendation')
    ->assertJsonPath('data.subject.primaryParty', $vendor->name);

$this->assertDatabaseHas('rfq_award_recommendations', [
    'id' => $recommendation->id,
    'status' => 'approved',
    'approved_by_user_id' => $approver->id,
]);
```

The real route-stack test must use:

```txt
POST /api/auth/login
POST /api/rfqs/{rfq}/award-recommendation/approval-route
GET /api/rfqs/{rfq}/award-recommendation/approval-summary
POST /api/auth/logout
GET /api/rfqs/{rfq}/award-recommendation/approval-summary
```

Expected after logout: `401`.

- [ ] **Step 2: Add requisition preservation assertions**

Extend `apps/api/tests/Feature/ApprovalTaskApiTest.php` with:

```php
public function test_requisition_approval_still_routes_and_approves_after_subject_handler_refactor(): void
```

Assert that requisition route, task summary, approve, reject, request-changes, sibling cancellation, notification href, and `RequisitionStatus` outcomes remain unchanged.

- [ ] **Step 3: Add award read-only assertions to recommendation tests**

Extend `apps/api/tests/Feature/RfqAwardRecommendationApiTest.php` with:

```php
public function test_approval_routed_and_decided_recommendations_are_read_only_for_draft_save(): void
```

Assert draft save fails with `409` for `approval_routed`, `approved`, `rejected`, and `changes_requested`.

- [ ] **Step 4: Run failing API tests**

Run:

```bash
cd apps/api
php artisan test --filter=RfqAwardApprovalApiTest
php artisan test --filter=ApprovalTaskApiTest
php artisan test --filter=RfqAwardRecommendationApiTest
```

Expected: `RfqAwardApprovalApiTest` fails with missing routes/classes or old requisition-only subject shapes. Existing tests should still run and expose the read-only/status gaps added above.

- [ ] **Step 5: Commit failing tests**

Run:

```bash
git add apps/api/tests/Feature/RfqAwardApprovalApiTest.php apps/api/tests/Feature/ApprovalTaskApiTest.php apps/api/tests/Feature/RfqAwardRecommendationApiTest.php
git commit -m "test: define RFQ award approval workflow"
```

Expected: commit contains only failing test changes.

---

## Task 2: Award Recommendation Approval State And Metadata

**Files:**

- Create: `apps/api/database/migrations/2026_05_26_100000_add_approval_metadata_to_rfq_award_recommendations_table.php`
- Modify: `apps/api/Domains/Quotation/Models/RfqAwardRecommendation.php`
- Modify: `apps/api/Domains/Quotation/States/RfqAwardRecommendationStatus.php`
- Modify: `apps/api/Domains/Quotation/Actions/SaveRfqAwardRecommendation.php`
- Modify: `apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationResource.php`

- [ ] **Step 1: Add approval metadata migration**

Create `apps/api/database/migrations/2026_05_26_100000_add_approval_metadata_to_rfq_award_recommendations_table.php`:

```php
<?php

use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_award_recommendations', function (Blueprint $table): void {
            $table->foreignIdFor(ApprovalInstance::class, 'approval_instance_id')->nullable()->after('status')->constrained('approval_instances')->nullOnDelete();
            $table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->after('withdrawn_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->foreignIdFor(User::class, 'rejected_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            $table->text('decision_reason')->nullable()->after('rejected_at');
            $table->foreignIdFor(User::class, 'changes_requested_by_user_id')->nullable()->after('decision_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('changes_requested_at')->nullable()->after('changes_requested_by_user_id');
            $table->text('changes_requested_reason')->nullable()->after('changes_requested_at');
            $table->json('changes_requested_fields')->nullable()->after('changes_requested_reason');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_award_recommendations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approval_instance_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejected_at', 'decision_reason']);
            $table->dropConstrainedForeignId('changes_requested_by_user_id');
            $table->dropColumn(['changes_requested_at', 'changes_requested_reason', 'changes_requested_fields']);
        });
    }
};
```

- [ ] **Step 2: Extend recommendation status enum**

Update `apps/api/Domains/Quotation/States/RfqAwardRecommendationStatus.php`:

```php
enum RfqAwardRecommendationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case ApprovalRouted = 'approval_routed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
    case Withdrawn = 'withdrawn';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PendingApproval;
    }

    public function isTerminalForAwardApproval(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::ChangesRequested, self::Withdrawn], true);
    }
}
```

- [ ] **Step 3: Add model fillables, casts, and relations**

Update `RfqAwardRecommendation` with fillables and casts for every new metadata column, plus:

```php
public function approvalInstance(): BelongsTo
{
    return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
}

public function approvedByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'approved_by_user_id');
}

public function rejectedByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'rejected_by_user_id');
}

public function changesRequestedByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'changes_requested_by_user_id');
}
```

Casts must include:

```php
'approved_at' => 'datetime',
'rejected_at' => 'datetime',
'changes_requested_at' => 'datetime',
'changes_requested_fields' => 'array',
```

- [ ] **Step 4: Keep draft save read-only outside draft**

Update `SaveRfqAwardRecommendation` so it only updates an existing recommendation when `status === Draft`. If the current status is `pending_approval`, `approval_routed`, `approved`, `rejected`, `changes_requested`, or `withdrawn`, throw:

```php
throw new ConflictHttpException('Only draft award recommendations can be edited.');
```

- [ ] **Step 5: Expose approval metadata in resource**

Update `RfqAwardRecommendationResource` to include:

```php
'approvalInstanceId' => $this->approval_instance_id !== null ? (string) $this->approval_instance_id : null,
'approvedByUserId' => $this->approved_by_user_id !== null ? (string) $this->approved_by_user_id : null,
'approvedAt' => $this->approved_at?->toISOString(),
'rejectedByUserId' => $this->rejected_by_user_id !== null ? (string) $this->rejected_by_user_id : null,
'rejectedAt' => $this->rejected_at?->toISOString(),
'decisionReason' => $this->decision_reason,
'changesRequestedByUserId' => $this->changes_requested_by_user_id !== null ? (string) $this->changes_requested_by_user_id : null,
'changesRequestedAt' => $this->changes_requested_at?->toISOString(),
'changesRequestedReason' => $this->changes_requested_reason,
'changesRequestedFields' => $this->changes_requested_fields ?? [],
```

- [ ] **Step 6: Run focused recommendation tests**

Run:

```bash
cd apps/api
php artisan test --filter=RfqAwardRecommendationApiTest
```

Expected: new read-only assertions pass; approval routing tests still fail until routing is implemented.

- [ ] **Step 7: Commit recommendation state changes**

Run:

```bash
git add apps/api/database/migrations/2026_05_26_100000_add_approval_metadata_to_rfq_award_recommendations_table.php apps/api/Domains/Quotation/Models/RfqAwardRecommendation.php apps/api/Domains/Quotation/States/RfqAwardRecommendationStatus.php apps/api/Domains/Quotation/Actions/SaveRfqAwardRecommendation.php apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationResource.php
git commit -m "feat: add award approval state metadata"
```

---

## Task 3: Approval Subject Handler Contract And Requisition Adapter

**Files:**

- Create: `apps/api/Domains/Approval/Contracts/ApprovalSubjectHandler.php`
- Create: `apps/api/Domains/Approval/Services/ApprovalSubjectRegistry.php`
- Create: `apps/api/Domains/Approval/Support/ApprovalSubjectSummary.php`
- Create: `apps/api/Domains/Approval/SubjectHandlers/RequisitionApprovalSubjectHandler.php`
- Modify: `apps/api/Domains/Approval/Data/ApprovalContextData.php`
- Modify: `apps/api/Domains/Approval/Http/Resources/ApprovalTaskResource.php`

- [ ] **Step 1: Create subject summary value object**

Create `ApprovalSubjectSummary`:

```php
<?php

namespace Domains\Approval\Support;

final class ApprovalSubjectSummary
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly ?string $number,
        public readonly ?string $title,
        public readonly ?string $status,
        public readonly ?string $primaryParty,
        public readonly ?float $amount,
        public readonly ?string $currency,
        public readonly ?string $href,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'status' => $this->status,
            'primaryParty' => $this->primaryParty,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'href' => $this->href,
            'metadata' => $this->metadata,
        ];
    }
}
```

- [ ] **Step 2: Create subject handler contract**

Create `ApprovalSubjectHandler`:

```php
<?php

namespace Domains\Approval\Contracts;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Support\ApprovalSubjectSummary;
use Illuminate\Database\Eloquent\Model;

interface ApprovalSubjectHandler
{
    public function subjectType(): string;

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string;

    public function buildContext(Model $subject): ApprovalContextData;

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary;

    public function taskTitle(Model $subject): string;

    public function notificationSubjectLabel(Model $subject): ?string;

    public function notificationBody(Model $subject): string;

    public function onRouted(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void;

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void;

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void;

    /**
     * @param  array<int, string>  $requestedFields
     */
    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void;
}
```

- [ ] **Step 3: Create subject registry**

Create `ApprovalSubjectRegistry`:

```php
<?php

namespace Domains\Approval\Services;

use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ApprovalSubjectRegistry
{
    /** @var array<string, ApprovalSubjectHandler> */
    private array $bySubjectType = [];

    /** @var array<class-string<Model>, ApprovalSubjectHandler> */
    private array $byModelClass = [];

    /**
     * @param  iterable<int, ApprovalSubjectHandler>  $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->bySubjectType[$handler->subjectType()] = $handler;
            $this->byModelClass[$handler->modelClass()] = $handler;
        }
    }

    public function forSubject(Model $subject): ApprovalSubjectHandler
    {
        foreach ($this->byModelClass as $class => $handler) {
            if ($subject instanceof $class) {
                return $handler;
            }
        }

        throw new InvalidArgumentException('Unsupported approval subject model ['.$subject::class.'].');
    }

    public function forStoredSubject(string $subjectType): ApprovalSubjectHandler
    {
        $handler = $this->byModelClass[$subjectType] ?? $this->bySubjectType[$subjectType] ?? null;

        if (! $handler instanceof ApprovalSubjectHandler) {
            throw new InvalidArgumentException('Unsupported approval subject type ['.$subjectType.'].');
        }

        return $handler;
    }
}
```

Bind it in `AppServiceProvider` with exactly the current handlers:

```php
$this->app->singleton(ApprovalSubjectRegistry::class, fn ($app) => new ApprovalSubjectRegistry([
    $app->make(RequisitionApprovalSubjectHandler::class),
    $app->make(RfqAwardRecommendationApprovalSubjectHandler::class),
]));
```

If `RfqAwardRecommendationApprovalSubjectHandler` does not exist yet, add the binding in Task 4 after creating it.

- [ ] **Step 4: Add subjectType and award fields to approval context**

Update `ApprovalContextData` constructor, `fromArray()`, `missingRequiredContext()`, and `toArray()` to include these nullable fields:

```php
public readonly string $subjectType,
public readonly ?string $awardRecommendationId,
public readonly ?string $rfqId,
public readonly ?string $rfqNumber,
public readonly ?string $recommendedVendorId,
public readonly ?string $recommendedVendorName,
public readonly ?string $recommendedQuotationId,
public readonly ?string $recommendedQuotationVersionId,
public readonly ?float $recommendedAmount,
public readonly ?string $recommendedCurrency,
public readonly ?string $scorecardId,
public readonly ?float $scorecardWeightedTotal,
public readonly bool $riskSummaryPresent,
public readonly bool $exceptionSummaryPresent,
```

`fromRequisition()` must set `subjectType: 'requisition'` and null award fields so existing policy rules still work.

- [ ] **Step 5: Create requisition handler**

Create `RequisitionApprovalSubjectHandler` using the current hard-coded requisition behavior:

```php
public function subjectType(): string
{
    return 'requisition';
}

public function modelClass(): string
{
    return Requisition::class;
}

public function buildContext(Model $subject): ApprovalContextData
{
    assert($subject instanceof Requisition);
    return ApprovalContextData::fromRequisition($subject);
}

public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary
{
    assert($subject instanceof Requisition);
    $subject->loadMissing(['requester', 'lineItems']);

    return new ApprovalSubjectSummary(
        type: 'requisition',
        id: (string) $subject->id,
        number: $subject->number,
        title: $subject->title,
        status: $subject->status->value,
        primaryParty: $subject->requester?->name,
        amount: round($subject->lineItems->reduce(fn (float $carry, $lineItem): float => $carry + ((float) $lineItem->quantity * (float) $lineItem->estimated_unit_price), 0.0), 2),
        currency: $subject->currency,
        href: "/requisitions/{$subject->id}",
        metadata: [
            'requester' => $subject->requester !== null ? ['id' => (string) $subject->requester->id, 'name' => $subject->requester->name, 'email' => $subject->requester->email] : null,
            'department' => $subject->department,
            'costCenter' => $subject->cost_center,
            'projectId' => $subject->project_id !== null ? (string) $subject->project_id : null,
        ],
    );
}
```

`onRouted()`, `onApproved()`, `onRejected()`, and `onChangesRequested()` must call the existing requisition actions now used directly by approval actions.

- [ ] **Step 6: Refactor ApprovalTaskResource to use handler summaries**

Inject or resolve `ApprovalSubjectRegistry` inside `ApprovalTaskResource::toArray()`. Replace the requisition-specific `subject` block with:

```php
$subject = $this->whenLoaded('subject');
$summary = $subject instanceof Model
    ? app(ApprovalSubjectRegistry::class)->forStoredSubject($this->subject_type)->taskSubjectSummary($subject)
    : null;

'subject' => $summary?->toArray() ?? [
    'type' => $this->subject_type,
    'id' => (string) $this->subject_id,
    'number' => null,
    'title' => null,
    'status' => null,
    'primaryParty' => null,
    'amount' => null,
    'currency' => null,
    'href' => null,
    'metadata' => [],
],
```

Keep `permissions` unchanged.

- [ ] **Step 7: Run approval task tests**

Run:

```bash
cd apps/api
php artisan test --filter=ApprovalTaskApiTest
```

Expected: requisition behavior is green or exposes only remaining routing/action refactor work.

- [ ] **Step 8: Commit subject contract and requisition adapter**

Run:

```bash
git add apps/api/Domains/Approval/Contracts/ApprovalSubjectHandler.php apps/api/Domains/Approval/Services/ApprovalSubjectRegistry.php apps/api/Domains/Approval/Support/ApprovalSubjectSummary.php apps/api/Domains/Approval/SubjectHandlers/RequisitionApprovalSubjectHandler.php apps/api/Domains/Approval/Data/ApprovalContextData.php apps/api/Domains/Approval/Http/Resources/ApprovalTaskResource.php apps/api/app/Providers/AppServiceProvider.php
git commit -m "refactor: introduce approval subject handlers"
```

---

## Task 4: Subject-Aware Routing And Decision Actions

**Files:**

- Create: `apps/api/Domains/Approval/Actions/RouteSubjectForApproval.php`
- Modify: `apps/api/Domains/Approval/Actions/RouteRequisitionForApproval.php`
- Modify: `apps/api/Domains/Approval/Actions/ApproveApprovalTask.php`
- Modify: `apps/api/Domains/Approval/Actions/RejectApprovalTask.php`
- Modify: `apps/api/Domains/Approval/Actions/RequestApprovalChanges.php`
- Modify: `apps/api/Domains/Approval/Http/Controllers/ApprovalTaskController.php`

- [ ] **Step 1: Create RouteSubjectForApproval**

Create `RouteSubjectForApproval` by moving the reusable logic out of `RouteRequisitionForApproval`. The public method must be:

```php
public function handle(Tenant $tenant, User $actor, Model $subject): ApprovalInstance
```

It must:

- Resolve the handler through `ApprovalSubjectRegistry::forSubject($subject)`.
- Lock the subject by `tenant_id` and primary key.
- Return an existing active instance for the same `tenant_id`, `subject_type` model class, and `subject_id`.
- Build context through the handler.
- Query published policy versions by `$handler->subjectType()`.
- Create `ApprovalInstance`, stages, and tasks using stored `subject_type = $handler->modelClass()` and subject summary/task title from the handler.
- Record `approval_instance.routed`.
- Call `$handler->onRouted($tenant, $subject, $instance, $actor)`.
- Record assignment notifications with `notificationBody()` and `notificationSubjectLabel()`.

The policy candidate query must use:

```php
->where('subject_type', $handler->subjectType())
->where('status', ApprovalPolicyVersionStatus::Published)
->orderByDesc('priority')
->orderByDesc('version_number')
```

- [ ] **Step 2: Keep RouteRequisitionForApproval as wrapper**

Replace `RouteRequisitionForApproval::handle()` with:

```php
public function __construct(private readonly RouteSubjectForApproval $routeSubjectForApproval) {}

public function handle(Tenant $tenant, User $actor, Requisition $requisition): ApprovalInstance
{
    return $this->routeSubjectForApproval->handle($tenant, $actor, $requisition);
}
```

- [ ] **Step 3: Replace hard-coded requisition approval completion**

In `ApproveApprovalTask`, replace:

```php
if ($subject instanceof Requisition) {
    $this->markRequisitionApproved->handle($subject, $instance, $actor);
}
```

with:

```php
if ($subject instanceof Model) {
    $this->subjectRegistry
        ->forStoredSubject($task->subject_type)
        ->onApproved($tenant, $subject, $instance, $actor);
}
```

Inject `ApprovalSubjectRegistry` and remove direct requisition action dependencies from approval actions after the handler owns them.

- [ ] **Step 4: Replace hard-coded reject and changes side effects**

In `RejectApprovalTask`, after setting instance/stage/task rejected state, call:

```php
$subject = $task->subject;
if ($subject instanceof Model) {
    $this->subjectRegistry
        ->forStoredSubject($task->subject_type)
        ->onRejected($tenant, $subject, $instance, $actor, $reason);
}
```

In `RequestApprovalChanges`, call:

```php
$subject = $task->subject;
if ($subject instanceof Model) {
    $this->subjectRegistry
        ->forStoredSubject($task->subject_type)
        ->onChangesRequested($tenant, $subject, $instance, $actor, $reason, $requestedFields);
}
```

- [ ] **Step 5: Make task loading subject-safe**

Update `ApprovalTaskController` eager loading from requisition-only:

```php
->with(['assignee', 'originalAssignee', 'decidedBy', 'stage', 'instance', 'subject.requester', 'subject.lineItems'])
```

to:

```php
->with(['assignee', 'originalAssignee', 'decidedBy', 'stage', 'instance', 'subject'])
```

Subject handlers must load their own nested relations in `taskSubjectSummary()`.

- [ ] **Step 6: Run requisition approval tests**

Run:

```bash
cd apps/api
php artisan test --filter=ApprovalTaskApiTest
php artisan test --filter=ApprovalSlaApiTest
php artisan test --filter=ApprovalDelegationApiTest
```

Expected: all existing requisition approval behavior remains green.

- [ ] **Step 7: Commit routing refactor**

Run:

```bash
git add apps/api/Domains/Approval/Actions/RouteSubjectForApproval.php apps/api/Domains/Approval/Actions/RouteRequisitionForApproval.php apps/api/Domains/Approval/Actions/ApproveApprovalTask.php apps/api/Domains/Approval/Actions/RejectApprovalTask.php apps/api/Domains/Approval/Actions/RequestApprovalChanges.php apps/api/Domains/Approval/Http/Controllers/ApprovalTaskController.php
git commit -m "refactor: route approval subjects generically"
```

---

## Task 5: Award Subject Handler And Quotation State Actions

**Files:**

- Create: `apps/api/Domains/Approval/SubjectHandlers/RfqAwardRecommendationApprovalSubjectHandler.php`
- Create: `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApprovalRouted.php`
- Create: `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApproved.php`
- Create: `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationRejected.php`
- Create: `apps/api/Domains/Quotation/Actions/RequestRfqAwardRecommendationChanges.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Create award approval context builder inside handler**

Create `RfqAwardRecommendationApprovalSubjectHandler`. Its `buildContext()` must load:

```php
$recommendation->loadMissing([
    'rfq',
    'recommendedVendor',
    'recommendedQuotation',
    'recommendedQuotationVersion',
    'scorecard',
    'evidenceReferences',
]);
```

Return `ApprovalContextData` with:

```php
subjectType: 'rfq_award_recommendation',
awardRecommendationId: (string) $recommendation->id,
rfqId: (string) $recommendation->rfq_id,
rfqNumber: $recommendation->rfq?->number,
recommendedVendorId: (string) $recommendation->recommended_vendor_id,
recommendedVendorName: $recommendation->recommendedVendor?->name,
recommendedQuotationId: (string) $recommendation->recommended_quotation_id,
recommendedQuotationVersionId: (string) $recommendation->recommended_quotation_version_id,
recommendedAmount: (float) ($recommendation->recommendedQuotationVersion?->total_amount ?? 0),
recommendedCurrency: $recommendation->recommendedQuotationVersion?->currency,
scorecardId: $recommendation->scorecard_id !== null ? (string) $recommendation->scorecard_id : null,
scorecardWeightedTotal: $recommendation->scorecard?->weighted_total !== null ? (float) $recommendation->scorecard->weighted_total : null,
riskSummaryPresent: filled($recommendation->risk_summary),
exceptionSummaryPresent: filled($recommendation->exception_summary),
```

Preserve shared requisition fields as null or zero where they do not apply.

- [ ] **Step 2: Create award subject summary**

`taskSubjectSummary()` must return:

```php
new ApprovalSubjectSummary(
    type: 'rfq_award_recommendation',
    id: (string) $recommendation->id,
    number: $recommendation->rfq?->number,
    title: 'Award recommendation for '.($recommendation->rfq?->title ?? 'RFQ'),
    status: $recommendation->statusState()->value,
    primaryParty: $recommendation->recommendedVendor?->name,
    amount: $amount,
    currency: $currency,
    href: "/quotations/awards/{$recommendation->rfq_id}",
    metadata: [
        'rfqId' => (string) $recommendation->rfq_id,
        'rfqNumber' => $recommendation->rfq?->number,
        'recommendedVendorId' => (string) $recommendation->recommended_vendor_id,
        'recommendedVendorName' => $recommendation->recommendedVendor?->name,
        'recommendedQuotationId' => (string) $recommendation->recommended_quotation_id,
        'recommendedQuotationVersionId' => (string) $recommendation->recommended_quotation_version_id,
        'rationale' => $recommendation->rationale,
        'tradeoffSummary' => $recommendation->tradeoff_summary,
        'riskSummary' => $recommendation->risk_summary,
        'exceptionSummary' => $recommendation->exception_summary,
        'scorecardId' => $recommendation->scorecard_id !== null ? (string) $recommendation->scorecard_id : null,
        'scorecardWeightedTotal' => $recommendation->scorecard?->weighted_total,
        'evidenceReferenceCount' => $recommendation->evidenceReferences->count(),
    ],
);
```

`taskTitle()` must return `Approve award recommendation for {rfq number}`.

- [ ] **Step 3: Implement routed state action**

Create `MarkRfqAwardRecommendationApprovalRouted`:

```php
public function handle(RfqAwardRecommendation $recommendation, ApprovalInstance $instance, User $actor): void
{
    if (! $recommendation->statusState()->isPendingApproval()) {
        throw new ConflictHttpException('Only pending award recommendations can be routed for approval.');
    }

    $recommendation->forceFill([
        'status' => RfqAwardRecommendationStatus::ApprovalRouted,
        'approval_instance_id' => $instance->id,
        'updated_by_user_id' => $actor->id,
    ])->save();

    $this->auditRecorder->record(new AuditEventData(
        tenant: $recommendation->tenant,
        actor: $actor,
        action: 'rfq_award_recommendation.approval_routed',
        subject: $recommendation,
        metadata: ['approvalInstanceId' => (string) $instance->id],
    ));
}
```

- [ ] **Step 4: Implement approved, rejected, and changes-requested actions**

Create the three decision actions. Each must:

- Lock by recommendation id and tenant id.
- Accept only `approval_routed`.
- Set the correct status and metadata.
- Preserve `approval_instance_id`.
- Record the exact audit action from the design spec.

Reject action must set:

```php
'status' => RfqAwardRecommendationStatus::Rejected,
'rejected_by_user_id' => $actor->id,
'rejected_at' => now(),
'decision_reason' => $reason,
```

Changes action must set:

```php
'status' => RfqAwardRecommendationStatus::ChangesRequested,
'changes_requested_by_user_id' => $actor->id,
'changes_requested_at' => now(),
'changes_requested_reason' => $reason,
'changes_requested_fields' => array_values($requestedFields),
```

- [ ] **Step 5: Wire handler callbacks to quotation actions**

In `RfqAwardRecommendationApprovalSubjectHandler`, inject the four quotation actions and call them from `onRouted()`, `onApproved()`, `onRejected()`, and `onChangesRequested()`.

- [ ] **Step 6: Bind award handler in registry**

Update `AppServiceProvider` registry binding so it includes:

```php
$app->make(RfqAwardRecommendationApprovalSubjectHandler::class),
```

- [ ] **Step 7: Run award approval tests**

Run:

```bash
cd apps/api
php artisan test --filter=RfqAwardApprovalApiTest
```

Expected: tests still fail only for missing award approval routes/summary/preview endpoints, queue filters, or OpenAPI contract.

- [ ] **Step 8: Commit award handler and state actions**

Run:

```bash
git add apps/api/Domains/Approval/SubjectHandlers/RfqAwardRecommendationApprovalSubjectHandler.php apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApprovalRouted.php apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApproved.php apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationRejected.php apps/api/Domains/Quotation/Actions/RequestRfqAwardRecommendationChanges.php apps/api/app/Providers/AppServiceProvider.php
git commit -m "feat: handle award recommendations as approval subjects"
```

---

## Task 6: Award Approval Routes, Summary, Preview, And Queue Filters

**Files:**

- Create: `apps/api/Domains/Quotation/Actions/BuildRfqAwardApprovalPreview.php`
- Create: `apps/api/Domains/Quotation/Actions/BuildRfqAwardApprovalSummary.php`
- Modify: `apps/api/Domains/Quotation/Http/Controllers/RfqAwardRecommendationController.php`
- Modify: `apps/api/Domains/Quotation/Policies/RfqAwardRecommendationPolicy.php`
- Modify: `apps/api/Domains/Approval/Http/Controllers/ApprovalTaskController.php`
- Modify: `apps/api/Domains/Approval/Http/Resources/ApprovalPreviewResource.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add policy methods**

Add these methods to `RfqAwardRecommendationPolicy`:

```php
public function routeApproval(User $user, RfqAwardRecommendation $recommendation): bool
{
    return $this->manage($user, $recommendation)
        && $recommendation->statusState()->isPendingApproval();
}

public function viewApproval(User $user, RfqAwardRecommendation $recommendation): bool
{
    return $this->view($user, $recommendation);
}
```

If the existing policy works from `Rfq` instead of recommendation, resolve the recommendation first in the controller and apply equivalent buyer/admin tenant checks.

- [ ] **Step 2: Build award approval summary action**

Create `BuildRfqAwardApprovalSummary`:

```php
public function handle(Tenant $tenant, RfqAwardRecommendation $recommendation): ?ApprovalInstance
{
    return ApprovalInstance::query()
        ->with(['stages.tasks.assignee', 'tasks.assignee', 'tasks.decidedBy'])
        ->where('tenant_id', $tenant->id)
        ->where('subject_type', RfqAwardRecommendation::class)
        ->where('subject_id', $recommendation->id)
        ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
        ->latest('started_at')
        ->first();
}
```

- [ ] **Step 3: Build award approval preview action**

Create `BuildRfqAwardApprovalPreview`. It must:

- Load the pending recommendation for the RFQ.
- Resolve `RfqAwardRecommendationApprovalSubjectHandler`.
- Build context.
- Match published `rfq_award_recommendation` policies through `ApprovalPolicyMatcher`.
- Build a route through `ApprovalRouteBuilder`.
- Return the same array shape consumed by `ApprovalPreviewResource` without creating tasks.

- [ ] **Step 4: Add controller methods**

Add methods to `RfqAwardRecommendationController`:

```php
public function routeApproval(
    CurrentTenant $currentTenant,
    int $rfq,
    RouteSubjectForApproval $routeSubjectForApproval,
    BuildRfqAwardApprovalSummary $summaryBuilder,
): ApprovalSummaryResource
```

```php
public function approvalSummary(
    CurrentTenant $currentTenant,
    int $rfq,
    BuildRfqAwardApprovalSummary $summaryBuilder,
): JsonResponse
```

```php
public function approvalPreview(
    CurrentTenant $currentTenant,
    int $rfq,
    BuildRfqAwardApprovalPreview $previewBuilder,
): ApprovalPreviewResource
```

Each method must:

- Resolve the tenant RFQ.
- Load the RFQ's recommendation.
- Return `404` if no recommendation exists.
- Authorize buyer/admin view or route behavior.
- Route only when recommendation status is `pending_approval`.

- [ ] **Step 5: Register routes**

Add inside the existing tenant-protected RFQ group in `apps/api/routes/api.php`:

```php
Route::post('/rfqs/{rfq}/award-recommendation/approval-route', [RfqAwardRecommendationController::class, 'routeApproval']);
Route::get('/rfqs/{rfq}/award-recommendation/approval-summary', [RfqAwardRecommendationController::class, 'approvalSummary']);
Route::get('/rfqs/{rfq}/award-recommendation/approval-preview', [RfqAwardRecommendationController::class, 'approvalPreview']);
```

- [ ] **Step 6: Add subjectType queue filter**

Update `ApprovalTaskController::index()` validation:

```php
'subjectType' => ['sometimes', 'string', 'in:requisition,rfq_award_recommendation'],
```

Apply:

```php
->when($validated['subjectType'] ?? null, function ($query, string $subjectType): void {
    $modelClass = app(ApprovalSubjectRegistry::class)->forStoredSubject($subjectType)->modelClass();
    $query->where('subject_type', $modelClass);
})
```

Only apply requester/department/costCenter/project/amount filters when no subject type is provided or when `subjectType === 'requisition'`. If a requisition-only filter is sent with `subjectType=rfq_award_recommendation`, return an empty result set instead of joining requisition fields onto award tasks.

- [ ] **Step 7: Run focused API tests**

Run:

```bash
cd apps/api
php artisan test --filter=RfqAwardApprovalApiTest
php artisan test --filter=ApprovalTaskApiTest
php artisan test --filter=ApprovalPreviewApiTest
```

Expected: API behavior tests pass except OpenAPI/generated-client assertions that require contract updates.

- [ ] **Step 8: Commit routes and query behavior**

Run:

```bash
git add apps/api/Domains/Quotation/Actions/BuildRfqAwardApprovalPreview.php apps/api/Domains/Quotation/Actions/BuildRfqAwardApprovalSummary.php apps/api/Domains/Quotation/Http/Controllers/RfqAwardRecommendationController.php apps/api/Domains/Quotation/Policies/RfqAwardRecommendationPolicy.php apps/api/Domains/Approval/Http/Controllers/ApprovalTaskController.php apps/api/Domains/Approval/Http/Resources/ApprovalPreviewResource.php apps/api/routes/api.php
git commit -m "feat: expose award approval route and summary APIs"
```

---

## Task 7: OpenAPI Contract And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated files under `packages/api-client/src/generated/**`
- Modify: `apps/web/features/approvals/types/approval-view-model.ts`

- [ ] **Step 1: Update OpenAPI approval task subject schema**

Change `ApprovalTask.subject` from requisition-shaped fields to a generic object:

```json
{
  "type": "object",
  "required": ["type", "id", "metadata"],
  "properties": {
    "type": { "type": "string", "enum": ["requisition", "rfq_award_recommendation"] },
    "id": { "type": "string" },
    "number": { "type": ["string", "null"] },
    "title": { "type": ["string", "null"] },
    "status": { "type": ["string", "null"] },
    "primaryParty": { "type": ["string", "null"] },
    "amount": { "type": ["number", "null"] },
    "currency": { "type": ["string", "null"] },
    "href": { "type": ["string", "null"] },
    "metadata": { "type": "object", "additionalProperties": true }
  }
}
```

- [ ] **Step 2: Add award approval endpoints**

Add OpenAPI paths:

```txt
POST /api/rfqs/{rfq}/award-recommendation/approval-route
GET /api/rfqs/{rfq}/award-recommendation/approval-summary
GET /api/rfqs/{rfq}/award-recommendation/approval-preview
```

Use these operation IDs:

```txt
routeRfqAwardRecommendationForApproval
getRfqAwardRecommendationApprovalSummary
previewRfqAwardRecommendationApproval
```

Responses:

- route: `200` with `{ data: ApprovalSummary }`
- summary: `200` with `{ data: ApprovalSummary | null }`
- preview: `200` with `{ data: ApprovalPreview }`
- shared errors: `401`, `403`, `404`, `409`, `422`

- [ ] **Step 3: Add subjectType list param**

Add `subjectType` to `ListApprovalTasksParams` as:

```json
{
  "name": "subjectType",
  "in": "query",
  "required": false,
  "schema": { "type": "string", "enum": ["requisition", "rfq_award_recommendation"] }
}
```

- [ ] **Step 4: Regenerate generated client**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated endpoint functions and schemas include the new operation IDs and `subjectType` filter without web type errors.

- [ ] **Step 5: Update web view model filters**

Update `ApprovalTaskFilters`:

```ts
subjectType?: "requisition" | "rfq_award_recommendation";
```

Update `ApprovalPolicyFormValues.subjectType` to:

```ts
subjectType: "requisition" | "rfq_award_recommendation";
```

- [ ] **Step 6: Run API client typecheck**

Run:

```bash
pnpm --filter @cognify/api-client typecheck
```

Expected: pass.

- [ ] **Step 7: Commit contract and generated client**

Run:

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated apps/web/features/approvals/types/approval-view-model.ts
git commit -m "feat: add award approval API contract"
```

---

## Task 8: Web API Hooks And MSW Fixtures

**Files:**

- Modify: `apps/web/features/quotations/api/quotation-award-recommendation-api.ts`
- Modify: `apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts`
- Modify: `apps/web/features/approvals/mocks/approval-fixtures.ts`
- Modify: `apps/web/features/approvals/mocks/approval-handlers.ts`
- Modify: `apps/web/features/quotations/tests/quotation-award-recommendation-api.test.ts`

- [ ] **Step 1: Add quotation award approval API wrappers**

Import generated endpoints:

```ts
import {
  getRfqAwardRecommendationApprovalSummary,
  previewRfqAwardRecommendationApproval,
  routeRfqAwardRecommendationForApproval,
} from "@cognify/api-client/endpoints";
import type { ApprovalPreview, ApprovalSummary } from "@cognify/api-client/schemas";
```

Add wrappers:

```ts
export async function routeRfqAwardRecommendationApproval(rfqId: string, tenantId: string | null = getStoredActiveTenantId()): Promise<ApprovalSummary> {
  const response = await routeRfqAwardRecommendationForApproval(rfqId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapOk(response) as ApprovalSummary;
}

export async function fetchRfqAwardRecommendationApprovalSummary(rfqId: string, tenantId: string | null = getStoredActiveTenantId()): Promise<ApprovalSummary | null> {
  const response = await getRfqAwardRecommendationApprovalSummary(rfqId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapOk(response) as ApprovalSummary | null;
}

export async function previewRfqAwardRecommendationRoute(rfqId: string, tenantId: string | null = getStoredActiveTenantId()): Promise<ApprovalPreview> {
  const response = await previewRfqAwardRecommendationApproval(rfqId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapOk(response) as ApprovalPreview;
}
```

- [ ] **Step 2: Add route mutation hook**

In `use-rfq-award-recommendation-actions.ts`, add:

```ts
export function useRouteRfqAwardRecommendationApproval(rfqId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => routeRfqAwardRecommendationApproval(rfqId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["rfq-award-recommendation", rfqId] });
      void queryClient.invalidateQueries({ queryKey: ["rfq-award-recommendation-approval-summary", rfqId] });
      void queryClient.invalidateQueries({ queryKey: ["approval-tasks"] });
    },
  });
}
```

Also add `useRfqAwardRecommendationApprovalSummary` and `useRfqAwardRecommendationApprovalPreview` query hooks if keeping query hooks separate is clearer.

- [ ] **Step 3: Add MSW award approval states**

Extend quotation award fixtures with:

- pending recommendation that can be routed
- routed recommendation with active stage
- approved recommendation
- rejected recommendation
- changes-requested recommendation
- no matching award approval policy
- missing approver fallback

Handlers must mutate in-memory fixture state for:

```txt
POST /api/rfqs/:rfq/award-recommendation/approval-route
GET /api/rfqs/:rfq/award-recommendation/approval-summary
GET /api/rfqs/:rfq/award-recommendation/approval-preview
```

- [ ] **Step 4: Add approval task fixture for award subject**

Add an approval task fixture with:

```ts
subject: {
  type: "rfq_award_recommendation",
  id: "award-rec-1",
  number: "RFQ-2026-0042",
  title: "Award recommendation for Network refresh",
  status: "approval_routed",
  primaryParty: "Northwind Traders",
  amount: 128500,
  currency: "USD",
  href: "/quotations/awards/42",
  metadata: {
    rfqId: "42",
    recommendedVendorName: "Northwind Traders",
    rationale: "Best overall value with lower delivery risk.",
    tradeoffSummary: "Higher price than lowest bid; stronger implementation plan.",
    riskSummary: "No unresolved normalization issues.",
    exceptionSummary: null,
    scorecardWeightedTotal: 91.5,
    evidenceReferenceCount: 3
  }
}
```

- [ ] **Step 5: Add wrapper tests**

Extend `quotation-award-recommendation-api.test.ts` to assert:

```ts
await expect(routeRfqAwardRecommendationApproval("42", "tenant-1")).resolves.toMatchObject({ status: "active" });
await expect(fetchRfqAwardRecommendationApprovalSummary("42", "tenant-1")).resolves.toMatchObject({ currentStage: expect.any(Object) });
await expect(previewRfqAwardRecommendationRoute("42", "tenant-1")).resolves.toMatchObject({ context: expect.objectContaining({ subjectType: "rfq_award_recommendation" }) });
```

- [ ] **Step 6: Run web API tests**

Run:

```bash
pnpm --filter @cognify/web exec vitest run features/quotations/tests/quotation-award-recommendation-api.test.ts
```

Expected: pass.

- [ ] **Step 7: Commit web API and mocks**

Run:

```bash
git add apps/web/features/quotations/api/quotation-award-recommendation-api.ts apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts apps/web/features/approvals/mocks/approval-fixtures.ts apps/web/features/approvals/mocks/approval-handlers.ts apps/web/features/quotations/tests/quotation-award-recommendation-api.test.ts
git commit -m "feat: wire award approval web APIs"
```

---

## Task 9: Award Workspace Approval Panel

**Files:**

- Create: `apps/web/features/quotations/components/rfq-award-approval-panel.tsx`
- Modify: `apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx`
- Modify: `apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx`

- [ ] **Step 1: Write failing workspace tests**

Extend `rfq-award-recommendation-workspace.test.tsx` with tests:

```ts
it("shows route for approval when recommendation is pending approval", async () => {});
it("routes the recommendation and shows the active approval summary", async () => {});
it("shows approved rejected and changes requested outcomes without award execution controls", async () => {});
it("shows no policy and stale route errors in the approval panel", async () => {});
```

Assert visible text:

```txt
Approval route
Route for approval
Current stage
Active approvers
Open approval task
Approved
Rejected
Changes requested
No matching approval policy
```

Assert absent text:

```txt
Award vendor
Create PO handoff
Notify vendors
```

- [ ] **Step 2: Create approval panel component**

Create `RfqAwardApprovalPanel` props:

```ts
type RfqAwardApprovalPanelProps = {
  recommendationStatus: string;
  canRoute: boolean;
  summary: ApprovalSummary | null;
  isLoading: boolean;
  error: unknown;
  isRouting: boolean;
  onRoute: () => void;
};
```

Render:

- Pending + can route: `Route for approval` button.
- Routed: current stage, due date, active approvers, current user task link when present.
- Approved/rejected/changes_requested: outcome summary from completed decisions.
- Error: API error message from `getApiErrorMessage()`.

Use `Button` from `@cognify/ui` and a normal `Link` for task/workspace links. Do not create a new shared UI primitive.

- [ ] **Step 3: Wire panel into workspace**

In `RfqAwardRecommendationWorkspaceContent`:

- Add summary query/mutation hooks.
- Treat `approval_routed`, `approved`, `rejected`, and `changes_requested` as read-only.
- Replace submit-oriented copy after submission so `pending_approval` shows the route action instead of draft editing.
- Add a `RecordWorkspaceLayout` section id `approval`.

Use:

```ts
const routeApproval = useRouteRfqAwardRecommendationApproval(rfqId);
const approvalSummary = useRfqAwardRecommendationApprovalSummary(rfqId);
```

- [ ] **Step 4: Run workspace tests**

Run:

```bash
pnpm --filter @cognify/web exec vitest run features/quotations/tests/rfq-award-recommendation-workspace.test.tsx
```

Expected: pass.

- [ ] **Step 5: Commit award approval panel**

Run:

```bash
git add apps/web/features/quotations/components/rfq-award-approval-panel.tsx apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx
git commit -m "feat: show award approval progress in workspace"
```

---

## Task 10: Approval Queue And Task Detail For Award Subjects

**Files:**

- Modify: `apps/web/features/approvals/tables/approval-tasks-table.tsx`
- Modify: `apps/web/features/approvals/workflows/approval-queue-page.tsx`
- Modify: `apps/web/features/approvals/workflows/approval-task-detail-page.tsx`
- Modify: `apps/web/features/approvals/tests/approval-queue-workflow.test.tsx`
- Create: `apps/web/features/approvals/tests/approval-award-task-detail.test.tsx`

- [ ] **Step 1: Write failing approval UI tests**

Add queue assertions:

```ts
expect(screen.getByText("Award recommendation for Network refresh")).toBeInTheDocument();
expect(screen.getByText("Northwind Traders")).toBeInTheDocument();
expect(screen.getByRole("link", { name: /open/i })).toHaveAttribute("href", "/approvals/tasks/award-task-1");
```

Add task detail assertions:

```ts
expect(screen.getByText("Recommended vendor")).toBeInTheDocument();
expect(screen.getByText("Northwind Traders")).toBeInTheDocument();
expect(screen.getByText("Scorecard total")).toBeInTheDocument();
expect(screen.getByText("91.5")).toBeInTheDocument();
expect(screen.getByRole("link", { name: /open award recommendation/i })).toHaveAttribute("href", "/quotations/awards/42");
```

Also assert approve/reject/request-changes dialogs still render for the award task.

- [ ] **Step 2: Make queue labels generic**

Update table header `Requisition` to `Subject`.

Render:

```tsx
<div className="font-medium">{task.subject.title ?? "Approval subject"}</div>
<div className="font-mono text-xs text-muted-foreground">{task.subject.number ?? task.subject.type}</div>
<div className="text-xs text-muted-foreground">{task.subject.primaryParty}</div>
```

Keep stable columns and avoid requisition-only requester as a required display. If metadata requester exists, show it; otherwise show `primaryParty`.

- [ ] **Step 3: Add subject type filter**

In `approval-queue-page.tsx`, add a compact select or segmented control backed by `subjectType` values:

```txt
All subjects
Requisitions
Award recommendations
```

Pass `subjectType` into `useApprovalTasks()`.

- [ ] **Step 4: Make task detail subject-aware**

In `approval-task-detail-page.tsx`:

- Replace `Requisition` section with a `Subject` section.
- For `task.subject.type === "rfq_award_recommendation"`, render award-specific metadata fields from the fixture/API.
- For requisitions, preserve requester, department, cost center, and open requisition link behavior.
- Use `task.subject.href` for the subject link when present.

Award metadata labels:

```txt
Recommended vendor
Quotation version
Rationale
Tradeoff summary
Risk summary
Exception summary
Scorecard total
Evidence references
```

- [ ] **Step 5: Run approval web tests**

Run:

```bash
pnpm --filter @cognify/web exec vitest run features/approvals/tests/approval-queue-workflow.test.tsx features/approvals/tests/approval-award-task-detail.test.tsx features/approvals/tests/approval-task-actions.test.tsx
```

Expected: pass.

- [ ] **Step 6: Commit approval UI changes**

Run:

```bash
git add apps/web/features/approvals/tables/approval-tasks-table.tsx apps/web/features/approvals/workflows/approval-queue-page.tsx apps/web/features/approvals/workflows/approval-task-detail-page.tsx apps/web/features/approvals/tests/approval-queue-workflow.test.tsx apps/web/features/approvals/tests/approval-award-task-detail.test.tsx
git commit -m "feat: render award subjects in approval workspace"
```

---

## Task 11: End-To-End Contract, Verification, And Roadmap

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify this plan by checking completed boxes.

- [ ] **Step 1: Run full contract and focused API verification**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=Approval
cd apps/api && php artisan test --filter=RfqAwardRecommendationApiTest
cd apps/api && php artisan test --filter=RfqAwardApprovalApiTest
```

Expected: all pass.

- [ ] **Step 2: Run focused web verification**

Run:

```bash
pnpm --filter @cognify/web exec vitest run features/approvals/tests
pnpm --filter @cognify/web exec vitest run features/quotations/tests/quotation-award-recommendation-api.test.ts features/quotations/tests/rfq-award-recommendation-workspace.test.tsx
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/api-client typecheck
```

Expected: all pass.

- [ ] **Step 3: Run root checks**

Run:

```bash
pnpm lint
pnpm typecheck
pnpm build
git diff --check
```

Expected: all pass.

- [ ] **Step 4: Update roadmap**

Mark P1-33 in `docs/01-product/feature-roadmap.md` as implemented with a short note:

```txt
Implemented via `docs/superpowers/plans/2026-05-26-award-approval.md`: award recommendations route through the shared Approval domain and record approval outcomes; operational awarding and PO handoff remain downstream.
```

- [ ] **Step 5: Commit verification and roadmap**

Run:

```bash
git add docs/01-product/feature-roadmap.md docs/superpowers/plans/2026-05-26-award-approval.md
git commit -m "docs: mark award approval implementation complete"
```

Expected: final commit only after all verification passes.

---

## Self-Review Checklist

- Spec coverage: The plan covers routing, subject handler generalization, policy matching, task summaries, queue filters, decisions, award recommendation state, summary/preview endpoints, workspace UI, approval queue/task UI, audit, notifications, OpenAPI, and generated clients.
- Scope boundary: The plan does not create RFQ awarded state, vendor notifications, PO handoff, ERP export, split awards, or a new Award domain.
- Placeholder scan: No implementation step relies on vague follow-up work; each task has concrete files, method names, expected states, and commands.
- Type consistency: Subject type uses `rfq_award_recommendation` in policy/context/API and stores `RfqAwardRecommendation::class` in polymorphic database rows.
- Verification: Narrow API/web checks, generated contract checks, root lint/typecheck/build, and `git diff --check` are included before completion.
