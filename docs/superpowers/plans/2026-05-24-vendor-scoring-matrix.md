# Vendor Scoring Matrix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-31 so admins can maintain lightweight reusable scoring templates and buyers can apply one frozen RFQ scorecard to score quotation responses without creating award, shortlist, recommendation, or RFQ state side effects.

**Architecture:** Keep scoring templates, RFQ scorecards, frozen criteria, score entries, policies, actions, and resources inside `apps/api/Domains/Quotation`. Expose tenant-scoped OpenAPI endpoints consumed through generated `@cognify/api-client` wrappers in `apps/web/features/quotations`, with `RecordWorkspaceLayout`, TanStack Query hooks, MSW fixtures, and shadcn/Radix primitives. Score math is deterministic and persisted scoring state is limited to templates, scorecard snapshots, score entries, and scorecard completion metadata.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped policies/actions/resources, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix via `packages/ui`.

---

## Grounding

- Design spec: `docs/superpowers/specs/2026-05-24-vendor-scoring-matrix-design.md`.
- Roadmap: `docs/01-product/feature-roadmap.md` feature `P1-31`.
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 7 slice 3.
- Architecture: `ARCHITECTURE.md` requires tenant isolation, backend-owned workflow behavior, OpenAPI/generated clients, and feature UI in `apps/web`.
- Runbook: `docs/05-runbooks/feature-development.md` requires workflow-first, contract-first vertical slices.
- Existing implementation context: P1-30 comparison already owns RFQ-level evaluation context under `apps/api/Domains/Quotation` and `apps/web/features/quotations`.

## Scope Boundaries

Implement:

- Admin scoring template list/create/edit/deactivate.
- Tenant-scoped template criteria with category, label, guidance, weight, max score, required flag, and display order.
- Buyer/admin RFQ scorecard creation from one active template.
- Frozen scorecard criteria snapshots that do not change when the template is edited later.
- Score entry updates with criterion-level notes.
- Deterministic raw totals, weighted totals, missing required counts, completion/reopen actions.
- API contract, generated client, MSW fixtures, web routes, and focused tests.
- Link from RFQ comparison to scoring.

Do not implement:

- Template approval workflow, version browser, cloning, archive lifecycle, configurable RBAC, AI scoring, external risk integration, currency conversion, shortlist, exclusion, award recommendation, award approval, PO handoff, vendor-facing scoring, or spreadsheet export.
- Any scoring action that changes RFQ, quotation, quotation version, normalization, comparison note, invitation, award, or vendor state.

## File Map

### API

- Create: `apps/api/database/migrations/2026_05_24_000000_create_quotation_scoring_templates_table.php`
- Create: `apps/api/database/migrations/2026_05_24_000001_create_rfq_scorecards_table.php`
- Create: `apps/api/Domains/Quotation/States/QuotationScoringCriterionCategory.php`
- Create: `apps/api/Domains/Quotation/States/RfqScorecardStatus.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationScoringTemplate.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationScoringTemplateCriterion.php`
- Create: `apps/api/Domains/Quotation/Models/RfqScorecard.php`
- Create: `apps/api/Domains/Quotation/Models/RfqScorecardCriterion.php`
- Create: `apps/api/Domains/Quotation/Models/RfqScorecardEntry.php`
- Create: `apps/api/Domains/Quotation/Policies/QuotationScoringTemplatePolicy.php`
- Create: `apps/api/Domains/Quotation/Policies/RfqScorecardPolicy.php`
- Create: `apps/api/Domains/Quotation/Actions/CreateQuotationScoringTemplate.php`
- Create: `apps/api/Domains/Quotation/Actions/UpdateQuotationScoringTemplate.php`
- Create: `apps/api/Domains/Quotation/Actions/DeactivateQuotationScoringTemplate.php`
- Create: `apps/api/Domains/Quotation/Actions/CreateRfqScorecard.php`
- Create: `apps/api/Domains/Quotation/Actions/UpdateRfqScorecardScores.php`
- Create: `apps/api/Domains/Quotation/Actions/CompleteRfqScorecard.php`
- Create: `apps/api/Domains/Quotation/Actions/ReopenRfqScorecard.php`
- Create: `apps/api/Domains/Quotation/Support/RfqScorecardCalculator.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/SaveQuotationScoringTemplateRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/CreateRfqScorecardRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/UpdateRfqScorecardScoresRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationScoringTemplateResource.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqScorecardResource.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/QuotationScoringTemplateController.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/RfqScorecardController.php`
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Test: `apps/api/tests/Feature/QuotationScoringApiTest.php`
- Test: `apps/api/tests/Unit/RfqScorecardCalculatorTest.php`

### Generated Client

- Modify generated files under `packages/api-client/src/generated/**` via `pnpm generate:api` and `pnpm check:api-contract`.

Expected generated endpoint wrappers from OpenAPI operation IDs:

- `listQuotationScoringTemplates`
- `createQuotationScoringTemplate`
- `showQuotationScoringTemplate`
- `updateQuotationScoringTemplate`
- `deactivateQuotationScoringTemplate`
- `showRfqScorecard`
- `createRfqScorecard`
- `updateRfqScorecardScores`
- `completeRfqScorecard`
- `reopenRfqScorecard`

### Web

- Create: `apps/web/app/(workspace)/quotations/scoring/[rfqId]/page.tsx`
- Create: `apps/web/app/(workspace)/quotations/scoring/templates/page.tsx`
- Create: `apps/web/app/(workspace)/quotations/scoring/templates/[templateId]/page.tsx`
- Create: `apps/web/features/quotations/api/quotation-scoring-api.ts`
- Create: `apps/web/features/quotations/hooks/use-quotation-scoring-templates.ts`
- Create: `apps/web/features/quotations/hooks/use-rfq-scorecard.ts`
- Create: `apps/web/features/quotations/hooks/use-rfq-scorecard-actions.ts`
- Create: `apps/web/features/quotations/workflows/quotation-scoring-template-list-page.tsx`
- Create: `apps/web/features/quotations/workflows/quotation-scoring-template-form-page.tsx`
- Create: `apps/web/features/quotations/workflows/rfq-scoring-workspace.tsx`
- Create: `apps/web/features/quotations/components/quotation-scoring-template-form.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-template-picker.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-completion-banner.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-matrix.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-vendor-summary.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-comparison-context.tsx`
- Create: `apps/web/features/quotations/mocks/quotation-scoring-fixtures.ts`
- Create: `apps/web/features/quotations/mocks/quotation-scoring-handlers.ts`
- Create: `apps/web/features/quotations/tests/quotation-scoring-api.test.ts`
- Create: `apps/web/features/quotations/tests/quotation-scoring-template-form.test.tsx`
- Create: `apps/web/features/quotations/tests/rfq-scoring-workspace.test.tsx`
- Modify: `apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`

### Docs

- Modify: `docs/01-product/feature-roadmap.md`
- Modify this plan during execution by checking completed boxes.

---

## Task 1: API Regression Tests For Scoring Boundaries

**Files:**

- Create: `apps/api/tests/Feature/QuotationScoringApiTest.php`

- [ ] **Step 1: Write failing feature tests**

Create `apps/api/tests/Feature/QuotationScoringApiTest.php`. Reuse tenant, RFQ, quotation, version, normalization, comparison, and session-auth helper patterns from `QuotationComparisonApiTest` and `QuotationNormalizationApiTest`.

Required test scenarios:

- `test_admin_can_create_update_list_and_deactivate_scoring_templates`: create a template as admin, update the name and criteria, list templates, deactivate the template, and assert it is inactive but still visible.
- `test_buyer_can_apply_active_template_to_rfq_and_snapshot_criteria`: create an active template, apply it to an RFQ as buyer, and assert frozen criteria match the template at apply time.
- `test_template_edits_do_not_change_existing_rfq_scorecard_criteria`: edit the template after applying it and assert `rfq_scorecard_criteria` still use the original labels, weights, max scores, and required flags.
- `test_buyer_can_update_scores_and_notes_and_receives_weighted_totals`: enter scores for two vendors across multiple criteria and assert raw totals, weighted totals, notes, and missing counts.
- `test_score_validation_rejects_values_outside_criterion_max_score`: submit a score above max score and assert `422` with a field error.
- `test_scorecard_completion_requires_required_scores_and_reopen_allows_edits`: assert incomplete completion returns `422`, complete scorecard becomes read-only, reopen allows edits again.
- `test_requester_approver_vendor_and_cross_tenant_users_cannot_access_scoring`: assert forbidden or not found responses for non-buyer/admin and cross-tenant actors.
- `test_scoring_actions_do_not_change_rfq_quotation_normalization_or_invitation_state`: snapshot states before scoring actions and assert they are unchanged afterwards.
- `test_scoring_routes_require_real_session_auth_and_tenant_context`: login through `/api/auth/login`, access read and mutation routes, logout, and assert later access returns `401`.

Assertions to include:

```php
$response->assertOk()
    ->assertJsonPath('data.template.name', 'Balanced RFQ Evaluation')
    ->assertJsonPath('data.criteria.0.category', 'cost')
    ->assertJsonPath('data.criteria.0.weight', '50.00')
    ->assertJsonPath('data.criteria.0.maxScore', 10)
    ->assertJsonPath('data.criteria.0.required', true);

$response->assertJsonPath('data.vendors.0.weightedTotal', '8.50')
    ->assertJsonPath('data.vendors.0.missingRequiredCount', 0)
    ->assertJsonPath('data.completion.status', 'complete');
```

Route middleware proof must use real session auth for at least:

```txt
POST /api/auth/login
GET /api/rfqs/{rfq}/scorecard
PATCH /api/rfqs/{rfq}/scorecard/scores
POST /api/auth/logout
GET /api/rfqs/{rfq}/scorecard
```

Expected after logout: `401`.

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd apps/api
php artisan test --filter=QuotationScoringApiTest
```

Expected: failures for missing classes, missing routes, or 404 endpoints.

- [ ] **Step 3: Commit tests**

```bash
git add apps/api/tests/Feature/QuotationScoringApiTest.php
git commit -m "test: define quotation scoring API behavior"
```

## Task 2: Scoring Data Model And States

**Files:**

- Create: `apps/api/database/migrations/2026_05_24_000000_create_quotation_scoring_templates_table.php`
- Create: `apps/api/database/migrations/2026_05_24_000001_create_rfq_scorecards_table.php`
- Create: `apps/api/Domains/Quotation/States/QuotationScoringCriterionCategory.php`
- Create: `apps/api/Domains/Quotation/States/RfqScorecardStatus.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationScoringTemplate.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationScoringTemplateCriterion.php`
- Create: `apps/api/Domains/Quotation/Models/RfqScorecard.php`
- Create: `apps/api/Domains/Quotation/Models/RfqScorecardCriterion.php`
- Create: `apps/api/Domains/Quotation/Models/RfqScorecardEntry.php`
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`

- [ ] **Step 1: Create migrations**

Create template tables:

```php
Schema::create('quotation_scoring_templates', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignUuid('created_by_user_id')->constrained('users');
    $table->foreignUuid('updated_by_user_id')->nullable()->constrained('users');
    $table->foreignUuid('deactivated_by_user_id')->nullable()->constrained('users');
    $table->timestamp('deactivated_at')->nullable();
    $table->timestamps();
    $table->unique(['tenant_id', 'name', 'is_active']);
});

Schema::create('quotation_scoring_template_criteria', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignUuid('template_id')->constrained('quotation_scoring_templates')->cascadeOnDelete();
    $table->string('category');
    $table->string('label');
    $table->text('guidance')->nullable();
    $table->decimal('weight', 8, 2);
    $table->unsignedInteger('max_score');
    $table->boolean('is_required')->default(true);
    $table->unsignedInteger('display_order');
    $table->timestamps();
    $table->unique(['template_id', 'display_order']);
});
```

Create scorecard tables:

```php
Schema::create('rfq_scorecards', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignUuid('rfq_id')->constrained('rfqs')->cascadeOnDelete();
    $table->foreignUuid('template_id')->nullable()->constrained('quotation_scoring_templates')->nullOnDelete();
    $table->string('template_name');
    $table->text('template_description')->nullable();
    $table->string('status')->default('in_progress');
    $table->foreignUuid('applied_by_user_id')->constrained('users');
    $table->timestamp('applied_at');
    $table->foreignUuid('completed_by_user_id')->nullable()->constrained('users');
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->unique(['tenant_id', 'rfq_id']);
});

Schema::create('rfq_scorecard_criteria', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignUuid('scorecard_id')->constrained('rfq_scorecards')->cascadeOnDelete();
    $table->foreignUuid('source_template_criterion_id')->nullable()->constrained('quotation_scoring_template_criteria')->nullOnDelete();
    $table->string('category');
    $table->string('label');
    $table->text('guidance')->nullable();
    $table->decimal('weight', 8, 2);
    $table->unsignedInteger('max_score');
    $table->boolean('is_required')->default(true);
    $table->unsignedInteger('display_order');
    $table->timestamps();
    $table->unique(['scorecard_id', 'display_order']);
});

Schema::create('rfq_scorecard_entries', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignUuid('scorecard_id')->constrained('rfq_scorecards')->cascadeOnDelete();
    $table->foreignUuid('scorecard_criterion_id')->constrained('rfq_scorecard_criteria')->cascadeOnDelete();
    $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
    $table->foreignUuid('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
    $table->foreignUuid('quotation_version_id')->nullable()->constrained('quotation_versions')->nullOnDelete();
    $table->decimal('score', 8, 2)->nullable();
    $table->text('note')->nullable();
    $table->foreignUuid('scored_by_user_id')->nullable()->constrained('users');
    $table->timestamp('scored_at')->nullable();
    $table->timestamps();
    $table->unique(['scorecard_id', 'scorecard_criterion_id', 'vendor_id']);
});
```

- [ ] **Step 2: Add states**

Create `QuotationScoringCriterionCategory`:

```php
enum QuotationScoringCriterionCategory: string
{
    case Cost = 'cost';
    case Delivery = 'delivery';
    case Quality = 'quality';
    case Compliance = 'compliance';
    case Risk = 'risk';
    case Sustainability = 'sustainability';
    case PastPerformance = 'past_performance';
    case Other = 'other';
}
```

Create `RfqScorecardStatus`:

```php
enum RfqScorecardStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
```

- [ ] **Step 3: Add models and relationships**

Each model must use `HasUuids`, guarded arrays consistent with nearby Quotation models, enum casts for `category` and `status`, and relationships to tenant, RFQ, template, criteria, entries, user, vendor, quotation, and version as applicable.

Add to `Rfq.php`:

```php
public function scorecard(): HasOne
{
    return $this->hasOne(RfqScorecard::class);
}
```

- [ ] **Step 4: Run migrations and model smoke tests**

```bash
cd apps/api
php artisan test --filter=QuotationScoringApiTest
```

Expected: migration/model errors resolved; route/action failures remain.

- [ ] **Step 5: Commit data model**

```bash
git add apps/api/database/migrations/2026_05_24_000000_create_quotation_scoring_templates_table.php apps/api/database/migrations/2026_05_24_000001_create_rfq_scorecards_table.php apps/api/Domains/Quotation/States/QuotationScoringCriterionCategory.php apps/api/Domains/Quotation/States/RfqScorecardStatus.php apps/api/Domains/Quotation/Models/QuotationScoringTemplate.php apps/api/Domains/Quotation/Models/QuotationScoringTemplateCriterion.php apps/api/Domains/Quotation/Models/RfqScorecard.php apps/api/Domains/Quotation/Models/RfqScorecardCriterion.php apps/api/Domains/Quotation/Models/RfqScorecardEntry.php apps/api/Domains/Quotation/Models/Rfq.php
git commit -m "feat: add quotation scoring data model"
```

## Task 3: Score Math, Template Actions, And Scorecard Actions

**Files:**

- Create: `apps/api/tests/Unit/RfqScorecardCalculatorTest.php`
- Create: `apps/api/Domains/Quotation/Support/RfqScorecardCalculator.php`
- Create: action files listed in the API file map.
- Create: `apps/api/Domains/Quotation/Policies/QuotationScoringTemplatePolicy.php`
- Create: `apps/api/Domains/Quotation/Policies/RfqScorecardPolicy.php`

- [ ] **Step 1: Write calculator unit tests**

Create tests named:

- `test_weighted_total_uses_score_divided_by_max_score_times_weight`
- `test_missing_scores_are_not_treated_as_zero`
- `test_completion_requires_required_scores_for_all_scoreable_vendors`

Use a criterion with `score=8`, `max_score=10`, `weight=50` and assert weighted contribution is `40.00`.

- [ ] **Step 2: Implement calculator**

Create methods:

```php
public function weightedContribution(float $score, int $maxScore, float $weight): string;
public function vendorTotals(RfqScorecard $scorecard): array;
public function completionSummary(RfqScorecard $scorecard): array;
```

Return decimal strings rounded to two places to match API resource output.

- [ ] **Step 3: Implement policies**

Rules:

```php
// QuotationScoringTemplatePolicy
viewAny: buyer or admin tenant role
view: same tenant and buyer/admin
create/update/deactivate: admin only

// RfqScorecardPolicy
view/create/update/complete/reopen: buyer or admin for same tenant RFQ
```

Do not grant access to requester, approver, or vendor portal contexts.

- [ ] **Step 4: Implement template actions**

`CreateQuotationScoringTemplate` validates at least one criterion, creates the template and ordered criteria in a transaction, and records `quotation_scoring_template.created`.

`UpdateQuotationScoringTemplate` updates the reusable template and replaces current template criteria in a transaction for future RFQs only. Existing `rfq_scorecard_criteria` must not be changed. Record `quotation_scoring_template.updated`.

`DeactivateQuotationScoringTemplate` sets `is_active=false`, `deactivated_by_user_id`, and `deactivated_at`. Record `quotation_scoring_template.deactivated`.

- [ ] **Step 5: Implement scorecard actions**

`CreateRfqScorecard`:

- rejects inactive templates;
- rejects RFQs that already have a scorecard with `409`;
- snapshots `template_name`, `template_description`, and all criteria;
- sets `status=in_progress`, `applied_by_user_id`, `applied_at`;
- records `quotation_scorecard.created`.

`UpdateRfqScorecardScores`:

- rejects completed scorecards with `409`;
- validates criterion/vendor/quotation membership;
- upserts entries by scorecard, criterion, and vendor;
- records `quotation_scorecard.score_updated` with before/after score values.

`CompleteRfqScorecard`:

- rejects incomplete required scores with `422`;
- sets `status=completed`, `completed_by_user_id`, `completed_at`;
- records `quotation_scorecard.completed`.

`ReopenRfqScorecard`:

- sets `status=in_progress`;
- clears `completed_by_user_id` and `completed_at`;
- records `quotation_scorecard.reopened`.

- [ ] **Step 6: Run focused API tests**

```bash
cd apps/api
php artisan test --filter=RfqScorecardCalculatorTest
php artisan test --filter=QuotationScoringApiTest
```

Expected: calculator tests pass; API tests fail only where controllers/routes/resources are still missing.

- [ ] **Step 7: Commit actions**

```bash
git add apps/api/tests/Unit/RfqScorecardCalculatorTest.php apps/api/Domains/Quotation/Support/RfqScorecardCalculator.php apps/api/Domains/Quotation/Actions/CreateQuotationScoringTemplate.php apps/api/Domains/Quotation/Actions/UpdateQuotationScoringTemplate.php apps/api/Domains/Quotation/Actions/DeactivateQuotationScoringTemplate.php apps/api/Domains/Quotation/Actions/CreateRfqScorecard.php apps/api/Domains/Quotation/Actions/UpdateRfqScorecardScores.php apps/api/Domains/Quotation/Actions/CompleteRfqScorecard.php apps/api/Domains/Quotation/Actions/ReopenRfqScorecard.php apps/api/Domains/Quotation/Policies/QuotationScoringTemplatePolicy.php apps/api/Domains/Quotation/Policies/RfqScorecardPolicy.php
git commit -m "feat: add quotation scoring domain actions"
```

## Task 4: API Requests, Resources, Controllers, Routes

**Files:**

- Create request, resource, and controller files listed in the API file map.
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add request validation**

`SaveQuotationScoringTemplateRequest` rules:

```php
'name' => ['required', 'string', 'max:160'],
'description' => ['nullable', 'string', 'max:2000'],
'criteria' => ['required', 'array', 'min:1'],
'criteria.*.category' => ['required', Rule::enum(QuotationScoringCriterionCategory::class)],
'criteria.*.label' => ['required', 'string', 'max:160'],
'criteria.*.guidance' => ['nullable', 'string', 'max:2000'],
'criteria.*.weight' => ['required', 'numeric', 'gt:0'],
'criteria.*.maxScore' => ['required', 'integer', 'min:1', 'max:100'],
'criteria.*.required' => ['required', 'boolean'],
'criteria.*.displayOrder' => ['required', 'integer', 'min:1'],
```

`CreateRfqScorecardRequest` rules:

```php
'templateId' => ['required', 'uuid', 'exists:quotation_scoring_templates,id'],
```

`UpdateRfqScorecardScoresRequest` rules:

```php
'entries' => ['required', 'array', 'min:1'],
'entries.*.criterionId' => ['required', 'uuid'],
'entries.*.vendorId' => ['required', 'uuid'],
'entries.*.quotationId' => ['nullable', 'uuid'],
'entries.*.quotationVersionId' => ['nullable', 'uuid'],
'entries.*.score' => ['nullable', 'numeric', 'min:0'],
'entries.*.note' => ['nullable', 'string', 'max:2000'],
```

- [ ] **Step 2: Add resources**

`QuotationScoringTemplateResource` outputs:

```php
[
    'id' => (string) $this->id,
    'name' => $this->name,
    'description' => $this->description,
    'active' => (bool) $this->is_active,
    'criteria' => $this->criteria->map(fn ($criterion) => [
        'id' => (string) $criterion->id,
        'category' => $criterion->category->value,
        'label' => $criterion->label,
        'guidance' => $criterion->guidance,
        'weight' => number_format((float) $criterion->weight, 2, '.', ''),
        'maxScore' => $criterion->max_score,
        'required' => (bool) $criterion->is_required,
        'displayOrder' => $criterion->display_order,
    ])->values(),
    'usageCount' => $this->scorecards_count ?? 0,
    'permissions' => [
        'canUpdate' => $request->user()->can('update', $this->resource),
        'canDeactivate' => $request->user()->can('deactivate', $this->resource),
    ],
]
```

`RfqScorecardResource` outputs:

```php
[
    'rfq' => ['id', 'number', 'title', 'status', 'responseDueAt'],
    'scorecard' => ['id', 'status', 'templateId', 'templateName', 'appliedAt', 'completedAt'],
    'criteria' => ['id', 'category', 'label', 'guidance', 'weight', 'maxScore', 'required', 'displayOrder'],
    'vendors' => ['vendorId', 'vendorName', 'quotationId', 'quotationVersionId', 'scoreable', 'rawTotal', 'weightedTotal', 'missingRequiredCount', 'readiness'],
    'entries' => ['criterionId', 'vendorId', 'quotationId', 'quotationVersionId', 'score', 'note', 'weightedContribution', 'scoredAt'],
    'completion' => ['status', 'requiredScoreCount', 'completedRequiredScoreCount', 'missingRequiredScoreCount', 'scoreableVendorCount'],
    'comparisonContext' => ['comparisonPath', 'normalizationPaths', 'quotationVersionPaths', 'readiness', 'commercialTerms'],
    'permissions' => ['canViewScorecard', 'canApplyScorecard', 'canManageScores', 'canManageScoringTemplates'],
]
```

Use `BuildQuotationComparison` or the same query sources as comparison to populate comparison readiness and links. Do not copy comparison values into new persisted scoring tables.

- [ ] **Step 3: Add controllers**

`QuotationScoringTemplateController`:

```php
index()
store(SaveQuotationScoringTemplateRequest $request)
show(QuotationScoringTemplate $template)
update(SaveQuotationScoringTemplateRequest $request, QuotationScoringTemplate $template)
deactivate(QuotationScoringTemplate $template)
```

`RfqScorecardController`:

```php
show(Rfq $rfq)
store(CreateRfqScorecardRequest $request, Rfq $rfq)
updateScores(UpdateRfqScorecardScoresRequest $request, Rfq $rfq)
complete(Rfq $rfq)
reopen(Rfq $rfq)
```

Controllers must stay thin: authorize, call actions, return resources.

- [ ] **Step 4: Add routes**

Inside the authenticated tenant-scoped route group:

```php
Route::prefix('quotation-scoring')->group(function (): void {
    Route::get('templates', [QuotationScoringTemplateController::class, 'index']);
    Route::post('templates', [QuotationScoringTemplateController::class, 'store']);
    Route::get('templates/{template}', [QuotationScoringTemplateController::class, 'show']);
    Route::patch('templates/{template}', [QuotationScoringTemplateController::class, 'update']);
    Route::post('templates/{template}/deactivate', [QuotationScoringTemplateController::class, 'deactivate']);
});

Route::get('rfqs/{rfq}/scorecard', [RfqScorecardController::class, 'show']);
Route::post('rfqs/{rfq}/scorecard', [RfqScorecardController::class, 'store']);
Route::patch('rfqs/{rfq}/scorecard/scores', [RfqScorecardController::class, 'updateScores']);
Route::post('rfqs/{rfq}/scorecard/complete', [RfqScorecardController::class, 'complete']);
Route::post('rfqs/{rfq}/scorecard/reopen', [RfqScorecardController::class, 'reopen']);
```

- [ ] **Step 5: Run API tests and route checks**

```bash
cd apps/api
php artisan test --filter=QuotationScoringApiTest
php artisan test --filter=RfqScorecardCalculatorTest
php artisan route:list --path=api/quotation-scoring
php artisan route:list --path=api/rfqs
```

Expected: tests pass; route list includes scoring template and RFQ scorecard routes.

- [ ] **Step 6: Commit API surface**

```bash
git add apps/api/Domains/Quotation/Http/Requests/SaveQuotationScoringTemplateRequest.php apps/api/Domains/Quotation/Http/Requests/CreateRfqScorecardRequest.php apps/api/Domains/Quotation/Http/Requests/UpdateRfqScorecardScoresRequest.php apps/api/Domains/Quotation/Http/Resources/QuotationScoringTemplateResource.php apps/api/Domains/Quotation/Http/Resources/RfqScorecardResource.php apps/api/Domains/Quotation/Http/Controllers/QuotationScoringTemplateController.php apps/api/Domains/Quotation/Http/Controllers/RfqScorecardController.php apps/api/routes/api.php
git commit -m "feat: expose quotation scoring API"
```

## Task 5: OpenAPI And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated files under `packages/api-client/src/generated/**`

- [ ] **Step 1: Add OpenAPI schemas and paths**

Add paths matching the route list and operation IDs from this plan. Define schemas:

```txt
QuotationScoringTemplate
QuotationScoringTemplateCriterion
SaveQuotationScoringTemplateRequest
RfqScorecardResponse
RfqScorecard
RfqScorecardCriterion
RfqScorecardVendor
RfqScorecardEntry
RfqScorecardCompletion
RfqScorecardComparisonContext
RfqScorecardPermissions
CreateRfqScorecardRequest
UpdateRfqScorecardScoresRequest
```

Use camelCase JSON property names matching web conventions: `maxScore`, `displayOrder`, `templateId`, `criterionId`, `vendorId`, `quotationVersionId`, `weightedTotal`, `missingRequiredCount`.

- [ ] **Step 2: Generate client and verify contract**

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
```

Expected: generated endpoint wrappers and schemas exist with no contract drift failures.

- [ ] **Step 3: Commit contract**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: add quotation scoring contract"
```

## Task 6: Web API Wrappers, Hooks, And MSW Fixtures

**Files:**

- Create: `apps/web/features/quotations/api/quotation-scoring-api.ts`
- Create: `apps/web/features/quotations/hooks/use-quotation-scoring-templates.ts`
- Create: `apps/web/features/quotations/hooks/use-rfq-scorecard.ts`
- Create: `apps/web/features/quotations/hooks/use-rfq-scorecard-actions.ts`
- Create: `apps/web/features/quotations/mocks/quotation-scoring-fixtures.ts`
- Create: `apps/web/features/quotations/mocks/quotation-scoring-handlers.ts`
- Create: `apps/web/features/quotations/tests/quotation-scoring-api.test.ts`

- [ ] **Step 1: Write API wrapper tests**

Test that wrappers pass tenant headers and call the generated operations. Include tests named:

- `loads scoring templates through the generated client`
- `applies an RFQ scorecard template`
- `updates score entries`
- `completes and reopens the scorecard`

- [ ] **Step 2: Add API wrappers**

Create wrapper functions:

```ts
export async function listScoringTemplates(tenantId: string): Promise<QuotationScoringTemplate[]>;
export async function saveScoringTemplate(input: SaveScoringTemplateInput, tenantId: string): Promise<QuotationScoringTemplate>;
export async function deactivateScoringTemplate(templateId: string, tenantId: string): Promise<QuotationScoringTemplate>;
export async function getRfqScorecard(rfqId: string, tenantId: string): Promise<RfqScorecardResponse>;
export async function createRfqScorecard(rfqId: string, templateId: string, tenantId: string): Promise<RfqScorecardResponse>;
export async function updateRfqScorecardScores(rfqId: string, entries: UpdateScoreEntryInput[], tenantId: string): Promise<RfqScorecardResponse>;
export async function completeRfqScorecard(rfqId: string, tenantId: string): Promise<RfqScorecardResponse>;
export async function reopenRfqScorecard(rfqId: string, tenantId: string): Promise<RfqScorecardResponse>;
```

Use generated schemas from `@cognify/api-client`; do not duplicate response types.

- [ ] **Step 3: Add hooks**

Query keys:

```ts
export const quotationScoringKeys = {
  templates: (tenantId: string | null) => ["quotation-scoring", tenantId, "templates"] as const,
  scorecard: (tenantId: string | null, rfqId: string) => ["quotation-scoring", tenantId, "scorecard", rfqId] as const,
};
```

Mutations must invalidate the relevant template or scorecard query after success.

- [ ] **Step 4: Add MSW fixtures and handlers**

Fixtures must include:

- two active templates;
- one inactive template;
- one RFQ scorecard with two vendors and three criteria;
- one incomplete scorecard state;
- one completed scorecard state.

Handlers must cover all scoring endpoints and export:

```ts
export function resetQuotationScoringMockState(): void;
```

- [ ] **Step 5: Run web API tests**

```bash
pnpm --filter @cognify/web test -- quotation-scoring-api
```

Expected: tests pass.

- [ ] **Step 6: Commit web API layer**

```bash
git add apps/web/features/quotations/api/quotation-scoring-api.ts apps/web/features/quotations/hooks/use-quotation-scoring-templates.ts apps/web/features/quotations/hooks/use-rfq-scorecard.ts apps/web/features/quotations/hooks/use-rfq-scorecard-actions.ts apps/web/features/quotations/mocks/quotation-scoring-fixtures.ts apps/web/features/quotations/mocks/quotation-scoring-handlers.ts apps/web/features/quotations/tests/quotation-scoring-api.test.ts
git commit -m "feat: add quotation scoring web API hooks"
```

## Task 7: Scoring Template Admin UI

**Files:**

- Create: `apps/web/app/(workspace)/quotations/scoring/templates/page.tsx`
- Create: `apps/web/app/(workspace)/quotations/scoring/templates/[templateId]/page.tsx`
- Create: `apps/web/features/quotations/workflows/quotation-scoring-template-list-page.tsx`
- Create: `apps/web/features/quotations/workflows/quotation-scoring-template-form-page.tsx`
- Create: `apps/web/features/quotations/components/quotation-scoring-template-form.tsx`
- Create: `apps/web/features/quotations/tests/quotation-scoring-template-form.test.tsx`

- [ ] **Step 1: Write UI tests**

Required test names:

- `renders active and inactive scoring templates for admins`
- `validates that a template has at least one criterion`
- `adds removes and reorders criteria`
- `saves a scoring template with criterion weights and max scores`
- `deactivates a template without deleting historical usage`

- [ ] **Step 2: Add template routes**

`page.tsx` files should delegate to workflow components and parse route params only.

- [ ] **Step 3: Build template list page**

Use existing app-shell and table patterns. Show:

- name;
- active state;
- criterion count;
- total weight;
- usage count;
- last updated;
- create/edit/deactivate actions.

- [ ] **Step 4: Build template form**

Use shadcn/Radix primitives through `packages/ui`. Controls:

- text input for name;
- textarea for description;
- select for category;
- inputs for label, guidance, weight, max score;
- required toggle;
- reorder controls using icon buttons;
- add/remove criterion buttons.

Validation messages:

- `Template name is required.`
- `Add at least one criterion.`
- `Weight must be greater than 0.`
- `Max score must be between 1 and 100.`

- [ ] **Step 5: Run template UI tests**

```bash
pnpm --filter @cognify/web test -- quotation-scoring-template-form
```

Expected: tests pass.

- [ ] **Step 6: Commit template UI**

```bash
git add apps/web/app/\(workspace\)/quotations/scoring/templates/page.tsx apps/web/app/\(workspace\)/quotations/scoring/templates/\[templateId\]/page.tsx apps/web/features/quotations/workflows/quotation-scoring-template-list-page.tsx apps/web/features/quotations/workflows/quotation-scoring-template-form-page.tsx apps/web/features/quotations/components/quotation-scoring-template-form.tsx apps/web/features/quotations/tests/quotation-scoring-template-form.test.tsx
git commit -m "feat: add quotation scoring template UI"
```

## Task 8: RFQ Scoring Workspace UI

**Files:**

- Create: `apps/web/app/(workspace)/quotations/scoring/[rfqId]/page.tsx`
- Create: `apps/web/features/quotations/workflows/rfq-scoring-workspace.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-template-picker.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-completion-banner.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-matrix.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-vendor-summary.tsx`
- Create: `apps/web/features/quotations/components/rfq-scorecard-comparison-context.tsx`
- Create: `apps/web/features/quotations/tests/rfq-scoring-workspace.test.tsx`

- [ ] **Step 1: Write workspace tests**

Required test names:

- `shows template picker when an RFQ has no scorecard`
- `creates a scorecard from the selected active template`
- `renders vendor summaries and score matrix`
- `updates scores and criterion notes`
- `shows missing required score indicators`
- `completes a scorecard only when required scores are present`
- `renders completed scorecards as read only until reopened`
- `links back to the quotation comparison workspace`

- [ ] **Step 2: Add route page**

Create a thin route file that renders `RfqScoringWorkspace` with `rfqId`.

- [ ] **Step 3: Build workspace shell**

Use `RecordWorkspaceLayout`. Header actions:

- Back to comparison;
- Apply template when no scorecard exists;
- Complete scoring when required scores are present;
- Reopen scoring when completed.

Do not show award, recommendation, shortlist, or winner language.

- [ ] **Step 4: Build template picker**

Show active templates with name, criterion count, total weight, and description. Disable apply while the mutation is pending.

- [ ] **Step 5: Build scorecard matrix**

Desktop matrix:

- criteria rows;
- vendor columns;
- score input per cell;
- note input per cell;
- weighted contribution display;
- missing required indicator.

Mobile layout:

- vendor card;
- criteria sections inside each card;
- fixed-width inputs so labels do not shift layout.

- [ ] **Step 6: Build completion and comparison context panels**

Completion banner states:

- incomplete with missing required counts;
- ready to complete;
- completed read-only;
- reopened/in progress.

Comparison context shows selected totals, delivery, compliance, readiness, and links to comparison/normalization/quotation version workspaces. It does not persist comparison values in scoring state.

- [ ] **Step 7: Run workspace tests**

```bash
pnpm --filter @cognify/web test -- rfq-scoring-workspace
```

Expected: tests pass.

- [ ] **Step 8: Commit RFQ scoring workspace**

```bash
git add apps/web/app/\(workspace\)/quotations/scoring/\[rfqId\]/page.tsx apps/web/features/quotations/workflows/rfq-scoring-workspace.tsx apps/web/features/quotations/components/rfq-scorecard-template-picker.tsx apps/web/features/quotations/components/rfq-scorecard-completion-banner.tsx apps/web/features/quotations/components/rfq-scorecard-matrix.tsx apps/web/features/quotations/components/rfq-scorecard-vendor-summary.tsx apps/web/features/quotations/components/rfq-scorecard-comparison-context.tsx apps/web/features/quotations/tests/rfq-scoring-workspace.test.tsx
git commit -m "feat: add RFQ scoring workspace"
```

## Task 9: Route Integration, Shell Config, And Roadmap Loopback

**Files:**

- Modify: `apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`
- Modify: `docs/01-product/feature-roadmap.md`
- Modify: `docs/superpowers/plans/2026-05-24-vendor-scoring-matrix.md`

- [ ] **Step 1: Link comparison to scoring**

Add a buyer/admin scoring action near the comparison header:

```tsx
<Button asChild variant="secondary">
  <Link href={`/quotations/scoring/${comparison.rfq.id}`}>Open scoring</Link>
</Button>
```

Copy must say `Open scoring`, not `Recommend vendor` or `Award vendor`.

- [ ] **Step 2: Add shell route config**

Add route patterns for:

```txt
/quotations/scoring/[rfqId]
/quotations/scoring/templates
/quotations/scoring/templates/[templateId]
```

Breadcrumbs should be operational:

```txt
Quotations > Scoring > RFQ
Quotations > Scoring Templates
```

Do not add a standalone scoring queue nav item.

- [ ] **Step 3: Update roadmap after implementation verification**

Update P1-31 row:

```md
| P1-31 | Vendor Scoring Matrix | Score vendor responses using configurable criteria such as cost, delivery, quality, compliance, risk, sustainability, and past performance. Scores help explain award recommendations. | Fully Implemented | 2026-05-24-vendor-scoring-matrix-design.md | 2026-05-24-vendor-scoring-matrix.md |  | Implemented as lightweight admin scoring templates plus RFQ scorecard snapshots with buyer scoring, weighted totals, completion/reopen workflow, audit events, and no award-state side effects. |
```

Keep P1-32 through P1-34 as `Not Implemented`.

- [ ] **Step 4: Run integration tests**

```bash
pnpm --filter @cognify/web test -- quotation-comparison-workspace
pnpm --filter @cognify/web test -- shell-route-config
pnpm --filter @cognify/web test -- quotation-scoring
```

Expected: tests pass.

- [ ] **Step 5: Commit integration and docs**

```bash
git add apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx apps/web/components/shell/shell-route-config.ts apps/web/components/shell/shell-route-config.test.tsx docs/01-product/feature-roadmap.md docs/superpowers/plans/2026-05-24-vendor-scoring-matrix.md
git commit -m "docs: mark vendor scoring implemented"
```

## Task 10: Final Verification And Scope Audit

**Files:**

- No new files unless verification reveals a defect.

- [ ] **Step 1: Run focused backend verification**

```bash
cd apps/api
php artisan test --filter=QuotationScoringApiTest
php artisan test --filter=RfqScorecardCalculatorTest
php artisan route:list --path=api/quotation-scoring
php artisan route:list --path=api/rfqs
```

Expected: tests pass and routes include scoring endpoints.

- [ ] **Step 2: Run contract verification**

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
```

Expected: no diff from regenerated client after committed generated files; typecheck passes.

- [ ] **Step 3: Run focused frontend verification**

```bash
pnpm --filter @cognify/web test -- quotation-scoring
pnpm --filter @cognify/web test -- quotation-comparison-workspace
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
```

Expected: tests, lint, and typecheck pass.

- [ ] **Step 4: Run root verification**

```bash
pnpm typecheck
pnpm test
git diff --check
```

Expected: all pass; `git diff --check` has no output.

- [ ] **Step 5: Run scope grep**

```bash
rg -n "winner|recommended vendor|award decision|shortlist|excluded|purchase order|AI scoring|currency conversion|template approval|template clone|version browser" apps/api/Domains/Quotation apps/web/features/quotations docs/01-product/feature-roadmap.md
```

Expected: matches are limited to explicit non-goals, future-slice copy, or user-facing warnings that scoring is not award/recommendation behavior.

- [ ] **Step 6: Commit verification fixes if needed**

If verification required changes, stage the specific files changed by those fixes and commit:

```bash
git status --short
git add apps/api/Domains/Quotation/Actions/UpdateRfqScorecardScores.php apps/web/features/quotations/workflows/rfq-scoring-workspace.tsx
git commit -m "fix: harden vendor scoring verification"
```

If no changes were required, leave the branch without an extra verification commit.

## Final Handoff Checklist

- [ ] Design spec coverage: template CRUD/deactivate, RFQ scorecard snapshot, score entry notes, totals, completion/reopen, comparison context, permissions, audit, OpenAPI, web UI, and verification are covered.
- [ ] Scope check: P1-32 recommendation/award decision, P1-33 award approval, and P1-34 PO handoff remain out of scope.
- [ ] Architecture check: backend business behavior lives in `apps/api/Domains/Quotation`; web feature code lives in `apps/web/features/quotations`; generated API client is the contract source for web consumers.
- [ ] Tenant check: every scoring query and mutation validates tenant ownership across template, RFQ, vendor, quotation, quotation version, scorecard, criterion, entry, and actor.
- [ ] Contract check: OpenAPI and generated client are regenerated and verified.
- [ ] Runtime check: real session-auth route coverage exists for protected scoring read and mutation endpoints.
