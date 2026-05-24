# Vendor Scoring Matrix Design

## Status

- Status: Draft for review
- Date: 2026-05-24
- Release scope: P1 Epic 7, slice 3 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-31`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 7, slice 3
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-manual-entry-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-versioning-design.md`
  - `docs/superpowers/specs/2026-05-21-quotation-normalization-design.md`
  - `docs/superpowers/specs/2026-05-22-quotation-comparison-table-design.md`

## Purpose

This slice adds a scoring layer on top of the RFQ quotation comparison workspace. Buyers can apply a tenant-scoped scoring template to one RFQ, score each vendor response against frozen criteria, and preserve score rationale for the later award recommendation workflow.

Scoring explains evaluation. It does not recommend a vendor, award a vendor, exclude a vendor, shortlist a vendor, approve an award, change RFQ status, change quotation status, or mutate normalization records.

## Problem

P1-30 gives buyers a stable comparison workspace, but evaluation still depends on informal notes or external spreadsheets when teams need to explain why one vendor response is stronger than another. Procurement teams commonly compare more than price: delivery, compliance, quality, sustainability, risk, and supplier history all matter.

Cognify needs a reusable scoring matrix that remains auditable and stable for each RFQ. Template edits after an RFQ is scored must not rewrite historical evaluations, and scoring must stay clearly separate from recommendation and award decisions.

## Goals

- Add lightweight admin-managed scoring templates.
- Let buyers apply one active template to one RFQ.
- Freeze template criteria into an RFQ scorecard snapshot when applied.
- Score invited/responding vendors against the frozen scorecard criteria.
- Store criterion-level scores and notes.
- Calculate raw totals and weighted totals consistently.
- Show missing required scores and incomplete scorecards.
- Let buyers/admins mark scoring complete and reopen it for corrections without changing RFQ or award state.
- Surface comparison context while scoring without duplicating comparison as a second source of truth.
- Keep scoring tenant-scoped, policy-guarded, auditable, and OpenAPI-backed.
- Keep award recommendation, award approval, shortlist, exclusion, and PO handoff out of this slice.

## Non-Goals

- Template approval workflow, template version browser, cloning workflow, archive workflow, or tenant-wide governance lifecycle.
- Configurable RBAC or role administration.
- Automatic vendor ranking beyond displaying score totals.
- Award recommendation, award decision, award approval, or PO handoff.
- Shortlist, exclusion, winner selection, negotiation round, or RFQ status transition.
- Mutating quotation comparison notes, normalization records, quotation versions, RFQ invitations, vendor records, or RFQ state from scoring actions.
- AI scoring, automated risk scoring, external risk integrations, OCR, document extraction, currency conversion, or score suggestions.
- Vendor-facing score visibility.
- Spreadsheet export.

## Design Decisions

### Put Scoring In The Quotation Domain

Backend ownership stays in `apps/api/Domains/Quotation` because the scorecard evaluates quotation responses for an RFQ. Scoring depends on RFQ, vendor, quotation, current quotation version, approved normalization, and comparison readiness. It is not a generic vendor-management scorecard and should not live in the vendor domain.

### Use Lightweight Reusable Templates

Admins can create, edit, deactivate, and list scoring templates. A template is reusable configuration, not an approved governance artifact. P1-31 should keep the template model deliberately simple:

- name;
- optional description;
- active/inactive status;
- criteria with category, label, guidance, weight, max score, display order, and required flag.

Editing a template affects future RFQ scorecards only. Existing RFQ scorecards keep their frozen criteria snapshot.

### Snapshot Criteria Onto The RFQ Scorecard

When a buyer applies an active template to an RFQ, Cognify creates one RFQ scorecard snapshot. The snapshot stores the template metadata and every criterion exactly as it existed at apply time.

This prevents later template edits from changing the meaning of historical RFQ scores. It also gives P1-32 recommendation and award workflows a stable scoring artifact to reference.

### One Active Scorecard Per RFQ In This Slice

An RFQ can have at most one active scorecard in P1-31. If a buyer applies a template after a scorecard already exists, the API should reject the action and tell the user that the RFQ already has a scorecard.

Replacing scorecards, comparing scorecard versions, merging templates, or rescoring from a new template belongs in a later governance slice if the product needs it.

### Score Current Evaluation Participants

The scorecard should include vendors that belong to the RFQ evaluation set. At minimum, that includes invited vendors with a quotation response. Vendors without a response may appear as not scoreable if the comparison workspace already surfaces them, but the scoring entry flow should not require scores for missing responses.

Score values should be entered only for scoreable vendor responses tied to the current RFQ and tenant.

### Consume Comparison Readiness, Do Not Rebuild Comparison

The scoring workspace should link from `/quotations/comparisons/[rfqId]` and live at:

```txt
/quotations/scoring/[rfqId]
```

The scoring page can show supporting comparison context such as totals, delivery, terms, compliance notes, normalization readiness, and risk placeholder. It should consume comparison-shaped readiness and links, but the persisted scoring data remains the scorecard, criteria snapshots, score entries, and notes.

If a vendor lacks an approved normalization, the scorecard should mark comparison context as incomplete. Buyers can still enter scores for scoreable vendor responses, but the UI must make missing approved comparison data visible before and during scoring.

### Keep Scoring Separate From Award Decisions

Score totals are explanatory evidence. They do not automatically produce a recommendation or decision. P1-32 can later consume the scorecard and require award rationale, but P1-31 should not emit award state.

### Use Deterministic Score Math

Each criterion has:

- `maxScore`: positive integer, suggested range 1-100;
- `weight`: positive decimal or integer weight;
- `required`: boolean.

Each score entry stores a numeric `score` between 0 and `maxScore`. Weighted contribution is:

```txt
(score / maxScore) * weight
```

Vendor weighted total is the sum of weighted contributions for criteria with submitted scores. Scorecard maximum weighted total is the sum of all criterion weights. Completion indicators should show how many required criteria are missing per vendor and overall.

Do not normalize scores across missing required criteria in P1-31. Missing scores should be shown as incomplete, not silently treated as zero unless the buyer explicitly enters `0`.

## Workflow

### Actors

- Admin: creates, edits, deactivates, and lists scoring templates.
- Buyer: applies an active template to an RFQ, views the scorecard, scores vendors, and adds criterion-level notes.
- Requester/approver: no scoring access in P1-31.
- Vendor portal visitor: no scoring access.
- System: freezes template snapshots, calculates totals, validates tenant and RFQ ownership, and records audit events.

### Admin Template Flow

1. Admin opens the scoring template management surface.
2. Admin creates a template with one or more criteria.
3. API validates criterion weights, max scores, required flags, and display order.
4. API persists the active template and criteria.
5. API records `quotation_scoring_template.created`.
6. Admin can edit the template for future RFQs.
7. Admin can deactivate the template so it can no longer be applied to new RFQs.

No approval, publishing, cloning, archive, or version browser exists in this slice.

### Apply Template Flow

1. Buyer opens the RFQ comparison workspace.
2. Buyer chooses a scoring action.
3. If no scorecard exists, buyer selects one active template.
4. API verifies session, tenant, RFQ ownership, buyer/admin role, template ownership, and comparison readiness.
5. API creates an RFQ scorecard snapshot with frozen criteria.
6. API records `quotation_scorecard.created`.
7. Web routes buyer to `/quotations/scoring/[rfqId]`.

### Score Entry Flow

1. Buyer opens `/quotations/scoring/[rfqId]`.
2. Web hook loads scorecard, criteria, vendors, existing score entries, completion summary, and comparison context.
3. Buyer enters or updates scores and notes per vendor and criterion.
4. API validates score range, criterion membership, vendor/RFQ membership, and tenant ownership.
5. API upserts score entries.
6. API records `quotation_scorecard.score_updated`.
7. Web invalidates the scoring query and recalculates visible totals from API response.

Score entry changes do not update RFQ, quotation, normalization, comparison note, invitation, award, or vendor status.

### Completion Flow

P1-31 includes explicit scorecard completion so later recommendation work can distinguish in-progress scoring from buyer-finished scoring.

Scorecard statuses:

- `in_progress`: scorecard exists and can be edited.
- `completed`: buyer/admin marked scoring complete.

Completion rules:

- A scorecard can be completed only when every required criterion has a score for every scoreable vendor.
- Completing a scorecard sets `completed_by_user_id` and `completed_at`.
- Completed scorecards are read-only until reopened.
- Reopening sets status back to `in_progress` and clears `completed_by_user_id` and `completed_at`; the audit trail preserves who reopened it and when.
- Completing and reopening affect only the scorecard. They do not transition RFQ, quotation, normalization, comparison, invitation, award, or vendor state.
- API responses should still include derived completion metrics such as missing required score counts and total scoreable vendors.

## Data Model

Backend ownership remains in `apps/api/Domains/Quotation`.

### `quotation_scoring_templates`

Stores tenant-scoped reusable template headers.

- `id`
- `tenant_id`
- `name`
- `description`, nullable
- `is_active`
- `created_by_user_id`
- `updated_by_user_id`, nullable
- `deactivated_by_user_id`, nullable
- `deactivated_at`, nullable
- timestamps

Validation rules:

- `name` is required, trimmed, and tenant-unique among active templates.
- `description` is optional and capped.
- Inactive templates cannot be applied to new RFQs.

### `quotation_scoring_template_criteria`

Stores reusable criteria for templates.

- `id`
- `tenant_id`
- `template_id`
- `category`
- `label`
- `guidance`, nullable
- `weight`
- `max_score`
- `is_required`
- `display_order`
- timestamps

Suggested categories:

- `cost`
- `delivery`
- `quality`
- `compliance`
- `risk`
- `sustainability`
- `past_performance`
- `other`

Validation rules:

- A template must have at least one criterion.
- `weight` must be greater than 0.
- `max_score` must be a positive integer.
- `label` is required and capped.
- `guidance` is optional and capped.
- Display order must be stable.

### `rfq_scorecards`

Stores one applied scorecard snapshot per RFQ.

- `id`
- `tenant_id`
- `rfq_id`
- `template_id`, nullable if the template is later deleted; keep historical identity fields.
- `template_name`
- `template_description`, nullable
- `status`
- `applied_by_user_id`
- `applied_at`
- `completed_by_user_id`, nullable
- `completed_at`, nullable
- timestamps

Rules:

- One active scorecard per tenant/RFQ in P1-31.
- The scorecard belongs to the same tenant as the RFQ.
- `template_name` and `template_description` are frozen at apply time.

### `rfq_scorecard_criteria`

Stores frozen criteria copied from the template at apply time.

- `id`
- `tenant_id`
- `scorecard_id`
- `source_template_criterion_id`, nullable
- `category`
- `label`
- `guidance`, nullable
- `weight`
- `max_score`
- `is_required`
- `display_order`
- timestamps

Rules:

- Criteria are immutable after snapshot creation in P1-31.
- Edits to `quotation_scoring_template_criteria` do not update existing `rfq_scorecard_criteria`.

### `rfq_scorecard_entries`

Stores scores and notes per vendor and criterion.

- `id`
- `tenant_id`
- `scorecard_id`
- `scorecard_criterion_id`
- `vendor_id`
- `quotation_id`, nullable
- `quotation_version_id`, nullable
- `score`, nullable until entered
- `note`, nullable
- `scored_by_user_id`, nullable
- `scored_at`, nullable
- timestamps

Validation rules:

- `scorecard_criterion_id` must belong to the scorecard.
- `vendor_id` must belong to the RFQ evaluation set and tenant.
- `quotation_id` and `quotation_version_id`, when present, must belong to the same RFQ, vendor, and tenant.
- `score` must be between 0 and the criterion `max_score`.
- `note` is optional and capped, suggested 2,000 characters.
- A score entry is unique per scorecard, criterion, and vendor.

## API Contract

Add authenticated tenant-scoped endpoints under the quotation/RFQ route group.

Template endpoints:

```txt
GET    /api/quotation-scoring/templates
POST   /api/quotation-scoring/templates
GET    /api/quotation-scoring/templates/{template}
PATCH  /api/quotation-scoring/templates/{template}
POST   /api/quotation-scoring/templates/{template}/deactivate
```

RFQ scorecard endpoints:

```txt
GET    /api/rfqs/{rfq}/scorecard
POST   /api/rfqs/{rfq}/scorecard
PATCH  /api/rfqs/{rfq}/scorecard/scores
POST   /api/rfqs/{rfq}/scorecard/complete
POST   /api/rfqs/{rfq}/scorecard/reopen
```

The endpoints must be defined in `apps/api/storage/openapi/openapi.json` and consumed through regenerated `packages/api-client` code.

Suggested operation IDs:

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

Suggested schemas:

- `QuotationScoringTemplate`
- `QuotationScoringTemplateCriterion`
- `SaveQuotationScoringTemplateRequest`
- `RfqScorecard`
- `RfqScorecardCriterion`
- `RfqScorecardEntry`
- `RfqScorecardVendor`
- `RfqScorecardCompletion`
- `RfqScorecardComparisonContext`
- `CreateRfqScorecardRequest`
- `UpdateRfqScorecardScoresRequest`
- `RfqScorecardResponse`
- `RfqScorecardPermissions`

### Template Response Shape

Template list and detail responses should include:

- template identity, name, description, active state, timestamps;
- criteria ordered by `displayOrder`;
- usage summary for admin context, such as number of RFQ scorecards created from the template;
- permissions such as `canUpdate` and `canDeactivate`.

### Scorecard Response Shape

The RFQ scorecard response should include:

- `rfq`: ID, number, title/scope, status, response due date, requisition/project summary when already available.
- `scorecard`: scorecard ID, frozen template identity, status, applied/completed metadata.
- `criteria`: frozen criteria ordered by display order.
- `vendors`: vendor columns with response identity, scoreability, total raw score, weighted total, missing required count, and comparison readiness.
- `entries`: score and note per criterion/vendor.
- `completion`: overall required score counts and per-vendor missing score counts.
- `comparisonContext`: selected comparison values needed for scoring support, with links back to comparison/normalization/quotation version workspaces.
- `permissions`: `canViewScorecard`, `canApplyScorecard`, `canManageScores`, `canManageScoringTemplates`.

## Permissions

Backend policies remain authoritative.

Rules:

- Admin can create, update, deactivate, and list scoring templates for the current tenant.
- Buyer/admin can list active templates for applying to RFQs.
- Buyer/admin can view RFQ scorecards for RFQs in the current tenant when they can view/manage the RFQ comparison.
- Buyer/admin can apply one active tenant template to an RFQ.
- Buyer/admin can update score entries for the RFQ scorecard.
- Buyer/admin can complete or reopen the RFQ scorecard.
- Requesters and approvers cannot manage templates, apply scorecards, view scoring, or edit scores in P1-31.
- Vendor portal visitors cannot access scoring endpoints.
- Every query and mutation must verify tenant ownership across template, RFQ, invitation, vendor, quotation, quotation version, scorecard, criterion, entry, and actor.

If new permission flags are needed for web gating, use narrow flags such as:

- `canManageQuotationScoringTemplates`
- `canApplyRfqScorecard`
- `canManageRfqScores`

Do not introduce configurable RBAC in this slice.

## UI Design

Frontend work belongs in `apps/web/features/quotations`.

Suggested routes:

```txt
apps/web/app/(workspace)/quotations/scoring/[rfqId]/page.tsx
apps/web/app/(workspace)/quotations/scoring/templates/page.tsx
apps/web/app/(workspace)/quotations/scoring/templates/[templateId]/page.tsx
```

Suggested feature files:

```txt
apps/web/features/quotations/api/quotation-scoring-api.ts
apps/web/features/quotations/hooks/use-quotation-scoring-templates.ts
apps/web/features/quotations/hooks/use-rfq-scorecard.ts
apps/web/features/quotations/hooks/use-rfq-scorecard-actions.ts
apps/web/features/quotations/workflows/quotation-scoring-template-list-page.tsx
apps/web/features/quotations/workflows/quotation-scoring-template-form-page.tsx
apps/web/features/quotations/workflows/rfq-scoring-workspace.tsx
apps/web/features/quotations/components/quotation-scoring-template-form.tsx
apps/web/features/quotations/components/rfq-scorecard-template-picker.tsx
apps/web/features/quotations/components/rfq-scorecard-completion-banner.tsx
apps/web/features/quotations/components/rfq-scorecard-matrix.tsx
apps/web/features/quotations/components/rfq-scorecard-vendor-summary.tsx
apps/web/features/quotations/components/rfq-scorecard-comparison-context.tsx
apps/web/features/quotations/mocks/quotation-scoring-fixtures.ts
apps/web/features/quotations/mocks/quotation-scoring-handlers.ts
apps/web/features/quotations/tests/quotation-scoring-api.test.ts
apps/web/features/quotations/tests/quotation-scoring-template-form.test.tsx
apps/web/features/quotations/tests/rfq-scoring-workspace.test.tsx
```

Use `RecordWorkspaceLayout` for the RFQ scoring page. Use shadcn/Radix primitives through `packages/ui` where primitives are needed, but keep scoring-specific workflows and components in `apps/web/features/quotations`.

### Template Management Surface

Admins need a restrained operational surface, not a broad governance console.

The template list should show:

- name;
- active state;
- criterion count;
- total weight;
- last updated timestamp;
- usage count;
- actions to create, edit, and deactivate.

The template form should support:

- template name and description;
- add, remove, and reorder criteria;
- category selector;
- label;
- guidance;
- weight;
- max score;
- required toggle;
- active state behavior.

Use form validation before submission and server validation after submission. Do not allow a template with no criteria.

### RFQ Scoring Workspace

The primary entry point is the RFQ comparison workspace. Buyers should see a scoring action from `/quotations/comparisons/[rfqId]`.

If no scorecard exists:

- show RFQ context and comparison readiness;
- show active template selector;
- apply button creates the scorecard snapshot.

If a scorecard exists:

- show RFQ title, comparison link, template name, scorecard status, and completion summary;
- show vendor summary columns with raw total, weighted total, missing required count, and readiness;
- show a matrix with criteria rows and vendor columns;
- each editable cell includes score input, max score context, weighted contribution, and criterion-level note;
- show supporting comparison context without hiding the score inputs;
- show explicit incomplete states for vendors missing required scores.
- show a complete scoring action only when all required scores are present;
- show completed scorecards as read-only with a reopen action for buyers/admins.

Desktop should use a dense horizontal scoring matrix. Mobile and tablet should switch to vendor cards with criteria sections to avoid unusable compressed tables.

### Copy And Interaction Rules

Use scoring language carefully:

- Acceptable: score, weighted total, missing required score, evaluation scorecard, scoring note.
- Avoid in P1-31: winner, award, recommended, shortlisted, excluded, ranked first, approved vendor.

Totals may be sorted for usability, but the UI must not label that order as an award recommendation.

## Audit Events

Record tenant-scoped audit events for:

- `quotation_scoring_template.created`
- `quotation_scoring_template.updated`
- `quotation_scoring_template.deactivated`
- `quotation_scorecard.created`
- `quotation_scorecard.score_updated`
- `quotation_scorecard.completed`
- `quotation_scorecard.reopened`

Audit metadata should include tenant ID, actor ID, template ID, RFQ ID, scorecard ID, criterion ID when relevant, vendor ID when relevant, and before/after score values for score updates.

Do not emit award or RFQ workflow transition events from scoring actions.

## Notifications

P1-31 does not need notifications by default. Score entry changes are frequent and should not notify requesters, approvers, or vendors.

If implementation adds a notification, limit it to an internal buyer/admin scorecard completion event and keep it disabled unless the existing notification preference surface already supports it. The default design is no new notification type.

## Error Handling

API errors should be shaped for generated-client consumers:

- `403` for authenticated users without scoring permissions.
- `404` for inaccessible or cross-tenant RFQ, template, scorecard, criterion, vendor, quotation, or quotation version records.
- `409` when applying a template to an RFQ that already has a scorecard.
- `409` when editing a completed scorecard without reopening it.
- `422` for invalid template criteria, inactive template application, score out of range, missing required fields, incomplete completion attempts, or vendor/criterion mismatches.

UI should show:

- permission-denied state;
- missing RFQ or inaccessible RFQ state;
- no active templates state;
- RFQ already has scorecard state;
- comparison data incomplete state;
- score validation errors inline per cell where possible.

## Testing Strategy

### API Feature Tests

Add focused tests for:

- admin can create, update, list, and deactivate templates;
- inactive templates cannot be applied;
- buyer/admin can apply one active template to an RFQ;
- applying a second template to the same RFQ returns conflict;
- scorecard freezes template criteria and is not changed by later template edits;
- buyer/admin can update scores and criterion notes;
- buyer/admin can complete a scorecard only after required scores are present;
- buyer/admin can reopen a completed scorecard and edit scores again;
- score validation rejects values outside criterion max score;
- missing required score counts are returned correctly;
- requester, approver, vendor portal visitor, and cross-tenant users cannot access scoring endpoints;
- scoring actions do not change RFQ, quotation, normalization, comparison note, award, or invitation state;
- audit events are recorded for template and scorecard actions.

Route middleware proof should include real session auth for at least one protected scoring endpoint and one mutation endpoint, not only `actingAs()`.

### API Unit Tests

Add focused unit tests for:

- score math;
- weighted totals;
- completion summary;
- template snapshot creation;
- comparison readiness mapping.

### Web Tests

Add focused tests for:

- template list and form behavior;
- criterion add/remove/reorder validation;
- no active templates state;
- apply-template flow from an RFQ scorecard page;
- scoring matrix rendering;
- score update and note update mutation;
- missing required score indicators;
- comparison-context link back to `/quotations/comparisons/[rfqId]`;
- permission-denied and validation-error states;
- responsive/card fallback for narrow viewports if existing test utilities support it.

### Contract Verification

After OpenAPI changes:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
pnpm --filter @cognify/web typecheck
```

### Focused Verification Commands

Expected implementation verification should include:

```bash
cd apps/api
php artisan test --filter=QuotationScoringApiTest
php artisan route:list --path=api/rfqs
php artisan route:list --path=api/quotation-scoring
```

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web test -- quotation-scoring
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm typecheck
git diff --check
```

Run broader `pnpm test` or `php artisan test` if implementation touches shared auth, tenancy, shell navigation, generated client helpers, or shared UI primitives.

## Scope Guard

P1-31 is complete when buyers can score RFQ vendors in Cognify using a frozen scorecard snapshot and admins can manage the lightweight templates that feed those snapshots.

The slice is not complete if it:

- stores scores without freezing the template criteria;
- lets template edits rewrite existing RFQ scorecards;
- allows scoring to mutate RFQ, quotation, normalization, comparison, invitation, award, or vendor state;
- labels score totals as recommendation or award decisions;
- exposes scoring to vendors, requesters, or approvers;
- bypasses OpenAPI/generated-client consumption;
- duplicates generated response types in app code.

After implementation and verification, update `docs/01-product/feature-roadmap.md` for P1-31 with this design spec and the implementation plan path. Keep P1-32 recommendation/award decision, P1-33 award approval, and P1-34 PO handoff as separate not-implemented slices until their own specs are approved.
