# Quotation Normalization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-29 so every current quotation version gets an auditable buyer-reviewable normalization record that downstream comparison can consume without reading raw quotation fields directly.

**Architecture:** Keep durable normalization state, deterministic normalization, review actions, policies, jobs, audit, and notifications inside `apps/api/Domains/Quotation`. Expose normalization review through OpenAPI-generated endpoints consumed by a new `apps/web/features/quotations` workspace, while keeping vendor portal endpoints free of normalization data. Approved normalization revisions are immutable; draft revisions are mutable only until approval.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped policies/actions, Laravel queues, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix primitives through `packages/ui`.

---

## Grounding

- Product roadmap: `docs/01-product/feature-roadmap.md` lists P1-29 `Quotation Normalization` as the next unimplemented quotation slice after P1-26 upload, P1-27 manual entry, and P1-28 versioning. P1-30 comparison, P1-31 scoring, and P1-32 award decision stay out of scope.
- Design spec: `docs/superpowers/specs/2026-05-21-quotation-normalization-design.md`.
- Architecture: `ARCHITECTURE.md` requires tenant isolation, backend-owned workflow decisions, OpenAPI/generated clients as the web/API contract, and first-class audit/notification/queue side effects.
- Runbook: `docs/05-runbooks/feature-development.md` requires workflow-first, contract-first vertical slices with feature-local web code and business behavior in `apps/api/Domains/Quotation`.
- Existing implementation context: quotation upload, manual entry, and versioning already exist in `apps/api/Domains/Quotation`, buyer RFQ surfaces live under `apps/web/features/sourcing`, vendor portal surfaces live under `apps/web/features/vendor-portal`, and P1-29 should introduce `apps/web/features/quotations` for the normalization review queue/workspace.

## Scope Decision

This design is broad, but it is one coherent implementation slice because the work centers on one durable workflow: normalize one submitted quotation version, let a buyer/admin review it, and approve a frozen normalization revision. Do not split this into several plan files. Keep downstream comparison, scoring, awards, OCR, AI extraction, reusable normalization rules, and vendor-facing review out of this implementation.

## Workflow Map

Actors:

- System queue worker: creates and retries normalization records for submitted quotation versions.
- Buyer/admin: reviews issues, applies corrections, maps lines, approves, approves with warnings, or creates a new draft revision from an approved normalization.
- Requester/approver: cannot access normalization review endpoints in this slice.
- Vendor portal visitor: cannot see normalization data, review issues, corrections, or approval status.

States:

- `pending`: record exists but job has not started.
- `processing`: deterministic normalizer is running.
- `needs_review`: blocking issues exist or buyer mapping is required.
- `ready_for_approval`: no blocking issues remain.
- `approved`: immutable approved revision with no unresolved warnings.
- `approved_with_warnings`: immutable approved revision with explicit warning acknowledgement.
- `failed`: job failed and can be retried by buyer/admin.
- `superseded`: unapproved normalization became inactive because a newer quotation version is current.

Transitions:

- New current `QuotationVersion` is created -> dispatch `NormalizeQuotationVersion` -> create or reset draft normalization for that version -> normalize fields/lines/attachments -> status becomes `ready_for_approval`, `needs_review`, or `failed`.
- Newer current version is created -> mark older unapproved normalizations for the same quotation as `superseded`.
- Buyer/admin correction -> write correction row, update draft normalized field/issue status, audit `quotation_normalization.correction_saved`.
- Buyer/admin line mapping -> replace draft line groups/mappings, recompute blocking issue status, audit `quotation_normalization.line_mapping_saved`.
- Approve -> require no unresolved blocking issues, freeze record as `approved`, audit and notify.
- Approve with warnings -> require no unresolved blocking issues and an acknowledgement note, freeze as `approved_with_warnings`, audit and notify.
- Create revision -> copy latest approved normalization into a new mutable revision for the same quotation version, audit `quotation_normalization.revision_created`.

Side effects:

- Audit events listed in the design spec are required.
- In-app notifications are required for `failed`, `needs_review`, `approved`, and `approved_with_warnings`; vendors must never receive these notifications.
- Queue work must be idempotent and tenant-scoped.

Tenant and permission rules:

- All authenticated endpoints use `auth:sanctum` plus `ResolveCurrentTenant`.
- Add `canReviewQuotationNormalization` to the permission contract: true for `buyer` and `admin`; false for `requester`, `approver`, and default.
- Backend policies remain authoritative. Every normalization query/mutation must verify tenant ownership across normalization, quotation version, quotation, RFQ, invitation, vendor, line items, attachments, and actor.

---

## File Map

### API: State, Models, Migrations

- Create: `apps/api/Domains/Quotation/States/QuotationNormalizationStatus.php`
  - Enum for normalization lifecycle statuses.
- Create: `apps/api/Domains/Quotation/States/QuotationNormalizationIssueSeverity.php`
  - Enum for `blocking`, `warning`, `info`.
- Create: `apps/api/Domains/Quotation/States/QuotationNormalizationIssueStatus.php`
  - Enum for `open`, `resolved`, `acknowledged`.
- Create: `apps/api/Domains/Quotation/States/QuotationNormalizationPricingMode.php`
  - Enum for `per_line`, `bundle`, `included`, `unknown`.
- Create: `apps/api/Domains/Quotation/States/QuotationNormalizationMappingType.php`
  - Enum for `full`, `partial`, `bundled`.
- Create: `apps/api/database/migrations/2026_05_21_000000_create_quotation_normalizations_table.php`
  - Stores one mutable or approved normalization revision per quotation version.
- Create: `apps/api/database/migrations/2026_05_21_000001_create_quotation_normalization_fields_table.php`
  - Stores comparable header/commercial normalized fields with provenance.
- Create: `apps/api/database/migrations/2026_05_21_000002_create_quotation_normalization_line_groups_table.php`
  - Stores buyer-reviewable comparable line groups.
- Create: `apps/api/database/migrations/2026_05_21_000003_create_quotation_normalization_line_mappings_table.php`
  - Stores RFQ/version line many-to-many mappings.
- Create: `apps/api/database/migrations/2026_05_21_000004_create_quotation_normalization_attachments_table.php`
  - Stores attachment evidence metadata snapshots.
- Create: `apps/api/database/migrations/2026_05_21_000005_create_quotation_normalization_issues_table.php`
  - Stores review issues and resolution metadata.
- Create: `apps/api/database/migrations/2026_05_21_000006_create_quotation_normalization_corrections_table.php`
  - Stores field corrections without mutating source quotation versions.
- Create: `apps/api/Domains/Quotation/Models/QuotationNormalization.php`
  - Root normalization model and relationships.
- Create: `apps/api/Domains/Quotation/Models/QuotationNormalizationField.php`
  - Normalized field model.
- Create: `apps/api/Domains/Quotation/Models/QuotationNormalizationLineGroup.php`
  - Line group model.
- Create: `apps/api/Domains/Quotation/Models/QuotationNormalizationLineMapping.php`
  - Mapping model.
- Create: `apps/api/Domains/Quotation/Models/QuotationNormalizationAttachment.php`
  - Attachment metadata model.
- Create: `apps/api/Domains/Quotation/Models/QuotationNormalizationIssue.php`
  - Issue model.
- Create: `apps/api/Domains/Quotation/Models/QuotationNormalizationCorrection.php`
  - Correction model.
- Modify: `apps/api/Domains/Quotation/Models/QuotationVersion.php`
  - Add `normalizations()` and `currentNormalization()` relationships.
- Modify: `apps/api/Domains/Quotation/Models/Quotation.php`
  - Add `currentVersion.currentNormalization` eager-load support where quotation resources need to link to the review workspace.

### API: Normalization Behavior

- Create: `apps/api/Domains/Quotation/Jobs/NormalizeQuotationVersion.php`
  - Idempotent queued job that runs deterministic normalization for one quotation version.
- Create: `apps/api/Domains/Quotation/Actions/StartQuotationNormalization.php`
  - Creates a pending/processing draft normalization, supersedes older unapproved records, and records start audit.
- Create: `apps/api/Domains/Quotation/Actions/RunDeterministicQuotationNormalizer.php`
  - Normalizes structured fields, line items, attachment metadata, totals, and issues.
- Create: `apps/api/Domains/Quotation/Actions/SaveQuotationNormalizationCorrections.php`
  - Applies buyer/admin corrections to mutable draft normalization fields and issues.
- Create: `apps/api/Domains/Quotation/Actions/SaveQuotationNormalizationLineMappings.php`
  - Replaces draft line groups/mappings and resolves mapping-related issues.
- Create: `apps/api/Domains/Quotation/Actions/ApproveQuotationNormalization.php`
  - Freezes an approved or approved-with-warnings revision.
- Create: `apps/api/Domains/Quotation/Actions/CreateQuotationNormalizationRevision.php`
  - Copies the latest approved normalization into a new mutable draft revision.
- Create: `apps/api/Domains/Quotation/Actions/RetryQuotationNormalization.php`
  - Requeues a failed normalization.
- Create: `apps/api/Domains/Quotation/Support/QuotationNormalizationNotifier.php`
  - Finds tenant buyer/admin recipients and records notification rows.
- Create: `apps/api/Domains/Quotation/Support/QuotationNormalizationIssueCatalog.php`
  - Central constants for issue codes and default messages.
- Create: `apps/api/Domains/Quotation/Support/QuotationNormalizationProvenance.php`
  - Helper to build field-level provenance arrays consistently.
- Modify: `apps/api/Domains/Quotation/Actions/CreateQuotationVersionSnapshot.php`
  - Dispatch normalization after a new current version is committed.

### API: Policies, Requests, Resources, Controllers, Routes

- Create: `apps/api/Domains/Quotation/Policies/QuotationNormalizationPolicy.php`
  - Authorizes review, correction, mapping, approval, revision, and retry.
- Create: `apps/api/Domains/Quotation/Http/Requests/ListQuotationNormalizationsRequest.php`
  - Validates status/filter input for the queue list.
- Create: `apps/api/Domains/Quotation/Http/Requests/SaveQuotationNormalizationCorrectionsRequest.php`
  - Validates field corrections and issue resolutions.
- Create: `apps/api/Domains/Quotation/Http/Requests/SaveQuotationNormalizationLineMappingsRequest.php`
  - Validates line groups and mappings.
- Create: `apps/api/Domains/Quotation/Http/Requests/ApproveQuotationNormalizationRequest.php`
  - Validates approval note/acknowledgement.
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationNormalizationResource.php`
  - Full buyer/admin workspace payload.
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationNormalizationSummaryResource.php`
  - Queue-list row payload.
- Create: `apps/api/Domains/Quotation/Http/Controllers/QuotationNormalizationController.php`
  - `index`, `show`, `corrections`, `lineMappings`, `approve`, `approveWithWarnings`, `revision`.
- Create: `apps/api/Domains/Quotation/Http/Controllers/QuotationVersionNormalizationController.php`
  - `retry` endpoint for failed current version normalizations.
- Modify: `apps/api/routes/api.php`
  - Add authenticated normalization routes inside `ResolveCurrentTenant`; do not add vendor portal routes.
- Modify: `apps/api/app/Auth/Permissions/TenantPermissionResolver.php`
  - Add `canReviewQuotationNormalization`.
- Modify generated contract source: `apps/api/storage/openapi/openapi.json`
  - Add schemas/endpoints for the new resources and permission flag.
- Modify generated client: `packages/api-client/src/generated/**`
  - Regenerate through `pnpm check:api-contract`.

### API: Tests

- Create: `apps/api/tests/Feature/QuotationNormalizationApiTest.php`
  - End-to-end endpoint, permission, tenant, approval, revision, audit, and notification tests.
- Create: `apps/api/tests/Unit/QuotationDeterministicNormalizerTest.php`
  - Focused normalizer cases for field parsing, issues, totals, attachments, and line mapping seeds.
- Modify: `apps/api/tests/Feature/QuotationVersionApiTest.php`
  - Assert version creation dispatches normalization and supersedes old unapproved normalizations.
- Keep quotation-specific setup in local test helpers unless multiple test classes need the same factory behavior.

### Web: Quotation Review Feature Group

- Create: `apps/web/app/(workspace)/quotations/normalizations/page.tsx`
  - Route for the normalization review queue.
- Create: `apps/web/app/(workspace)/quotations/normalizations/[normalizationId]/page.tsx`
  - Route for one normalization review workspace.
- Create: `apps/web/features/quotations/api/quotation-normalization-api.ts`
  - Generated-client wrappers only.
- Create: `apps/web/features/quotations/hooks/use-quotation-normalization-queue.ts`
  - Queue list query hook.
- Create: `apps/web/features/quotations/hooks/use-quotation-normalization.ts`
  - Workspace query hook.
- Create: `apps/web/features/quotations/hooks/use-quotation-normalization-actions.ts`
  - Corrections, line mappings, approval, approve-with-warnings, revision, retry mutations.
- Create: `apps/web/features/quotations/components/quotation-normalization-status-badge.tsx`
  - Local status badge mapping; keep product meaning out of `packages/ui`.
- Create: `apps/web/features/quotations/components/quotation-normalization-issue-badge.tsx`
  - Local severity/status badge.
- Create: `apps/web/features/quotations/tables/quotation-normalization-queue-table.tsx`
  - Dense buyer/admin queue table.
- Create: `apps/web/features/quotations/components/quotation-normalization-field-review.tsx`
  - Header/commercial field source-vs-normalized review.
- Create: `apps/web/features/quotations/components/quotation-normalization-line-mapping-panel.tsx`
  - Line groups, mapping controls, pricing mode selector.
- Create: `apps/web/features/quotations/components/quotation-normalization-attachment-panel.tsx`
  - Attachment evidence metadata review.
- Create: `apps/web/features/quotations/components/quotation-normalization-issue-list.tsx`
  - Issue list with correction/resolution controls.
- Create: `apps/web/features/quotations/components/quotation-normalization-approval-panel.tsx`
  - Approval, approve-with-warnings, retry, and revision actions.
- Create: `apps/web/features/quotations/workflows/quotation-normalization-queue-page.tsx`
  - Queue page composition.
- Create: `apps/web/features/quotations/workflows/quotation-normalization-workspace.tsx`
  - `RecordWorkspaceLayout`-based review workspace.
- Create: `apps/web/features/quotations/mocks/quotation-normalization-fixtures.ts`
  - OpenAPI-shaped fixture state for MSW tests.
- Create: `apps/web/features/quotations/mocks/quotation-normalization-handlers.ts`
  - MSW handlers for lifecycle transitions.
- Create: `apps/web/features/quotations/tests/quotation-normalization-queue.test.tsx`
  - Queue state, permission, loading, and error tests.
- Create: `apps/web/features/quotations/tests/quotation-normalization-workspace.test.tsx`
  - Correction, line mapping, approval, approval-with-warnings, and retry tests.
- Modify: `apps/web/components/shell/shell-route-config.ts`
  - Mark `Quotations` route implemented and point it to `/quotations/normalizations` for users with `canReviewQuotationNormalization`.
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`
  - Permission/breadcrumb tests for the new route.
- Modify: `apps/web/features/identity/mocks/identity-fixtures.ts`
  - Include `canReviewQuotationNormalization` in mock permissions.
- Modify: `apps/web/features/sourcing/components/quotation-version-history.tsx`
  - Add a buyer/admin link from current version history to the active normalization workspace when payload data is available.

### Docs

- Modify: `docs/01-product/feature-roadmap.md`
  - During implementation closeout, set P1-29 design/plan links and mark fully implemented only after verification.
  - Add future row `Quotation Document Extraction` near P2 OCR/extraction items; keep P2-04/P2-05 broader AI/OCR items.
- Modify: `docs/superpowers/plans/2026-05-21-quotation-normalization.md`
  - Mark completed checkboxes during execution.

---

## Task 1: Backend Normalization Contract Tests First

**Files:**

- Create: `apps/api/tests/Feature/QuotationNormalizationApiTest.php`

- [x] **Step 1: Create failing endpoint tests**

Create `apps/api/tests/Feature/QuotationNormalizationApiTest.php` with these required tests. Reuse the helper style from `apps/api/tests/Feature/QuotationVersionApiTest.php`: `tenantUser()`, `draftRfq()`, `vendor()`, `invitation()`, `actingAsTenant()`, and `validManualEntryPayload()`.

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Jobs\NormalizeQuotationVersion;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationNormalizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_current_quotation_version_dispatches_normalization(): void
    {
        Bus::fake();

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        Bus::assertDispatched(NormalizeQuotationVersion::class, function (NormalizeQuotationVersion $job) use ($tenant, $version): bool {
            return $job->tenantId === $tenant->id && $job->quotationVersionId === $version->id;
        });
    }

    public function test_buyer_can_review_correct_map_and_approve_normalization(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $normalization = $this->normalizationNeedingReview($tenant, $requester, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/quotation-normalizations/{$normalization->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_review')
            ->assertJsonPath('data.permissions.canApprove', false)
            ->assertJsonPath('data.issues.0.severity', 'blocking');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotation-normalizations/{$normalization->id}/corrections", [
                'corrections' => [[
                    'issueId' => (string) $normalization->issues()->where('severity', 'blocking')->firstOrFail()->id,
                    'fieldPath' => 'manualEntry.currency',
                    'correctedValue' => 'USD',
                    'correctionNote' => 'Supplier quote is USD.',
                    'resolutionNote' => 'Currency confirmed against submitted quotation.',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_review');

        $versionLineId = (string) $normalization->quotationVersion->lineItems()->firstOrFail()->id;
        $rfqLineId = 'rfq-line-1';

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotation-normalizations/{$normalization->id}/line-mappings", [
                'lineGroups' => [[
                    'groupNumber' => 1,
                    'pricingMode' => 'bundle',
                    'description' => 'Laptop bundle',
                    'currency' => 'USD',
                    'bundleTotalAmount' => '12470.00',
                    'notes' => 'Vendor bundled laptops and freight.',
                    'mappings' => [[
                        'rfqLineItemId' => $rfqLineId,
                        'quotationVersionLineItemId' => $versionLineId,
                        'mappingType' => 'bundled',
                        'quantity' => '10',
                        'unit' => 'each',
                        'unitPrice' => null,
                        'lineTotal' => null,
                        'buyerNote' => 'Covered by bundle total.',
                    ]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_approval');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotation-normalizations/{$normalization->id}/approve", [
                'approvalNote' => 'Normalization reviewed for comparison.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.permissions.canEdit', false);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation_normalization.approved',
        ]);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $buyer->id,
            'type' => 'quotation_normalization.approved',
        ]);
    }

    public function test_requester_approver_vendor_and_cross_tenant_users_cannot_access_normalization(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);
        $normalization = $this->normalizationNeedingReview($tenant, $requester, $buyer);
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/quotation-normalizations')
            ->assertForbidden();

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/quotation-normalizations/{$normalization->id}")
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/quotation-normalizations/{$normalization->id}")
            ->assertNotFound();
    }
}
```

The helper methods may be copied from `QuotationVersionApiTest.php`, but update them to seed at least one RFQ line item in `rfqs.line_items` and one quotation version line item so line mapping tests have real IDs.

- [x] **Step 2: Run the focused test to verify it fails**

Run:

```bash
php artisan test --filter=QuotationNormalizationApiTest
```

Expected: fail because `NormalizeQuotationVersion`, `QuotationNormalization`, and the routes do not exist.

- [x] **Step 3: Commit test scaffold after it fails**

```bash
git add apps/api/tests/Feature/QuotationNormalizationApiTest.php
git commit -m "test: cover quotation normalization workflow"
```

---

## Task 2: Normalization Schema, Models, And Enums

**Files:**

- Create: migration/model/state files listed in the API state/model file map.
- Modify: `apps/api/Domains/Quotation/Models/QuotationVersion.php`

- [x] **Step 1: Add lifecycle enums**

Create the enum files with exact values:

```php
<?php

namespace Domains\Quotation\States;

enum QuotationNormalizationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case ReadyForApproval = 'ready_for_approval';
    case Approved = 'approved';
    case ApprovedWithWarnings = 'approved_with_warnings';
    case Failed = 'failed';
    case Superseded = 'superseded';
}
```

Create matching string-backed enums for issue severity (`blocking`, `warning`, `info`), issue status (`open`, `resolved`, `acknowledged`), pricing mode (`per_line`, `bundle`, `included`, `unknown`), and mapping type (`full`, `partial`, `bundled`).

- [x] **Step 2: Add migrations**

Use `foreignIdFor(Tenant::class)` and `foreignIdFor(...)` consistently with existing quotation migrations. Required columns:

```php
// quotation_normalizations
$table->id();
$table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
$table->foreignIdFor(Quotation::class)->constrained()->cascadeOnDelete();
$table->foreignIdFor(QuotationVersion::class)->constrained()->cascadeOnDelete();
$table->unsignedInteger('normalization_revision');
$table->string('status', 32);
$table->boolean('is_current_for_version')->default(true);
$table->timestamp('superseded_at')->nullable();
$table->timestamp('normalized_at')->nullable();
$table->timestamp('approved_at')->nullable();
$table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
$table->text('approval_note')->nullable();
$table->string('algorithm_version', 32)->default('deterministic-v1');
$table->unsignedInteger('job_attempt_count')->default(0);
$table->text('last_job_error')->nullable();
$table->json('metadata')->nullable();
$table->timestamps();
$table->unique(['tenant_id', 'quotation_version_id', 'normalization_revision'], 'quotation_normalization_revision_unique');
$table->index(['tenant_id', 'status', 'updated_at'], 'quotation_normalization_queue_index');
```

For JSON-like fields, use `json` columns for `raw_value`, `normalized_value`, `provenance`, `suggested_value`, and correction values so strings, numbers, nulls, and arrays remain distinguishable.

- [x] **Step 3: Add model relationships and casts**

`QuotationNormalization` must cast `status`, timestamps, and `metadata`, and expose:

```php
public function quotation(): BelongsTo;
public function quotationVersion(): BelongsTo;
public function fields(): HasMany;
public function lineGroups(): HasMany;
public function attachments(): HasMany;
public function issues(): HasMany;
public function corrections(): HasMany;
public function approvedBy(): BelongsTo;
public function isMutable(): bool;
```

`isMutable()` returns true only for `pending`, `processing`, `needs_review`, `ready_for_approval`, and `failed`.

- [x] **Step 4: Wire quotation version relationships**

Add to `QuotationVersion`:

```php
public function normalizations(): HasMany
{
    return $this->hasMany(QuotationNormalization::class)
        ->orderByDesc('normalization_revision');
}

public function currentNormalization(): HasOne
{
    return $this->hasOne(QuotationNormalization::class)
        ->where('is_current_for_version', true)
        ->latestOfMany('normalization_revision');
}
```

- [x] **Step 5: Run migration syntax checks**

Run:

```bash
php -l apps/api/Domains/Quotation/States/QuotationNormalizationStatus.php
php -l apps/api/Domains/Quotation/Models/QuotationNormalization.php
php artisan test --filter=QuotationNormalizationApiTest
```

Expected: PHP lint passes; feature tests still fail on missing actions/routes.

- [x] **Step 6: Commit schema work**

```bash
git add apps/api/database/migrations apps/api/Domains/Quotation/Models apps/api/Domains/Quotation/States
git commit -m "feat: add quotation normalization data model"
```

---

## Task 3: Deterministic Normalizer And Queue Job

**Files:**

- Create: `apps/api/Domains/Quotation/Jobs/NormalizeQuotationVersion.php`
- Create: `apps/api/Domains/Quotation/Actions/StartQuotationNormalization.php`
- Create: `apps/api/Domains/Quotation/Actions/RunDeterministicQuotationNormalizer.php`
- Create: `apps/api/Domains/Quotation/Support/QuotationNormalizationIssueCatalog.php`
- Create: `apps/api/Domains/Quotation/Support/QuotationNormalizationProvenance.php`
- Create: `apps/api/tests/Unit/QuotationDeterministicNormalizerTest.php`
- Modify: `apps/api/Domains/Quotation/Actions/CreateQuotationVersionSnapshot.php`

- [x] **Step 1: Write focused normalizer tests**

Create unit tests covering:

```php
public function test_normalizer_uppercases_currency_and_records_amount_fields(): void;
public function test_missing_currency_and_total_are_blocking_issues(): void;
public function test_unstructured_payment_terms_are_warning_issues(): void;
public function test_attachment_snapshots_become_evidence_metadata(): void;
public function test_total_mismatch_records_blocking_issue(): void;
```

Use real `QuotationVersion` and `QuotationVersionLineItem` records with `RefreshDatabase`; avoid mocking Eloquent.

- [x] **Step 2: Implement issue/provenance helpers**

`QuotationNormalizationIssueCatalog` must expose constants such as:

```php
public const MISSING_CURRENCY = 'missing_currency';
public const INVALID_CURRENCY = 'invalid_currency';
public const MISSING_TOTAL_AMOUNT = 'missing_total_amount';
public const MISSING_COMPARABLE_LINE_ITEMS = 'missing_comparable_line_items';
public const REQUIRED_RFQ_LINE_UNMAPPED = 'required_rfq_line_unmapped';
public const TOTAL_RECONCILIATION_MISMATCH = 'total_reconciliation_mismatch';
public const PAYMENT_TERMS_UNSTRUCTURED = 'payment_terms_unstructured';
public const WARRANTY_TERMS_MISSING = 'warranty_terms_missing';
public const ATTACHMENT_CHECKSUM_UNAVAILABLE = 'attachment_checksum_unavailable';
```

- [x] **Step 3: Implement `StartQuotationNormalization`**

Behavior:

- Lock quotation and version by tenant.
- Mark old unapproved normalizations for older versions of the same quotation as `superseded`.
- Create next normalization revision for the target version with `pending`, then `processing`.
- Record `quotation_normalization.started`.
- Return the locked normalization for normalizer use.

- [x] **Step 4: Implement deterministic normalization**

`RunDeterministicQuotationNormalizer::handle(Tenant $tenant, QuotationVersion $version, QuotationNormalization $normalization): QuotationNormalization` must:

- Delete/rebuild draft `fields`, `attachments`, and open generated issues for the target normalization.
- Preserve buyer-created corrections unless called after a new revision copy.
- Normalize currency to uppercase 3-letter code.
- Normalize decimal strings for subtotal, tax, freight, discount, total, unit price, and line totals.
- Normalize lead time to integer days.
- Preserve payment, delivery, warranty, exclusions, and compliance notes as text.
- Create attachment metadata rows from `attachment_snapshots`.
- Create initial line groups from version line items when possible; otherwise record blocking line issues.
- Record blocking/warning/info issues exactly as the design lists.
- Set status to `ready_for_approval` when no blocking issues remain; otherwise `needs_review`.
- Record `quotation_normalization.completed` or `quotation_normalization.issue_recorded`.

- [x] **Step 5: Implement idempotent job**

`NormalizeQuotationVersion` constructor should expose public readonly IDs so tests can assert dispatch:

```php
public function __construct(
    public readonly int $tenantId,
    public readonly int $quotationVersionId,
) {}
```

`handle()` loads tenant/version by tenant, calls start + run actions, increments `job_attempt_count`, stores `last_job_error` on failure, sets `failed`, audits `quotation_normalization.failed`, and notifies buyer/admin users.

- [x] **Step 6: Dispatch after version creation commits**

In `CreateQuotationVersionSnapshot`, after the transaction commits, dispatch:

```php
NormalizeQuotationVersion::dispatch($tenant->id, $version->id)->afterCommit();
```

If the current action returns from inside `DB::transaction`, capture the created version and dispatch after the transaction result is available so the job cannot run before commit.

- [x] **Step 7: Run tests**

```bash
php artisan test --filter=QuotationDeterministicNormalizerTest
php artisan test --filter=QuotationNormalizationApiTest
php artisan test --filter=QuotationVersionApiTest
```

Expected: normalizer unit tests pass; API tests still fail where endpoints/actions are missing.

- [x] **Step 8: Commit normalizer and job**

```bash
git add apps/api/Domains/Quotation apps/api/tests/Unit/QuotationDeterministicNormalizerTest.php apps/api/tests/Feature/QuotationVersionApiTest.php
git commit -m "feat: normalize quotation versions asynchronously"
```

---

## Task 4: Review Actions, Policy, API Resources, And Routes

**Files:**

- Create: action/request/resource/controller/policy files listed in the API controller file map.
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/app/Auth/Permissions/TenantPermissionResolver.php`
- Modify: `apps/api/storage/openapi/openapi.json`

- [x] **Step 1: Add permission flag tests**

Extend `apps/api/tests/Feature/QuotationNormalizationApiTest.php`:

```php
public function test_current_user_permissions_include_normalization_review_for_buyer_and_admin_only(): void
{
    [$tenant, $buyer] = $this->tenantUser('buyer');
    [, $admin] = $this->tenantUser('admin', $tenant);
    [, $requester] = $this->tenantUser('requester', $tenant);

    $this->actingAsTenant($tenant, $buyer)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('permissions.canReviewQuotationNormalization', true);

    $this->actingAsTenant($tenant, $admin)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('permissions.canReviewQuotationNormalization', true);

    $this->actingAsTenant($tenant, $requester)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('permissions.canReviewQuotationNormalization', false);
}
```

- [x] **Step 2: Update permission resolver**

Add `canReviewQuotationNormalization` to every role branch in `TenantPermissionResolver.php`.

- [x] **Step 3: Implement policy**

`QuotationNormalizationPolicy` must:

- Return true for buyer/admin tenant members.
- Return false for requester/approver.
- Verify `normalization.tenant_id === CurrentTenant.id`.
- Reject mutations when `isMutable()` is false, except creating a new revision from approved records.
- Reject approval when unresolved blocking issues exist.

- [x] **Step 4: Implement requests**

Validation highlights:

- `ListQuotationNormalizationsRequest`: `status` optional array of allowed statuses, default active queue statuses `needs_review`, `failed`, `ready_for_approval`.
- `SaveQuotationNormalizationCorrectionsRequest`: `corrections` required array, each item has `fieldPath`, `correctedValue`, optional `issueId`, required `correctionNote`, optional `resolutionNote`.
- `SaveQuotationNormalizationLineMappingsRequest`: `lineGroups` required array; each group requires `groupNumber`, `pricingMode`, `description`, optional `currency`, optional `bundleTotalAmount`, and non-empty `mappings` array.
- `ApproveQuotationNormalizationRequest`: `approvalNote` nullable for normal approval, required for approve-with-warnings.

- [x] **Step 5: Implement resources**

`QuotationNormalizationResource` must include:

```json
{
  "id": "1",
  "status": "needs_review",
  "normalizationRevision": 1,
  "algorithmVersion": "deterministic-v1",
  "source": {
    "quotationId": "1",
    "quotationVersionId": "1",
    "quotationNumber": "QUO-...",
    "versionNumber": 1,
    "rfqId": "1",
    "rfqNumber": "RFQ-...",
    "vendorId": "1",
    "vendorName": "Northwind Traders"
  },
  "summary": {
    "blockingIssueCount": 1,
    "warningIssueCount": 2,
    "infoIssueCount": 1
  },
  "fields": [],
  "lineGroups": [],
  "attachments": [],
  "issues": [],
  "permissions": {
    "canEdit": true,
    "canApprove": false,
    "canApproveWithWarnings": false,
    "canRetry": false,
    "canCreateRevision": false
  }
}
```

Keep response keys camelCase to match existing generated client conventions.

- [x] **Step 6: Add routes**

Inside the protected tenant route group in `apps/api/routes/api.php`:

```php
Route::get('/quotation-normalizations', [QuotationNormalizationController::class, 'index']);
Route::get('/quotation-normalizations/{normalization}', [QuotationNormalizationController::class, 'show']);
Route::post('/quotation-normalizations/{normalization}/corrections', [QuotationNormalizationController::class, 'corrections']);
Route::post('/quotation-normalizations/{normalization}/line-mappings', [QuotationNormalizationController::class, 'lineMappings']);
Route::post('/quotation-normalizations/{normalization}/approve', [QuotationNormalizationController::class, 'approve']);
Route::post('/quotation-normalizations/{normalization}/approve-with-warnings', [QuotationNormalizationController::class, 'approveWithWarnings']);
Route::post('/quotation-normalizations/{normalization}/revisions', [QuotationNormalizationController::class, 'revision']);
Route::post('/quotation-versions/{version}/normalization/retry', [QuotationVersionNormalizationController::class, 'retry']);
```

Do not add vendor portal normalization routes.

- [x] **Step 7: Update OpenAPI manually**

Add schemas and paths to `apps/api/storage/openapi/openapi.json` before generating the client. Required generated schema names:

- `QuotationNormalizationSummary`
- `QuotationNormalization`
- `QuotationNormalizationField`
- `QuotationNormalizationLineGroup`
- `QuotationNormalizationLineMapping`
- `QuotationNormalizationAttachment`
- `QuotationNormalizationIssue`
- `SaveQuotationNormalizationCorrectionsRequest`
- `SaveQuotationNormalizationLineMappingsRequest`
- `ApproveQuotationNormalizationRequest`

- [x] **Step 8: Run API tests and route check**

```bash
php artisan route:list --path=api/quotation-normalizations
php artisan route:list --path=api/quotation-versions
php artisan test --filter=QuotationNormalizationApiTest
php artisan test --filter=QuotationVersionApiTest
```

Expected: route list includes the new endpoints; feature tests pass.

- [x] **Step 9: Commit API review surface**

```bash
git add apps/api/storage/openapi/openapi.json
git add apps/api/Domains/Quotation apps/api/app/Auth/Permissions/TenantPermissionResolver.php apps/api/routes/api.php apps/api/tests/Feature/QuotationNormalizationApiTest.php
git commit -m "feat: expose quotation normalization review API"
```

---

## Task 5: Generated Client And API Wrappers

**Files:**

- Modify generated: `packages/api-client/src/generated/**`
- Create: `apps/web/features/quotations/api/quotation-normalization-api.ts`
- Create hooks listed in the web file map.
- Modify: `apps/web/features/identity/mocks/identity-fixtures.ts`

- [x] **Step 1: Regenerate and check contract**

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated endpoints and schemas include the normalization routes and `IdentityPermissions.canReviewQuotationNormalization`.

- [x] **Step 2: Create generated-client wrappers**

`apps/web/features/quotations/api/quotation-normalization-api.ts` must import only generated endpoints/schemas:

```ts
import {
  approveQuotationNormalization as approveEndpoint,
  approveQuotationNormalizationWithWarnings as approveWithWarningsEndpoint,
  createQuotationNormalizationRevision as createRevisionEndpoint,
  getQuotationNormalization as getEndpoint,
  listQuotationNormalizations as listEndpoint,
  retryQuotationVersionNormalization as retryEndpoint,
  saveQuotationNormalizationCorrections as saveCorrectionsEndpoint,
  saveQuotationNormalizationLineMappings as saveLineMappingsEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  ApproveQuotationNormalizationRequest,
  QuotationNormalization,
  QuotationNormalizationSummary,
  SaveQuotationNormalizationCorrectionsRequest,
  SaveQuotationNormalizationLineMappingsRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
  if (!tenantId) return undefined;
  return { headers: { "X-Tenant-Id": tenantId } };
}
```

Add exported functions for list/show/corrections/line-mappings/approve/approve-with-warnings/revision/retry. Throw `response.data` on non-success, following `apps/web/features/sourcing/api/quotation-api.ts`.

- [x] **Step 3: Create TanStack Query hooks**

Use query keys:

```ts
export const quotationNormalizationKeys = {
  all: ["quotation-normalizations"] as const,
  list: (filters: Record<string, unknown>) => [...quotationNormalizationKeys.all, "list", filters] as const,
  detail: (normalizationId: string) => [...quotationNormalizationKeys.all, "detail", normalizationId] as const,
};
```

After each mutation, invalidate `all` and the detail key. Do not store duplicated API response types in app code.

- [x] **Step 4: Update identity fixtures**

Add `canReviewQuotationNormalization` to all mock permission objects:

```ts
permissions: {
  canCreateRequisition: true,
  canViewSubmittedRequisitions: false,
  canUpdateOwnDraftRequisition: true,
  canSubmitOwnDraftRequisition: true,
  canAccessAdmin: false,
  canManageSourcingIntake: false,
  canReviewQuotationNormalization: false,
}
```

- [x] **Step 5: Run typecheck for generated contract consumers**

```bash
pnpm --filter @cognify/web typecheck
pnpm check:api-contract
```

Expected: typecheck fails only if endpoint names differ from generated names. If names differ, adjust wrapper imports to the generated names; do not hand-write schemas.

- [x] **Step 6: Commit contract and wrappers**

```bash
git add packages/api-client apps/web/features/quotations/api apps/web/features/quotations/hooks apps/web/features/identity/mocks
git commit -m "feat: add quotation normalization client hooks"
```

---

## Task 6: Buyer/Admin Normalization Queue And Workspace UI

**Files:**

- Create all `apps/web/features/quotations/components`, `tables`, `workflows`, `mocks`, `tests`, and App Router files listed in the web file map.
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`
- Modify: `apps/web/features/sourcing/components/quotation-version-history.tsx`

- [ ] **Step 1: Create MSW fixtures and handlers**

Fixtures must be OpenAPI-shaped and include:

- One `needs_review` normalization with blocking currency and line mapping issues.
- One `ready_for_approval` normalization with warning issues.
- One `failed` normalization with `lastJobError`.
- One `approved_with_warnings` normalization for read-only rendering.

Handlers must support list, show, corrections, line mappings, approve, approve-with-warnings, revision, and retry state transitions.

- [ ] **Step 2: Write queue tests first**

`quotation-normalization-queue.test.tsx` must assert:

- Loading state renders.
- Queue rows show status, vendor, RFQ, version number, blocking/warning counts, and updated time.
- Clicking a row links to `/quotations/normalizations/{normalizationId}`.
- Failed rows expose a retry command when permissions allow.
- Permission denial shows an error state instead of rendering queue actions.

- [ ] **Step 3: Write workspace tests first**

`quotation-normalization-workspace.test.tsx` must assert:

- Source and normalized values render side by side.
- Blocking/warning/info issue badges render.
- Buyer can submit a correction for `manualEntry.currency`.
- Buyer can map one quotation version line to one RFQ line as a bundle.
- Approval button remains disabled while blocking issues exist.
- Approval succeeds after blocking issues are resolved.
- Approve-with-warnings requires an acknowledgement note.
- Approved records are read-only.

- [ ] **Step 4: Build local status/issue badges**

Use `packages/ui` primitives only for neutral UI; keep Cognify labels in feature code. Suggested status labels:

```ts
const STATUS_LABELS = {
  pending: "Pending",
  processing: "Processing",
  needs_review: "Needs review",
  ready_for_approval: "Ready for approval",
  approved: "Approved",
  approved_with_warnings: "Approved with warnings",
  failed: "Failed",
  superseded: "Superseded",
} as const;
```

- [ ] **Step 5: Build queue workflow**

Use a dense table/list suitable for operational buyer work. Include empty, loading, error, and populated states. Do not make a marketing-style page or explanatory landing page.

- [ ] **Step 6: Build workspace workflow**

Use `RecordWorkspaceLayout`. Sections:

- Overview/source version summary.
- Header fields.
- Line mappings.
- Attachments.
- Issues.
- Approval.

Use shadcn/Radix controls already available through the local UI layer for dialogs/popovers/selects/tabs. Do not create reusable business-specific primitives in `packages/ui`.

- [ ] **Step 7: Wire shell route and breadcrumbs**

Set `Quotations` to:

```ts
{
  label: "Quotations",
  href: "/quotations/normalizations",
  icon: ReceiptText,
  implemented: true,
  permission: (permissions) => permissions.canReviewQuotationNormalization,
}
```

Add breadcrumbs for `/quotations/normalizations` and `/quotations/normalizations/{id}`.

- [ ] **Step 8: Link from quotation version history**

Where buyer RFQ version history has a current version with active normalization metadata, link to the review workspace. If the existing version API does not expose normalization summary yet, add a small backend/resource field in Task 4 before implementing this link.

- [ ] **Step 9: Run targeted web tests**

```bash
pnpm --filter @cognify/web test -- quotation-normalization
pnpm --filter @cognify/web test -- shell-route-config
pnpm --filter @cognify/web test -- rfq-invitations
pnpm --filter @cognify/web typecheck
```

Expected: all targeted tests pass.

- [ ] **Step 10: Commit UI work**

```bash
git add apps/web/app apps/web/features/quotations apps/web/components/shell apps/web/features/sourcing/components/quotation-version-history.tsx
git commit -m "feat: add quotation normalization review workspace"
```

---

## Task 7: Vendor Portal Redaction And Regression Coverage

**Files:**

- Review/modify: `apps/api/Domains/Quotation/Http/Resources/VendorPortalRfqInvitationResource.php`
- Review/modify: `apps/api/Domains/Quotation/Http/Resources/QuotationVersionResource.php`
- Modify: `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`
- Modify: `apps/api/tests/Feature/QuotationNormalizationApiTest.php`

- [ ] **Step 1: Add backend redaction assertions**

Extend API tests so vendor portal quotation and version endpoints do not include:

- `normalization`
- `normalizationStatus`
- `normalizationIssues`
- `normalizedFields`
- `corrections`
- `approvalStatus`

Use `assertJsonMissingPath()` for each path that might be accidentally added.

- [ ] **Step 2: Add web vendor portal regression test**

In `vendor-rfq-portal.test.tsx`, assert that rendered portal pages do not show normalization status, buyer correction text, approval status, or issue labels.

- [ ] **Step 3: Run vendor portal tests**

```bash
php artisan test --filter=QuotationNormalizationApiTest
pnpm --filter @cognify/web test -- vendor-rfq-portal
```

Expected: pass without changing vendor-facing UX.

- [ ] **Step 4: Commit redaction coverage**

```bash
git add apps/api/tests/Feature/QuotationNormalizationApiTest.php apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx
git commit -m "test: keep normalization internal to buyers"
```

---

## Task 8: Roadmap Loopback And Final Verification

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify: `docs/superpowers/plans/2026-05-21-quotation-normalization.md`

- [ ] **Step 1: Update roadmap after implementation passes**

Set P1-29:

- Feature Status: `Fully Implemented`
- Design Spec: `2026-05-21-quotation-normalization-design.md`
- Implementation Plan: `2026-05-21-quotation-normalization.md`
- Notes: mention structured normalization, buyer/admin review workspace, immutable approved revisions, audit/notifications, and no OCR/document extraction.

Add a future roadmap row near the P2 extraction items:

```markdown
| P2-36 | Quotation Document Extraction | Parse PDF, XLS, XLSX, CSV, DOC, and DOCX quotation evidence into structured candidate fields with human review and conflict handling before normalization. | Not Implemented |  |  |  | Quotation-specific extraction follow-up; P1-29 normalizes existing structured fields and attachment metadata only. |
```
Do not rename P2-04 or P2-05.

- [ ] **Step 2: Run focused backend verification**

```bash
php artisan test --filter=QuotationNormalizationApiTest
php artisan test --filter=QuotationDeterministicNormalizerTest
php artisan test --filter=QuotationVersionApiTest
php artisan route:list --path=api/quotation-normalizations
php artisan route:list --path=api/quotation-versions
```

Expected: all pass and route lists include expected endpoints.

- [ ] **Step 3: Run focused frontend verification**

```bash
pnpm --filter @cognify/web test -- quotation-normalization
pnpm --filter @cognify/web test -- rfq-invitations
pnpm --filter @cognify/web test -- vendor-rfq-portal
pnpm --filter @cognify/web test -- shell-route-config
pnpm --filter @cognify/web typecheck
```

Expected: all pass.

- [ ] **Step 4: Run contract and root verification**

```bash
pnpm check:api-contract
pnpm lint
pnpm typecheck
pnpm test
pnpm build
git diff --check
```

Expected: all pass. `pnpm build` is required before claiming completion.

- [ ] **Step 5: Commit docs and final verification state**

```bash
git add docs/01-product/feature-roadmap.md docs/superpowers/plans/2026-05-21-quotation-normalization.md
git commit -m "docs: close quotation normalization roadmap loop"
```

---

## Self-Review Checklist

- Spec coverage: queued normalization, deterministic fields, provenance, issues, buyer/admin correction, line mapping, bundles, attachment metadata, approval snapshots, superseding, audit, notifications, permissions, vendor redaction, roadmap loopback, and verification are all covered.
- Explicit non-goals: document parsing, OCR, AI extraction, reusable rules, alternate-offer workflow state, comparison, scoring, award, vendor-facing normalization, and configurable RBAC are excluded.
- Type consistency: status, severity, pricing mode, and mapping type names match the design spec and should be exposed as generated OpenAPI schemas.
- Contract consistency: frontend code must import from `@cognify/api-client`; no handwritten duplicate response types.
- Verification: final verification includes focused API/web checks, contract checks, root lint/typecheck/test, `pnpm build`, and `git diff --check`.
