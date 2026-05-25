# Recommendation and Award Decision Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-32 so buyers can record, save, submit, and withdraw an auditable RFQ award recommendation without creating approval tasks, awarding the RFQ, notifying vendors, or starting PO handoff.

**Architecture:** Keep the recommendation workflow inside `apps/api/Domains/Quotation` and `apps/web/features/quotations`, even though a small `Domains/Award\Models\Award` model exists for demo/search readiness. Persist only recommendation state and evidence references; assemble comparison, scoring, quotation, and vendor context from existing read paths. Expose tenant-scoped OpenAPI endpoints consumed through generated `@cognify/api-client` wrappers, TanStack Query hooks, MSW fixtures, and a `RecordWorkspaceLayout` workspace.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped policies/actions/resources, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix via `packages/ui`.

---

## Grounding

- Design spec: `docs/superpowers/specs/2026-05-25-recommendation-award-decision-design.md`.
- Roadmap: `docs/01-product/feature-roadmap.md` feature `P1-32`.
- Architecture: `ARCHITECTURE.md` requires tenant isolation, backend-owned workflow behavior, OpenAPI-generated clients, and feature UI in `apps/web`.
- Runbook: `docs/05-runbooks/feature-development.md` requires workflow-first, contract-first vertical slices.
- Predecessor implementation context:
  - P1-30 comparison endpoints and UI live under `apps/api/Domains/Quotation` and `apps/web/features/quotations`.
  - P1-31 scoring endpoints and UI live under the same quotation feature area.
  - `Domains/Award\Models\Award` exists for demo/search readiness only. Do not expand it for P1-32.

## Scope Boundaries

Implement:

- RFQ award recommendation context endpoint.
- Draft save/upsert.
- Submit into `pending_approval`.
- Withdraw from `pending_approval`.
- Recommendation state, selected vendor/quotation/version, rationale fields, withdrawal reason, and evidence references.
- Same-tenant/same-RFQ validation for selected vendor, quotation, quotation version, scorecard, comparison note, and quotation attachment references.
- Buyer/admin policies and role denial tests.
- Audit events for save, submit, and withdraw.
- OpenAPI contract and generated client.
- Web API wrappers, hooks, MSW fixtures, workspace route, form, vendor options, evidence selector, summary, and focused tests.
- Links from comparison and scoring workspaces to the award recommendation workspace.

Do not implement:

- Approval task creation, approval policy routing, approval notifications, award approval/rejection, RFQ awarded status, vendor notifications, PO handoff, ERP export, split awards, new award upload pipeline, Evidence Vault, AI-generated recommendation text, or a new `Award` domain workflow.

## File Map

### API

- Create: `apps/api/database/migrations/2026_05_26_000000_create_rfq_award_recommendations_table.php`
- Create: `apps/api/Domains/Quotation/States/RfqAwardRecommendationStatus.php`
- Create: `apps/api/Domains/Quotation/States/RfqAwardRecommendationEvidenceType.php`
- Create: `apps/api/Domains/Quotation/Models/RfqAwardRecommendation.php`
- Create: `apps/api/Domains/Quotation/Models/RfqAwardRecommendationEvidence.php`
- Create: `apps/api/Domains/Quotation/Policies/RfqAwardRecommendationPolicy.php`
- Create: `apps/api/Domains/Quotation/Actions/BuildRfqAwardRecommendationContext.php`
- Create: `apps/api/Domains/Quotation/Actions/SaveRfqAwardRecommendation.php`
- Create: `apps/api/Domains/Quotation/Actions/SubmitRfqAwardRecommendation.php`
- Create: `apps/api/Domains/Quotation/Actions/WithdrawRfqAwardRecommendation.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/SaveRfqAwardRecommendationRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/WithdrawRfqAwardRecommendationRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationResource.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationContextResource.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/RfqAwardRecommendationController.php`
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`
- Modify: `apps/api/Domains/Quotation/Models/Quotation.php`
- Modify: `apps/api/Domains/Quotation/Models/QuotationVersion.php`
- Modify: `apps/api/Domains/Quotation/Models/RfqScorecard.php`
- Modify: `apps/api/Domains/Quotation/Models/QuotationComparisonNote.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Test: `apps/api/tests/Feature/RfqAwardRecommendationApiTest.php`

### Generated Client

- Modify generated files under `packages/api-client/src/generated/**` via `pnpm generate:api` and `pnpm check:api-contract`.

Expected operation IDs:

- `showRfqAwardRecommendation`
- `saveRfqAwardRecommendation`
- `submitRfqAwardRecommendation`
- `withdrawRfqAwardRecommendation`

Expected generated schemas:

- `RfqAwardRecommendationResponse`
- `RfqAwardRecommendation`
- `RfqAwardRecommendationRfq`
- `RfqAwardRecommendationVendorOption`
- `RfqAwardRecommendationEvidenceReference`
- `RfqAwardRecommendationEvidenceReferenceInput`
- `RfqAwardRecommendationPermissions`
- `RfqAwardRecommendationScorecardSummary`
- `RfqAwardRecommendationReadiness`
- `SaveRfqAwardRecommendationRequest`
- `SubmitRfqAwardRecommendationRequest`
- `WithdrawRfqAwardRecommendationRequest`

### Web

- Create: `apps/web/app/(workspace)/quotations/awards/[rfqId]/page.tsx`
- Create: `apps/web/features/quotations/api/quotation-award-recommendation-api.ts`
- Create: `apps/web/features/quotations/hooks/use-rfq-award-recommendation.ts`
- Create: `apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts`
- Create: `apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-vendor-option-list.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-rationale-form.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-evidence-selector.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-decision-summary.tsx`
- Create: `apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts`
- Create: `apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts`
- Create: `apps/web/features/quotations/tests/quotation-award-recommendation-api.test.ts`
- Create: `apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx`
- Modify: `apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx`
- Modify: `apps/web/features/quotations/workflows/rfq-scoring-workspace.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`

### Docs

- Modify: `docs/01-product/feature-roadmap.md`
- Modify this plan during execution by checking completed boxes.

---

## Task 1: API Regression Tests For Award Recommendation Boundaries

**Files:**

- Create: `apps/api/tests/Feature/RfqAwardRecommendationApiTest.php`

- [ ] **Step 1: Write failing feature tests**

Create `apps/api/tests/Feature/RfqAwardRecommendationApiTest.php`. Reuse helper patterns from `QuotationComparisonApiTest`, `QuotationScoringApiTest`, and `QuotationNormalizationApiTest`.

The file must include tests with these exact scenario names:

```php
public function test_buyer_can_load_award_recommendation_context(): void
public function test_buyer_can_save_and_update_draft_recommendation_with_evidence_references(): void
public function test_buyer_can_submit_recommendation_to_pending_approval(): void
public function test_pending_approval_recommendation_is_read_only_except_withdrawal(): void
public function test_withdraw_requires_reason_and_records_withdrawal_metadata(): void
public function test_submit_rejects_stale_quotation_version(): void
public function test_submit_rejects_incomplete_scorecard_when_scorecard_exists(): void
public function test_submit_can_proceed_without_scorecard_when_comparison_is_ready(): void
public function test_evidence_references_must_belong_to_same_rfq_and_tenant(): void
public function test_requester_approver_vendor_and_cross_tenant_users_cannot_access_award_recommendation(): void
public function test_award_recommendation_actions_record_audit_events(): void
public function test_award_recommendation_routes_require_real_session_auth_and_tenant_context(): void
```

Use assertions shaped like this:

```php
$this->actingAsTenant($tenant, $buyer)
    ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
    ->assertOk()
    ->assertJsonPath('data.rfq.id', (string) $rfq->id)
    ->assertJsonPath('data.recommendation', null)
    ->assertJsonPath('data.vendorOptions.0.vendorName', 'Northwind Traders')
    ->assertJsonPath('data.permissions.canManageAwardRecommendation', true)
    ->assertJsonPath('data.readiness.comparisonStatus', 'ready');

$this->actingAsTenant($tenant, $buyer)
    ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
        'recommendedVendorId' => (string) $vendor->id,
        'recommendedQuotationId' => (string) $quotation->id,
        'recommendedQuotationVersionId' => (string) $version->id,
        'scorecardId' => (string) $scorecard->id,
        'rationale' => 'Best overall value with strong delivery confidence.',
        'tradeoffSummary' => 'Higher price than the lowest bid, but lower delivery risk.',
        'riskSummary' => 'No blocking normalization issues remain.',
        'exceptionSummary' => null,
        'evidenceReferences' => [
            [
                'type' => 'quotation_version',
                'id' => (string) $version->id,
                'label' => 'Evaluated quotation version',
            ],
            [
                'type' => 'scorecard',
                'id' => (string) $scorecard->id,
                'label' => 'Completed scoring matrix',
            ],
        ],
    ])
    ->assertOk()
    ->assertJsonPath('data.recommendation.status', 'draft')
    ->assertJsonPath('data.recommendation.rationale', 'Best overall value with strong delivery confidence.');

$this->actingAsTenant($tenant, $buyer)
    ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
    ->assertOk()
    ->assertJsonPath('data.recommendation.status', 'pending_approval')
    ->assertJsonPath('data.recommendation.submittedByUserId', (string) $buyer->id);
```

Route middleware proof must use real session auth for at least:

```txt
POST /api/auth/login
GET /api/rfqs/{rfq}/award-recommendation
PUT /api/rfqs/{rfq}/award-recommendation
POST /api/auth/logout
GET /api/rfqs/{rfq}/award-recommendation
```

Expected after logout: `401`.

- [ ] **Step 2: Run the API tests to verify they fail**

Run:

```bash
cd apps/api
php artisan test --filter=RfqAwardRecommendationApiTest
```

Expected: FAIL with missing routes, missing models, or missing controller classes.

- [ ] **Step 3: Commit failing tests**

Run:

```bash
git add apps/api/tests/Feature/RfqAwardRecommendationApiTest.php
git commit -m "test: define RFQ award recommendation API behavior"
```

Expected: commit containing only the new failing API test file.

---

## Task 2: Recommendation Data Model, States, And Relationships

**Files:**

- Create: `apps/api/database/migrations/2026_05_26_000000_create_rfq_award_recommendations_table.php`
- Create: `apps/api/Domains/Quotation/States/RfqAwardRecommendationStatus.php`
- Create: `apps/api/Domains/Quotation/States/RfqAwardRecommendationEvidenceType.php`
- Create: `apps/api/Domains/Quotation/Models/RfqAwardRecommendation.php`
- Create: `apps/api/Domains/Quotation/Models/RfqAwardRecommendationEvidence.php`
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`
- Modify: `apps/api/Domains/Quotation/Models/Quotation.php`
- Modify: `apps/api/Domains/Quotation/Models/QuotationVersion.php`
- Modify: `apps/api/Domains/Quotation/Models/RfqScorecard.php`
- Modify: `apps/api/Domains/Quotation/Models/QuotationComparisonNote.php`

- [ ] **Step 1: Create the migration**

Create `apps/api/database/migrations/2026_05_26_000000_create_rfq_award_recommendations_table.php`:

```php
<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_award_recommendations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Rfq::class)->constrained('rfqs')->cascadeOnDelete();
            $table->foreignIdFor(Vendor::class, 'recommended_vendor_id')->nullable()->constrained('vendors')->restrictOnDelete();
            $table->foreignIdFor(Quotation::class, 'recommended_quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $table->foreignIdFor(QuotationVersion::class, 'recommended_quotation_version_id')->nullable()->constrained('quotation_versions')->restrictOnDelete();
            $table->foreignUuid('scorecard_id')->nullable()->constrained('rfq_scorecards')->nullOnDelete();
            $table->string('status');
            $table->text('rationale')->nullable();
            $table->text('tradeoff_summary')->nullable();
            $table->text('risk_summary')->nullable();
            $table->text('exception_summary')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->foreignIdFor(User::class, 'created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignIdFor(User::class, 'updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignIdFor(User::class, 'withdrawn_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'rfq_id', 'status']);
        });

        Schema::create('rfq_award_recommendation_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignUuid('recommendation_id')->constrained('rfq_award_recommendations')->cascadeOnDelete();
            $table->string('evidence_type');
            $table->string('evidence_id');
            $table->string('label')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'evidence_type', 'evidence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_award_recommendation_evidence');
        Schema::dropIfExists('rfq_award_recommendations');
    }
};
```

Important: this migration uses a normal index for status and enforces “one non-withdrawn recommendation per RFQ” in domain actions because cross-database partial uniqueness is more complex and needs careful SQLite/Postgres/MySQL handling.

- [ ] **Step 2: Create state enums**

Create `apps/api/Domains/Quotation/States/RfqAwardRecommendationStatus.php`:

```php
<?php

namespace Domains\Quotation\States;

enum RfqAwardRecommendationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Withdrawn = 'withdrawn';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PendingApproval;
    }
}
```

Create `apps/api/Domains/Quotation/States/RfqAwardRecommendationEvidenceType.php`:

```php
<?php

namespace Domains\Quotation\States;

enum RfqAwardRecommendationEvidenceType: string
{
    case QuotationVersion = 'quotation_version';
    case QuotationAttachment = 'quotation_attachment';
    case ComparisonNote = 'comparison_note';
    case Scorecard = 'scorecard';
}
```

- [ ] **Step 3: Create models with tenant invariants**

Create `apps/api/Domains/Quotation/Models/RfqAwardRecommendation.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RfqAwardRecommendation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'recommended_vendor_id',
        'recommended_quotation_id',
        'recommended_quotation_version_id',
        'scorecard_id',
        'status',
        'rationale',
        'tradeoff_summary',
        'risk_summary',
        'exception_summary',
        'withdrawal_reason',
        'created_by_user_id',
        'updated_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'withdrawn_by_user_id',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqAwardRecommendationStatus::class,
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function statusState(): RfqAwardRecommendationStatus
    {
        return $this->status instanceof RfqAwardRecommendationStatus
            ? $this->status
            : RfqAwardRecommendationStatus::from((string) $this->getAttribute('status'));
    }

    protected static function booted(): void
    {
        static::saving(function (self $recommendation): void {
            DB::transaction(function () use ($recommendation): void {
                if (! Rfq::query()->whereKey($recommendation->rfq_id)->where('tenant_id', $recommendation->tenant_id)->lockForUpdate()->exists()) {
                    throw new InvalidArgumentException('Award recommendation RFQ must belong to the same tenant.');
                }
            });
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function recommendedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'recommended_vendor_id');
    }

    public function recommendedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'recommended_quotation_id');
    }

    public function recommendedQuotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'recommended_quotation_version_id');
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(RfqScorecard::class, 'scorecard_id');
    }

    public function evidenceReferences(): HasMany
    {
        return $this->hasMany(RfqAwardRecommendationEvidence::class, 'recommendation_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
```

Create `apps/api/Domains/Quotation/Models/RfqAwardRecommendationEvidence.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\RfqAwardRecommendationEvidenceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqAwardRecommendationEvidence extends Model
{
    use HasUuids;

    protected $table = 'rfq_award_recommendation_evidence';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'recommendation_id',
        'evidence_type',
        'evidence_id',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'evidence_type' => RfqAwardRecommendationEvidenceType::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(RfqAwardRecommendation::class, 'recommendation_id');
    }
}
```

- [ ] **Step 4: Add relationships to existing models**

Modify `apps/api/Domains/Quotation/Models/Rfq.php`:

```php
/**
 * @return HasMany<RfqAwardRecommendation, $this>
 */
public function awardRecommendations(): HasMany
{
    return $this->hasMany(RfqAwardRecommendation::class);
}

/**
 * @return \Illuminate\Database\Eloquent\Relations\HasOne<RfqAwardRecommendation, $this>
 */
public function activeAwardRecommendation()
{
    return $this->hasOne(RfqAwardRecommendation::class)
        ->whereIn('status', [
            RfqAwardRecommendationStatus::Draft->value,
            RfqAwardRecommendationStatus::PendingApproval->value,
        ])
        ->latestOfMany();
}
```

Add imports:

```php
use Domains\Quotation\States\RfqAwardRecommendationStatus;
```

Modify `Quotation`, `QuotationVersion`, `RfqScorecard`, and `QuotationComparisonNote` with `hasMany` relationships named `awardRecommendationEvidenceReferences()` when useful for validation. If the implementation validates evidence with direct queries instead, skip these relationships and keep validation in actions.

- [ ] **Step 5: Run migration/model checks**

Run:

```bash
cd apps/api
php -l database/migrations/2026_05_26_000000_create_rfq_award_recommendations_table.php
php -l Domains/Quotation/States/RfqAwardRecommendationStatus.php
php -l Domains/Quotation/States/RfqAwardRecommendationEvidenceType.php
php -l Domains/Quotation/Models/RfqAwardRecommendation.php
php -l Domains/Quotation/Models/RfqAwardRecommendationEvidence.php
php artisan test --filter=RfqAwardRecommendationApiTest
```

Expected: syntax checks pass; feature tests still fail on missing routes/actions/resources.

- [ ] **Step 6: Commit data model**

Run:

```bash
git add apps/api/database/migrations/2026_05_26_000000_create_rfq_award_recommendations_table.php \
  apps/api/Domains/Quotation/States/RfqAwardRecommendationStatus.php \
  apps/api/Domains/Quotation/States/RfqAwardRecommendationEvidenceType.php \
  apps/api/Domains/Quotation/Models/RfqAwardRecommendation.php \
  apps/api/Domains/Quotation/Models/RfqAwardRecommendationEvidence.php \
  apps/api/Domains/Quotation/Models/Rfq.php \
  apps/api/Domains/Quotation/Models/Quotation.php \
  apps/api/Domains/Quotation/Models/QuotationVersion.php \
  apps/api/Domains/Quotation/Models/RfqScorecard.php \
  apps/api/Domains/Quotation/Models/QuotationComparisonNote.php
git commit -m "feat: add RFQ award recommendation model"
```

---

## Task 3: Policies, Actions, Context Builder, And Resources

**Files:**

- Create: `apps/api/Domains/Quotation/Policies/RfqAwardRecommendationPolicy.php`
- Create: `apps/api/Domains/Quotation/Actions/BuildRfqAwardRecommendationContext.php`
- Create: `apps/api/Domains/Quotation/Actions/SaveRfqAwardRecommendation.php`
- Create: `apps/api/Domains/Quotation/Actions/SubmitRfqAwardRecommendation.php`
- Create: `apps/api/Domains/Quotation/Actions/WithdrawRfqAwardRecommendation.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/SaveRfqAwardRecommendationRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/WithdrawRfqAwardRecommendationRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationResource.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationContextResource.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add policy and register it**

Create `RfqAwardRecommendationPolicy`:

```php
<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;

class RfqAwardRecommendationPolicy
{
    public function view(User $user, Rfq|RfqAwardRecommendation $subject): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function manage(User $user, Rfq|RfqAwardRecommendation $subject): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function submit(User $user, RfqAwardRecommendation $recommendation): bool
    {
        return $this->buyerOrAdmin($user) && $recommendation->statusState()->isEditable();
    }

    public function withdraw(User $user, RfqAwardRecommendation $recommendation): bool
    {
        return $this->buyerOrAdmin($user) && $recommendation->statusState()->isPendingApproval();
    }

    private function buyerOrAdmin(User $user): bool
    {
        return in_array($user->activeTenantRole(), [TenantRole::Buyer, TenantRole::Admin], true);
    }
}
```

If `activeTenantRole()` is not available, follow the pattern used in `RfqScorecardPolicy` and use the same tenant-role resolver method that policy uses.

Register in `apps/api/app/Providers/AppServiceProvider.php`:

```php
Gate::policy(RfqAwardRecommendation::class, RfqAwardRecommendationPolicy::class);
```

- [ ] **Step 2: Create request classes**

Create `SaveRfqAwardRecommendationRequest`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\RfqAwardRecommendationEvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRfqAwardRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recommendedVendorId' => ['nullable', 'integer'],
            'recommendedQuotationId' => ['nullable', 'integer'],
            'recommendedQuotationVersionId' => ['nullable', 'integer'],
            'scorecardId' => ['nullable', 'uuid'],
            'rationale' => ['nullable', 'string', 'max:4000'],
            'tradeoffSummary' => ['nullable', 'string', 'max:4000'],
            'riskSummary' => ['nullable', 'string', 'max:4000'],
            'exceptionSummary' => ['nullable', 'string', 'max:4000'],
            'evidenceReferences' => ['sometimes', 'array'],
            'evidenceReferences.*.type' => ['required_with:evidenceReferences', Rule::enum(RfqAwardRecommendationEvidenceType::class)],
            'evidenceReferences.*.id' => ['required_with:evidenceReferences', 'string', 'max:80'],
            'evidenceReferences.*.label' => ['nullable', 'string', 'max:160'],
        ];
    }
}
```

Create `WithdrawRfqAwardRecommendationRequest`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRfqAwardRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 3: Implement context builder**

Create `BuildRfqAwardRecommendationContext`. It should return an array for the resource, not persist data.

Required public signature:

```php
public function handle(Tenant $tenant, User $actor, Rfq $rfq): array
```

Required behavior:

- Authorize `view` on `[RfqAwardRecommendation::class, $rfq]` or policy equivalent.
- Load RFQ with requisition and project summaries.
- Load current RFQ quotations with `vendor` and `currentVersion`.
- Include only quotations in the current tenant and RFQ.
- Load existing active recommendation with evidence references.
- Load optional scorecard and use `RfqScorecardCalculator` for summary values.
- Reuse `BuildQuotationComparison` to get comparison readiness and vendor context.

Return this array shape:

```php
[
    'rfq' => [...],
    'recommendation' => $recommendation,
    'vendorOptions' => [...],
    'scorecard' => [...],
    'readiness' => [
        'comparisonStatus' => 'ready'|'incomplete',
        'scoringStatus' => 'not_started'|'in_progress'|'complete',
        'blockingMessages' => [...],
    ],
    'evidenceReferences' => [...],
    'links' => [
        'comparison' => "/quotations/comparisons/{$rfq->id}",
        'scoring' => "/quotations/scoring/{$rfq->id}",
    ],
    'permissions' => [...],
]
```

- [ ] **Step 4: Implement save action**

Create `SaveRfqAwardRecommendation` with:

```php
public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): RfqAwardRecommendation
```

Implementation rules:

- Use a DB transaction.
- Lock RFQ.
- Find existing non-withdrawn recommendation for RFQ.
- If existing status is `pending_approval`, throw `ConflictHttpException('Pending award recommendations cannot be edited.')`.
- If no existing record, create a `draft` record with `created_by_user_id`.
- Validate selected vendor/quotation/version if provided.
- Validate scorecard if provided.
- Replace evidence references atomically after validating each reference.
- Trim rationale and summary fields.
- Record `rfq_award_recommendation.saved` with before/after status and evidence count.

Evidence validation helper must support:

```php
quotation_version -> QuotationVersion where tenant_id matches and quotation.rfq_id matches.
quotation_attachment -> Attachment where attachable_type is Quotation::class and attachable quotation rfq_id matches.
comparison_note -> QuotationComparisonNote where tenant_id and rfq_id match.
scorecard -> RfqScorecard where tenant_id and rfq_id match.
```

- [ ] **Step 5: Implement submit action**

Create `SubmitRfqAwardRecommendation` with:

```php
public function handle(Tenant $tenant, User $actor, Rfq $rfq, ?array $data = null): RfqAwardRecommendation
```

Implementation rules:

- If `$data` is provided, call save behavior first or share a private validator to update the draft before submit.
- Lock RFQ and recommendation.
- Require a draft recommendation.
- Require selected vendor, quotation, current quotation version, and non-empty rationale.
- Reject stale quotation version when `quotation.current_version_id !== recommended_quotation_version_id`.
- If an RFQ scorecard exists, require `RfqScorecardStatus::Completed`.
- Set `status`, `submitted_by_user_id`, and `submitted_at`.
- Record `rfq_award_recommendation.submitted`.
- Do not create `ApprovalTask`, `ApprovalInstance`, notifications, `Award`, RFQ status changes, quotation status changes, or vendor status changes.

- [ ] **Step 6: Implement withdraw action**

Create `WithdrawRfqAwardRecommendation` with:

```php
public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): RfqAwardRecommendation
```

Implementation rules:

- Lock RFQ and recommendation.
- Require `pending_approval`.
- Require trimmed reason.
- Set `status = withdrawn`, withdrawal metadata, and `withdrawal_reason`.
- Record `rfq_award_recommendation.withdrawn`.
- Do not reopen/edit scoring or comparison state.

- [ ] **Step 7: Create resources**

`RfqAwardRecommendationResource` must output:

```php
[
    'id' => (string) $recommendation->id,
    'status' => $recommendation->statusState()->value,
    'recommendedVendorId' => $recommendation->recommended_vendor_id !== null ? (string) $recommendation->recommended_vendor_id : null,
    'recommendedQuotationId' => $recommendation->recommended_quotation_id !== null ? (string) $recommendation->recommended_quotation_id : null,
    'recommendedQuotationVersionId' => $recommendation->recommended_quotation_version_id !== null ? (string) $recommendation->recommended_quotation_version_id : null,
    'scorecardId' => $recommendation->scorecard_id !== null ? (string) $recommendation->scorecard_id : null,
    'rationale' => $recommendation->rationale,
    'tradeoffSummary' => $recommendation->tradeoff_summary,
    'riskSummary' => $recommendation->risk_summary,
    'exceptionSummary' => $recommendation->exception_summary,
    'withdrawalReason' => $recommendation->withdrawal_reason,
    'submittedByUserId' => $recommendation->submitted_by_user_id !== null ? (string) $recommendation->submitted_by_user_id : null,
    'submittedAt' => $recommendation->submitted_at?->toISOString(),
    'withdrawnByUserId' => $recommendation->withdrawn_by_user_id !== null ? (string) $recommendation->withdrawn_by_user_id : null,
    'withdrawnAt' => $recommendation->withdrawn_at?->toISOString(),
    'updatedAt' => $recommendation->updated_at?->toISOString(),
]
```

`RfqAwardRecommendationContextResource` should return the context array from the builder and transform the recommendation through `RfqAwardRecommendationResource` when present.

- [ ] **Step 8: Run focused API checks**

Run:

```bash
cd apps/api
php artisan test --filter=RfqAwardRecommendationApiTest
```

Expected: failures only for missing controller/routes/OpenAPI if actions/resources compile, or passing tests if routes were added while implementing this task.

- [ ] **Step 9: Commit actions and resources**

Run:

```bash
git add apps/api/Domains/Quotation/Policies/RfqAwardRecommendationPolicy.php \
  apps/api/Domains/Quotation/Actions/BuildRfqAwardRecommendationContext.php \
  apps/api/Domains/Quotation/Actions/SaveRfqAwardRecommendation.php \
  apps/api/Domains/Quotation/Actions/SubmitRfqAwardRecommendation.php \
  apps/api/Domains/Quotation/Actions/WithdrawRfqAwardRecommendation.php \
  apps/api/Domains/Quotation/Http/Requests/SaveRfqAwardRecommendationRequest.php \
  apps/api/Domains/Quotation/Http/Requests/WithdrawRfqAwardRecommendationRequest.php \
  apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationResource.php \
  apps/api/Domains/Quotation/Http/Resources/RfqAwardRecommendationContextResource.php \
  apps/api/app/Providers/AppServiceProvider.php
git commit -m "feat: add RFQ award recommendation domain actions"
```

---

## Task 4: Controller, Routes, OpenAPI, And Generated Client

**Files:**

- Create: `apps/api/Domains/Quotation/Http/Controllers/RfqAwardRecommendationController.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated files under `packages/api-client/src/generated/**`

- [ ] **Step 1: Create controller**

Create `RfqAwardRecommendationController`:

```php
<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Actions\BuildRfqAwardRecommendationContext;
use Domains\Quotation\Actions\SaveRfqAwardRecommendation;
use Domains\Quotation\Actions\SubmitRfqAwardRecommendation;
use Domains\Quotation\Actions\WithdrawRfqAwardRecommendation;
use Domains\Quotation\Http\Requests\SaveRfqAwardRecommendationRequest;
use Domains\Quotation\Http\Requests\WithdrawRfqAwardRecommendationRequest;
use Domains\Quotation\Http\Resources\RfqAwardRecommendationContextResource;
use Domains\Quotation\Models\Rfq;

class RfqAwardRecommendationController extends Controller
{
    public function show(CurrentTenant $currentTenant, Rfq $rfq, BuildRfqAwardRecommendationContext $context): RfqAwardRecommendationContextResource
    {
        return new RfqAwardRecommendationContextResource(
            $context->handle($currentTenant->get(), request()->user(), $rfq)
        );
    }

    public function save(
        SaveRfqAwardRecommendationRequest $request,
        CurrentTenant $currentTenant,
        Rfq $rfq,
        SaveRfqAwardRecommendation $save,
        BuildRfqAwardRecommendationContext $context,
    ): RfqAwardRecommendationContextResource {
        $save->handle($currentTenant->get(), $request->user(), $rfq, $request->validated());

        return new RfqAwardRecommendationContextResource(
            $context->handle($currentTenant->get(), $request->user(), $rfq)
        );
    }

    public function submit(
        SaveRfqAwardRecommendationRequest $request,
        CurrentTenant $currentTenant,
        Rfq $rfq,
        SubmitRfqAwardRecommendation $submit,
        BuildRfqAwardRecommendationContext $context,
    ): RfqAwardRecommendationContextResource {
        $payload = $request->all() === [] ? null : $request->validated();
        $submit->handle($currentTenant->get(), $request->user(), $rfq, $payload);

        return new RfqAwardRecommendationContextResource(
            $context->handle($currentTenant->get(), $request->user(), $rfq)
        );
    }

    public function withdraw(
        WithdrawRfqAwardRecommendationRequest $request,
        CurrentTenant $currentTenant,
        Rfq $rfq,
        WithdrawRfqAwardRecommendation $withdraw,
        BuildRfqAwardRecommendationContext $context,
    ): RfqAwardRecommendationContextResource {
        $withdraw->handle($currentTenant->get(), $request->user(), $rfq, $request->validated());

        return new RfqAwardRecommendationContextResource(
            $context->handle($currentTenant->get(), $request->user(), $rfq)
        );
    }
}
```

- [ ] **Step 2: Add routes under tenant middleware**

Modify `apps/api/routes/api.php` near comparison/scoring routes:

```php
Route::middleware(RequireTenantHeader::class)->group(function (): void {
    Route::get('/rfqs/{rfq}/award-recommendation', [RfqAwardRecommendationController::class, 'show']);
    Route::put('/rfqs/{rfq}/award-recommendation', [RfqAwardRecommendationController::class, 'save']);
    Route::post('/rfqs/{rfq}/award-recommendation/submit', [RfqAwardRecommendationController::class, 'submit']);
    Route::post('/rfqs/{rfq}/award-recommendation/withdraw', [RfqAwardRecommendationController::class, 'withdraw']);
});
```

Add controller import:

```php
use Domains\Quotation\Http\Controllers\RfqAwardRecommendationController;
```

- [ ] **Step 3: Update OpenAPI paths and schemas**

Modify `apps/api/storage/openapi/openapi.json`.

Add paths:

```json
"/api/rfqs/{rfq}/award-recommendation": {
  "get": {
    "operationId": "showRfqAwardRecommendation",
    "tags": ["quotation-award-recommendations"],
    "summary": "Show RFQ award recommendation context",
    "parameters": [{ "$ref": "#/components/parameters/RfqPath" }],
    "responses": {
      "200": {
        "description": "RFQ award recommendation context",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/RfqAwardRecommendationResponse" }
          }
        }
      }
    }
  },
  "put": {
    "operationId": "saveRfqAwardRecommendation",
    "tags": ["quotation-award-recommendations"],
    "summary": "Save RFQ award recommendation draft",
    "parameters": [{ "$ref": "#/components/parameters/RfqPath" }],
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/SaveRfqAwardRecommendationRequest" }
        }
      }
    },
    "responses": {
      "200": {
        "description": "Saved RFQ award recommendation context",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/RfqAwardRecommendationResponse" }
          }
        }
      }
    }
  }
}
```

Add submit and withdraw paths with operation IDs `submitRfqAwardRecommendation` and `withdrawRfqAwardRecommendation`.

Add schemas matching the design spec. Required response root:

```json
"RfqAwardRecommendationResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/RfqAwardRecommendationContext" }
  }
}
```

Use camelCase property names in OpenAPI and web code.

- [ ] **Step 4: Generate and verify API client**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated client exports functions and schemas listed in the file map, and contract check passes.

- [ ] **Step 5: Run API tests**

Run:

```bash
cd apps/api
php artisan test --filter=RfqAwardRecommendationApiTest
```

Expected: all award recommendation API tests pass.

- [ ] **Step 6: Commit controller, contract, and generated client**

Run:

```bash
git add apps/api/Domains/Quotation/Http/Controllers/RfqAwardRecommendationController.php \
  apps/api/routes/api.php \
  apps/api/storage/openapi/openapi.json \
  packages/api-client/src/generated
git commit -m "feat: expose RFQ award recommendation API"
```

---

## Task 5: Web API Wrappers, Hooks, MSW Fixtures, And API Tests

**Files:**

- Create: `apps/web/features/quotations/api/quotation-award-recommendation-api.ts`
- Create: `apps/web/features/quotations/hooks/use-rfq-award-recommendation.ts`
- Create: `apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts`
- Create: `apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts`
- Create: `apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts`
- Create: `apps/web/features/quotations/tests/quotation-award-recommendation-api.test.ts`

- [ ] **Step 1: Write web API tests**

Create `quotation-award-recommendation-api.test.ts`:

```ts
import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";
import { server } from "@/tests/msw/server";
import {
  saveRfqAwardRecommendation,
  showRfqAwardRecommendation,
  submitRfqAwardRecommendation,
  withdrawRfqAwardRecommendation,
} from "../api/quotation-award-recommendation-api";
import { quotationAwardRecommendationHandlers, resetQuotationAwardRecommendationMockState } from "../mocks/quotation-award-recommendation-handlers";

describe("quotation award recommendation api", () => {
  beforeEach(() => {
    resetQuotationAwardRecommendationMockState();
    window.localStorage.clear();
    server.use(...quotationAwardRecommendationHandlers);
  });

  it("loads recommendation context with tenant header", async () => {
    storeActiveTenantId("tenant-1");
    let tenantHeader: string | null = null;
    server.use(
      http.get("*/api/rfqs/rfq-ready/award-recommendation", ({ request }) => {
        tenantHeader = request.headers.get("X-Tenant-Id");
        return HttpResponse.json({ data: awardRecommendationFixture("rfq-ready") });
      }),
    );

    const context = await showRfqAwardRecommendation("rfq-ready", "tenant-1");
    expect(context.rfq.id).toBe("rfq-ready");
    expect(tenantHeader).toBe("tenant-1");
  });

  it("saves submits and withdraws a recommendation", async () => {
    const saved = await saveRfqAwardRecommendation("rfq-ready", {
      recommendedVendorId: "vendor-1",
      recommendedQuotationId: "quotation-1",
      recommendedQuotationVersionId: "version-1",
      scorecardId: "scorecard-rfq-ready",
      rationale: "Best overall value.",
      tradeoffSummary: "Slightly higher price, lower risk.",
      riskSummary: null,
      exceptionSummary: null,
      evidenceReferences: [{ type: "scorecard", id: "scorecard-rfq-ready", label: "Scorecard" }],
    }, "tenant-1");

    expect(saved.recommendation?.status).toBe("draft");

    const submitted = await submitRfqAwardRecommendation("rfq-ready", undefined, "tenant-1");
    expect(submitted.recommendation?.status).toBe("pending_approval");

    const withdrawn = await withdrawRfqAwardRecommendation("rfq-ready", { reason: "Need revised vendor confirmation." }, "tenant-1");
    expect(withdrawn.recommendation?.status).toBe("withdrawn");
  });
});
```

Adjust imported fixture helper name after creating fixtures.

- [ ] **Step 2: Create API wrappers**

Create `quotation-award-recommendation-api.ts` using generated functions:

```ts
import {
  saveRfqAwardRecommendation as saveGeneratedRfqAwardRecommendation,
  showRfqAwardRecommendation as showGeneratedRfqAwardRecommendation,
  submitRfqAwardRecommendation as submitGeneratedRfqAwardRecommendation,
  withdrawRfqAwardRecommendation as withdrawGeneratedRfqAwardRecommendation,
} from "@cognify/api-client";
import type {
  RfqAwardRecommendationContext,
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
  WithdrawRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";
import { withTenantHeader } from "@/features/identity/api/identity-api";

export async function showRfqAwardRecommendation(rfqId: string, tenantId?: string): Promise<RfqAwardRecommendationContext> {
  const response = await showGeneratedRfqAwardRecommendation(rfqId, withTenantHeader(tenantId));
  return response.data;
}

export async function saveRfqAwardRecommendation(
  rfqId: string,
  input: SaveRfqAwardRecommendationRequest,
  tenantId?: string,
): Promise<RfqAwardRecommendationContext> {
  const response = await saveGeneratedRfqAwardRecommendation(rfqId, input, withTenantHeader(tenantId));
  return response.data;
}

export async function submitRfqAwardRecommendation(
  rfqId: string,
  input?: SubmitRfqAwardRecommendationRequest,
  tenantId?: string,
): Promise<RfqAwardRecommendationContext> {
  const response = await submitGeneratedRfqAwardRecommendation(rfqId, input ?? {}, withTenantHeader(tenantId));
  return response.data;
}

export async function withdrawRfqAwardRecommendation(
  rfqId: string,
  input: WithdrawRfqAwardRecommendationRequest,
  tenantId?: string,
): Promise<RfqAwardRecommendationContext> {
  const response = await withdrawGeneratedRfqAwardRecommendation(rfqId, input, withTenantHeader(tenantId));
  return response.data;
}
```

Use the actual generated function signatures after `pnpm generate:api`; do not hand-write generated response types.

- [ ] **Step 3: Create hooks**

`use-rfq-award-recommendation.ts`:

```ts
import { useQuery } from "@tanstack/react-query";
import { useActiveTenantId } from "@/features/identity/hooks/use-active-tenant-id";
import { showRfqAwardRecommendation } from "../api/quotation-award-recommendation-api";

export const rfqAwardRecommendationQueryKey = (rfqId: string, tenantId?: string | null) => [
  "rfq-award-recommendation",
  tenantId ?? "no-tenant",
  rfqId,
] as const;

export function useRfqAwardRecommendation(rfqId: string) {
  const tenantId = useActiveTenantId();
  return useQuery({
    queryKey: rfqAwardRecommendationQueryKey(rfqId, tenantId),
    queryFn: () => showRfqAwardRecommendation(rfqId, tenantId ?? undefined),
    enabled: Boolean(tenantId),
  });
}
```

`use-rfq-award-recommendation-actions.ts` should define `useSaveRfqAwardRecommendation`, `useSubmitRfqAwardRecommendation`, and `useWithdrawRfqAwardRecommendation` using `useMutation`, invalidating `rfqAwardRecommendationQueryKey(rfqId, tenantId)` on success.

- [ ] **Step 4: Create MSW fixtures and handlers**

Fixtures must include:

- `rfq-ready`: no recommendation initially, completed scorecard, ready comparison.
- `rfq-draft-recommendation`: existing draft.
- `rfq-pending-recommendation`: pending approval.
- `rfq-incomplete-scorecard`: scorecard exists with incomplete scoring.
- `rfq-no-scorecard`: ready comparison without scorecard.
- `rfq-no-vendors`: empty vendor options.

Handler routes:

```ts
http.get("/api/rfqs/:rfq/award-recommendation", ...)
http.put("/api/rfqs/:rfq/award-recommendation", ...)
http.post("/api/rfqs/:rfq/award-recommendation/submit", ...)
http.post("/api/rfqs/:rfq/award-recommendation/withdraw", ...)
```

Submit handler rules:

- Return `409` when scorecard status is not complete.
- Return `422` when rationale or selected vendor is missing.
- Return `409` when recommendation is already `pending_approval`.
- Return `200` and set status to `pending_approval` when valid.

- [ ] **Step 5: Run web API tests**

Run:

```bash
pnpm --filter @cognify/web exec vitest run features/quotations/tests/quotation-award-recommendation-api.test.ts
```

Expected: tests pass.

- [ ] **Step 6: Commit web API layer**

Run:

```bash
git add apps/web/features/quotations/api/quotation-award-recommendation-api.ts \
  apps/web/features/quotations/hooks/use-rfq-award-recommendation.ts \
  apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts \
  apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts \
  apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts \
  apps/web/features/quotations/tests/quotation-award-recommendation-api.test.ts
git commit -m "feat: add award recommendation web API layer"
```

---

## Task 6: Award Recommendation Workspace UI

**Files:**

- Create: `apps/web/app/(workspace)/quotations/awards/[rfqId]/page.tsx`
- Create: `apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-vendor-option-list.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-rationale-form.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-evidence-selector.tsx`
- Create: `apps/web/features/quotations/components/rfq-award-decision-summary.tsx`
- Create: `apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx`
- Modify: `apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx`
- Modify: `apps/web/features/quotations/workflows/rfq-scoring-workspace.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`

- [ ] **Step 1: Write workspace tests**

Create `rfq-award-recommendation-workspace.test.tsx` covering:

```ts
it("renders award recommendation context and vendor options", async () => ...)
it("saves a draft recommendation", async () => ...)
it("blocks submit when required fields are missing", async () => ...)
it("blocks submit when scorecard is incomplete", async () => ...)
it("submits a complete recommendation", async () => ...)
it("renders pending approval recommendations as read only and withdraws with reason", async () => ...)
it("shows empty state when no eligible vendor responses exist", async () => ...)
```

Expected key assertions:

```ts
expect(await screen.findByRole("heading", { name: "Award recommendation" })).toBeInTheDocument();
expect(screen.getByText("Northwind Traders")).toBeInTheDocument();
expect(screen.getByRole("button", { name: "Submit for approval" })).toBeDisabled();
expect(await screen.findByText("Scorecard must be completed before submission.")).toBeInTheDocument();
```

- [ ] **Step 2: Create route page**

Create `apps/web/app/(workspace)/quotations/awards/[rfqId]/page.tsx`:

```tsx
import { RfqAwardRecommendationWorkspace } from "@/features/quotations/workflows/rfq-award-recommendation-workspace";

export default async function RfqAwardRecommendationPage({ params }: { params: Promise<{ rfqId: string }> }) {
  const { rfqId } = await params;
  return <RfqAwardRecommendationWorkspace rfqId={rfqId} />;
}
```

- [ ] **Step 3: Build workspace composition**

Create `rfq-award-recommendation-workspace.tsx` with:

- `useRfqAwardRecommendation(rfqId)`
- local form state initialized from `context.recommendation`
- `RecordWorkspaceLayout`
- loading, error, empty, draft, pending, withdrawn states
- action buttons for save, submit, withdraw

Submit disabled helper:

```ts
function submitBlocked(context: RfqAwardRecommendationContext, draft: DraftState): string | null {
  if (!draft.recommendedVendorId || !draft.recommendedQuotationId || !draft.recommendedQuotationVersionId) {
    return "Select a recommended vendor response before submission.";
  }
  if (!draft.rationale.trim()) {
    return "Rationale is required before submission.";
  }
  if (context.scorecard && context.scorecard.status !== "completed") {
    return "Scorecard must be completed before submission.";
  }
  if (context.readiness.comparisonStatus !== "ready") {
    return "Comparison readiness must be complete before submission.";
  }
  return null;
}
```

- [ ] **Step 4: Build vendor option component**

`rfq-award-vendor-option-list.tsx` should render a dense table/list:

- vendor name
- total amount and currency
- lead time
- readiness
- weighted score total when present
- missing score count
- select radio/button

Use stable row keys from `vendor.vendorId`.

- [ ] **Step 5: Build rationale form**

`rfq-award-rationale-form.tsx` should render fields:

- rationale
- tradeoff summary
- risk summary
- exception summary

Use `Textarea` from `@cognify/ui`. Disable all fields when recommendation status is `pending_approval` or `withdrawn`.

- [ ] **Step 6: Build evidence selector**

`rfq-award-evidence-selector.tsx` should render checkbox-style references from context:

- selected quotation version
- completed scorecard when present
- comparison notes from context if exposed
- quotation attachments when exposed

Keep the selector deterministic. It should emit:

```ts
{ type: "scorecard", id: context.scorecard.id, label: "Completed scoring matrix" }
```

- [ ] **Step 7: Build decision summary**

`rfq-award-decision-summary.tsx` should show:

- selected vendor name
- selected quotation version
- status badge
- readiness warnings
- submit-blocking reason
- pending approval explanation
- withdrawal reason when withdrawn

- [ ] **Step 8: Link from comparison and scoring workspaces**

Modify `quotation-comparison-workspace.tsx` and `rfq-scoring-workspace.tsx` to include:

```tsx
<Link
  className="inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent"
  href={`/quotations/awards/${rfqId}`}
>
  Award recommendation
</Link>
```

Only show the link when the current comparison/scoring permissions indicate the buyer/admin can manage the workflow. If no specific award permission is available on those responses yet, show it for existing buyer/admin quotation workspace users and rely on the award endpoint for enforcement.

- [ ] **Step 9: Add shell route config**

Modify `apps/web/components/shell/shell-route-config.ts` to include a route label for `/quotations/awards/[rfqId]` equivalent to "Award recommendation".

Update `shell-route-config.test.tsx` with an assertion that the route resolves to the expected label.

- [ ] **Step 10: Run workspace tests**

Run:

```bash
pnpm --filter @cognify/web exec vitest run features/quotations/tests/rfq-award-recommendation-workspace.test.tsx features/quotations/tests/quotation-award-recommendation-api.test.ts
```

Expected: all award recommendation web tests pass.

- [ ] **Step 11: Commit workspace UI**

Run:

```bash
git add apps/web/app/'(workspace)'/quotations/awards/[rfqId]/page.tsx \
  apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx \
  apps/web/features/quotations/components/rfq-award-vendor-option-list.tsx \
  apps/web/features/quotations/components/rfq-award-rationale-form.tsx \
  apps/web/features/quotations/components/rfq-award-evidence-selector.tsx \
  apps/web/features/quotations/components/rfq-award-decision-summary.tsx \
  apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx \
  apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx \
  apps/web/features/quotations/workflows/rfq-scoring-workspace.tsx \
  apps/web/components/shell/shell-route-config.ts \
  apps/web/components/shell/shell-route-config.test.tsx
git commit -m "feat: add RFQ award recommendation workspace"
```

---

## Task 7: Roadmap, Demo Fixtures, And Final Verification

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Optionally modify: `apps/api/database/seeders/Demo/DemoRoadmapPreviewSeeder.php`
- Optionally modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts`

- [ ] **Step 1: Update roadmap**

Modify P1-32 row in `docs/01-product/feature-roadmap.md`:

```md
| P1-32 | Recommendation and Award Decision | Let buyers select a recommended vendor, explain the rationale, attach supporting evidence, and route the decision for approval if required. The award decision should be auditable. | Fully Implemented | 2026-05-25-recommendation-award-decision-design.md | 2026-05-26-recommendation-award-decision.md |  | Implemented as RFQ-level award recommendations with draft, pending approval, and withdrawn states, evidence references to existing quotation/comparison/scoring artifacts, audit events, and no approval-task, awarded-state, vendor-notification, or PO-handoff side effects. |
```

- [ ] **Step 2: Add or confirm demo/mock data**

Ensure MSW fixtures expose at least:

- no recommendation
- draft recommendation
- pending approval recommendation
- incomplete-scorecard blocked path
- no-scorecard allowed path

If local demo seeders already include enough RFQ/quotation/scoring data for manual review, do not broaden backend seeders. If a seeded recommendation is useful for demos, add one deterministic draft or pending recommendation to `DemoRoadmapPreviewSeeder` using the new model and evidence references.

- [ ] **Step 3: Run focused API verification**

Run:

```bash
cd apps/api
php artisan test --filter=RfqAwardRecommendationApiTest
php artisan test --filter=QuotationComparisonApiTest
php artisan test --filter=QuotationScoringApiTest
```

Expected: all pass.

- [ ] **Step 4: Run contract verification**

Run:

```bash
pnpm check:api-contract
```

Expected: OpenAPI export and generated client are in sync.

- [ ] **Step 5: Run focused web verification**

Run:

```bash
pnpm --filter @cognify/web exec vitest run features/quotations/tests/quotation-award-recommendation-api.test.ts features/quotations/tests/rfq-award-recommendation-workspace.test.tsx
pnpm --filter @cognify/web exec vitest run features/quotations/tests/quotation-comparison-workspace.test.tsx features/quotations/tests/rfq-scoring-workspace.test.tsx
```

Expected: all pass.

- [ ] **Step 6: Run repo-level checks**

Run:

```bash
pnpm lint
pnpm typecheck
git diff --check
```

Expected: all pass.

- [ ] **Step 7: Manual route smoke if dev server is available**

Run:

```bash
pnpm dev
```

Visit:

```txt
http://localhost:8880/quotations/awards/rfq-ready
```

Expected:

- authenticated shell renders through `SessionGate`;
- workspace shows RFQ header, vendor options, rationale fields, evidence selector, and decision summary;
- draft save works through MSW in test/dev mode or real API in local full-stack mode;
- submit is blocked with clear copy when required fields or scoring readiness are missing.

Stop the dev server after smoke testing if it was started only for this verification.

- [ ] **Step 8: Final commit**

Run:

```bash
git add docs/01-product/feature-roadmap.md \
  apps/api \
  apps/web \
  packages/api-client
git commit -m "feat: add RFQ award recommendation workflow"
```

If previous tasks already committed all implementation files, this final commit should contain only roadmap/demo follow-through. If there is nothing left to commit, record that in the handoff.

---

## Execution Notes

- Keep all workflow mutations in domain actions, not controllers.
- Use generated OpenAPI schemas in web code; do not duplicate response types by hand.
- Do not import MSW fixtures into production components.
- Do not expand `Domains/Award\Models\Award` for this slice.
- Do not create approval tasks or call approval policy routing from submit.
- Do not mutate RFQ, quotation, invitation, normalization, scorecard, comparison note, vendor, requisition, project, or demo `awards` state from recommendation actions.
- Preserve user draft input on web mutation errors.
- If a reviewer asks for partial unique indexes on non-withdrawn recommendations, verify SQLite/Postgres/MySQL behavior before changing the migration; domain-action locking is the planned first-pass invariant.

## Self-Review Checklist

- Spec coverage: tasks cover recommendation context, draft save, submit, withdraw, evidence references, audit, OpenAPI, web workspace, tests, roadmap, and non-goal boundaries.
- Completion scan: this plan intentionally contains no incomplete markers or unspecified "add error handling" steps.
- Type consistency: API and web names consistently use `RfqAwardRecommendation*`, route path `/api/rfqs/{rfq}/award-recommendation`, and web path `/quotations/awards/[rfqId]`.
