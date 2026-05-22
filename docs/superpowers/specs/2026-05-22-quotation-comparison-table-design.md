# Quotation Comparison Table Design

## Status

- Status: Draft for review
- Date: 2026-05-22
- Release scope: P1 Epic 7, slice 2 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-30`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 7, slice 2
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-manual-entry-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-versioning-design.md`
  - `docs/superpowers/specs/2026-05-21-quotation-normalization-design.md`

## Purpose

This slice gives buyers an RFQ-level workspace for comparing vendor quotation responses side by side inside Cognify. The comparison table consumes the latest approved quotation normalization revision for each current quotation version, so buyers evaluate stable, auditable inputs instead of raw vendor submissions or spreadsheets.

The slice also supports buyer comparison notes. Notes are annotations only. They do not change normalization, scoring, ranking, recommendation, award state, RFQ state, quotation state, or vendor eligibility.

## Problem

P1-29 creates approved normalization records, but buyers still need a single workspace to compare vendor responses for one RFQ. Without that workspace, the normalized data is technically available but operationally awkward: buyers would still export values or switch between vendor records, which weakens auditability and slows evaluation.

Cognify needs a central comparison surface that makes differences visible while preserving the boundary between comparison, scoring, and award decisions.

## Goals

- Add an RFQ-level buyer comparison workspace.
- Compare current vendor quotations using latest approved normalization revisions only.
- Show side-by-side vendor differences for price, delivery, terms, compliance, evidence readiness, risk placeholder, and buyer notes.
- Support buyer/admin comparison notes without creating workflow side effects.
- Clearly mark quotations that are missing approved normalization.
- Preserve bundle pricing and unmapped line states without forcing artificial per-line allocation.
- Keep the comparison read model tenant-scoped, policy-guarded, and OpenAPI-backed.
- Record audit events when comparison notes are created, updated, or deleted.
- Keep vendor scoring, recommendation, award decision, award approval, and PO handoff out of this slice.

## Non-Goals

- Vendor scoring matrix, weighted criteria, ranking, or score persistence.
- Recommendation, shortlist, exclusion, award decision, award approval, or purchase order handoff.
- Mutating quotation normalization records from the comparison table.
- Automatically selecting winners or changing RFQ or quotation workflow status.
- Vendor-facing comparison visibility.
- AI comparison narrative, OCR, document extraction, currency conversion, or risk scoring.
- A standalone comparison queue.
- Export-to-spreadsheet workflow.

## Design Decisions

### Use The RFQ As The Comparison Boundary

The comparison workspace is opened for one RFQ at a time, for example `/quotations/comparisons/[rfqId]`. This matches the buyer's evaluation task: compare the invited vendors that responded to the same RFQ.

This slice should link from the RFQ workspace rather than introduce a separate comparison queue. A queue can be added later if buyers need a daily worklist for RFQs ready for evaluation.

### Consume Approved Normalization Only

Comparison values come from the latest approved normalization revision for each current quotation version. Draft, failed, superseded, or needs-review normalization records are not used as comparable values.

If a vendor response has no approved normalization, the table should show readiness state and link to the normalization workspace. The comparison page must not silently fall back to raw quotation fields.

### Persist Notes, Not Comparison Results

The comparison matrix itself is a read model assembled from RFQ, quotation, and normalization records. P1-30 should not persist a mutable comparison result object because that would duplicate approved normalization and create a second source of truth.

The only new durable comparison state is buyer/admin notes. Notes are useful context for the buyer, but they are not decision state.

### Keep Notes Non-Decisioning

Comparison notes must not:

- affect ranking or sorting beyond ordinary UI filtering;
- mark vendors as shortlisted, excluded, recommended, or awarded;
- change RFQ, quotation, normalization, approval, or award status;
- emit workflow notifications by default;
- become required for recommendation or award in this slice.

Audit should record note changes as note changes, not evaluation transitions.

### Show Risk As A Placeholder Only

The table should reserve visible space for risk but label it as not scored or not configured. This gives P1-31 and later governance/risk slices a stable UI slot without inventing risk values.

### Preserve Bundle Semantics

If normalization marked a vendor price as a bundle, the comparison table should display it as a bundle. P1-30 must not allocate bundled totals across RFQ lines unless a later normalization/scoring slice explicitly adds governed allocation behavior.

## Workflow

### Actors

- Buyer/admin: views RFQ comparison and manages comparison notes.
- Requester/approver: no comparison access in this slice.
- Vendor portal visitor: no access to comparison data or notes.
- System: assembles read-model response and records audit events for note changes.

### Entry Point

The primary entry point is the RFQ workspace. Buyers open a comparison action from an RFQ that has invited vendors and quotation responses.

Route:

```txt
/quotations/comparisons/[rfqId]
```

### Read Flow

1. Buyer opens the RFQ comparison page.
2. Web hook calls the generated API client with `X-Tenant-Id`.
3. API verifies session, tenant, RFQ ownership, and policy.
4. API loads RFQ, invited vendors, current quotations, and latest approved normalizations for current quotation versions.
5. API returns an OpenAPI-shaped comparison payload.
6. UI renders readiness, vendor summary, line comparison, commercial terms, notes, and risk placeholder.

### Note Flow

1. Buyer creates, edits, or deletes a comparison note.
2. API validates that the note target belongs to the same RFQ and tenant.
3. API persists the note mutation.
4. API records an audit event.
5. Web invalidates the comparison query and re-renders notes.

No workflow state transition happens.

## Data Model

Backend ownership remains in `apps/api/Domains/Quotation`.

### `quotation_comparison_notes`

Stores buyer/admin annotations scoped to one RFQ comparison.

- `id`
- `tenant_id`
- `rfq_id`
- `quotation_id`, nullable
- `vendor_id`, nullable
- `rfq_line_item_id`, nullable
- `section`
- `note`
- `created_by_user_id`
- `updated_by_user_id`, nullable
- `deleted_by_user_id`, nullable if soft deletes are used
- timestamps
- soft delete timestamp if the implementation uses soft deletes

Suggested `section` values:

- `overall`
- `price`
- `delivery`
- `terms`
- `compliance`
- `risk`

Validation rules:

- `note` is required, trimmed, and capped at 2,000 characters.
- `section` must be one of the controlled values.
- If `quotation_id`, `vendor_id`, or `rfq_line_item_id` is present, it must belong to the same tenant and RFQ.
- Updating or deleting a note requires the note to belong to the same tenant and RFQ.

## API Contract

Add authenticated tenant-scoped endpoints under the existing RFQ/quotation route group:

```txt
GET    /api/rfqs/{rfq}/comparison
POST   /api/rfqs/{rfq}/comparison/notes
PATCH  /api/rfqs/{rfq}/comparison/notes/{note}
DELETE /api/rfqs/{rfq}/comparison/notes/{note}
```

The endpoints must be defined in `apps/api/storage/openapi/openapi.json` and consumed through regenerated `packages/api-client` code.

### Comparison Response Shape

Use explicit OpenAPI schema names such as `QuotationComparisonResponse`, `QuotationComparisonRfq`, `QuotationComparisonVendor`, `QuotationComparisonLineRow`, `QuotationComparisonVendorCell`, `QuotationComparisonCommercialTerm`, `QuotationComparisonNote`, and `QuotationComparisonPermissions`. The response should include:

- `rfq`: ID, number, title/scope, status, response due date, requisition/project summary when already available.
- `readiness`: response count, approved normalization count, pending normalization count, missing response count, mixed currency flag.
- `vendors`: one column per invited/responding vendor with quotation, current version, approved normalization, readiness, issue counts, totals, lead time, and note count.
- `lineRows`: RFQ line-item rows with vendor cells sourced from approved normalization line groups/mappings.
- `commercialTerms`: section rows for subtotal, tax, freight, discount, total, valid until, lead time, payment terms, delivery terms, warranty, exclusions, and compliance notes.
- `notes`: persisted comparison notes grouped by section/target.
- `links`: RFQ, quotation version, and normalization workspace links.
- `permissions`: `canViewComparison`, `canManageComparisonNotes`.

## Permissions

Add a comparison policy path under the quotation domain. Backend policy remains authoritative.

Rules:

- Buyer/admin can view comparison for RFQs in the current tenant when they can view/manage the RFQ.
- Buyer/admin can create, update, and delete comparison notes.
- Vendors cannot access comparison endpoints.
- Requesters and approvers cannot view comparison or manage comparison notes in P1-30.
- Every query and mutation must verify tenant ownership across RFQ, invitation, quotation, vendor, RFQ line item, normalization, note, and actor.

If a new permission flag is needed for web gating, use a narrow flag such as `canManageQuotationComparisonNotes`. Do not introduce configurable RBAC in this slice.

## UI Design

Frontend work belongs in `apps/web/features/quotations`.

Suggested route:

```txt
apps/web/app/(workspace)/quotations/comparisons/[rfqId]/page.tsx
```

Suggested feature files:

```txt
apps/web/features/quotations/api/quotation-comparison-api.ts
apps/web/features/quotations/hooks/use-quotation-comparison.ts
apps/web/features/quotations/hooks/use-quotation-comparison-notes.ts
apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx
apps/web/features/quotations/components/quotation-comparison-vendor-summary.tsx
apps/web/features/quotations/components/quotation-comparison-table.tsx
apps/web/features/quotations/components/quotation-commercial-terms-table.tsx
apps/web/features/quotations/components/quotation-comparison-notes-panel.tsx
apps/web/features/quotations/components/quotation-comparison-readiness-banner.tsx
apps/web/features/quotations/mocks/quotation-comparison-fixtures.ts
apps/web/features/quotations/mocks/quotation-comparison-handlers.ts
apps/web/features/quotations/tests/quotation-comparison-workspace.test.tsx
apps/web/features/quotations/tests/quotation-comparison-api.test.ts
```

Use `RecordWorkspaceLayout` for the page shell. Use shadcn/Radix primitives through `packages/ui` where primitives are needed, but keep comparison-specific components in `apps/web/features/quotations`.

### Page Structure

Header:

- RFQ number and title/scope.
- Response count.
- Approved-normalization count.
- Mixed-readiness or mixed-currency indicator.
- Link back to RFQ workspace.

Main sections:

- Overview/readiness.
- Vendor summary strip.
- Line-item comparison table.
- Commercial terms table.
- Comparison notes panel.
- Risk placeholder.

### Comparison Table

The desktop comparison table should show RFQ line items down the left and vendors across columns.

Each vendor cell should include:

- mapped quotation line or group description;
- pricing mode;
- quantity and unit when available;
- unit price and line total when available;
- bundle total when pricing mode is `bundle`;
- compliance notes or warning markers;
- link to normalization workspace when data is missing or unresolved.

The table should not force per-line allocation for bundle prices.

### Commercial Terms

Show vendor columns for:

- subtotal;
- tax;
- freight;
- discount;
- total;
- valid until;
- lead time;
- payment terms;
- delivery terms;
- warranty;
- exclusions;
- compliance notes.

Currency mismatch should be visible. P1-30 does not convert currencies.

### Notes Panel

Buyers/admins can add, edit, and delete notes scoped to:

- overall RFQ comparison;
- vendor;
- RFQ line item;
- section.

The UI must label notes as annotations and avoid decision language such as shortlist, reject, recommend, winner, or award.

### Responsive Behavior

Desktop should use a dense horizontal matrix. Mobile and tablet should use vendor cards plus section-by-section comparison lists rather than squeezing the full table into an unusable layout.

## Empty And Error States

- No quotations: show "No vendor responses yet" and link back to RFQ invitations.
- Quotations but no approved normalizations: show vendors with "Normalization required" links; do not show fabricated comparison values.
- Mixed readiness: compare vendors with approved normalization and mark incomplete vendors clearly.
- Currency mismatch: show mixed-currency warning; do not convert values.
- Bundle pricing: show bundle groups as bundles.
- Missing line mappings: show "Unmapped" and link to normalization.
- Permission denied: show buyer/admin access message.
- Stale note update: return conflict and ask the buyer to refresh.
- Deleted note target: keep the soft-deleted note audit-visible and prevent editing against a missing target.

## Audit And Notifications

Audit events:

- `quotation_comparison.note_created`
- `quotation_comparison.note_updated`
- `quotation_comparison.note_deleted`

Each audit event should include tenant, actor, RFQ, note target metadata, and before/after note metadata where appropriate.

Do not emit workflow notifications by default. Comparison notes are non-blocking annotations and should not interrupt other users unless a later collaboration slice adds mentions or explicit notification behavior.

## Testing Strategy

### API Tests

Add feature tests for:

- buyer/admin can view RFQ comparison;
- requester/approver/vendor access is denied according to P1-30 scope;
- cross-tenant RFQ and note targets do not leak;
- no quotations response;
- quotations without approved normalization;
- mixed readiness response;
- mixed currency flag;
- bundle pricing response;
- comparison note create/update/delete;
- validation for invalid section, note length, and cross-RFQ targets;
- audit events for note changes.

Add focused unit/query tests for comparison read-model assembly from approved normalization revisions.

### Web Tests

Add tests for:

- loading, error, permission-denied, empty, and populated states;
- vendor summary rendering;
- line comparison rendering;
- bundle pricing display;
- currency mismatch warning;
- normalization-required links;
- note add/edit/delete;
- disabled note controls without permission;
- mobile fallback structure where practical.

### Contract And Verification

OpenAPI and generated clients must change together.

Focused verification should include:

```bash
pnpm generate:api
pnpm check:api-contract
php artisan test --filter=QuotationComparison
php artisan test --filter=QuotationNormalization
pnpm --filter @cognify/web test -- quotation-comparison
pnpm --filter @cognify/web test -- quotation-normalization
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
pnpm typecheck
```

If route definitions change, also run:

```bash
php artisan route:list --path=api/rfqs
```

## Roadmap Loopback

After implementation and verification, update `docs/01-product/feature-roadmap.md` for P1-30 with this design spec and the implementation plan path. Keep P1-31 vendor scoring, P1-32 recommendation/award decision, P1-33 award approval, and P1-34 PO handoff as separate not-implemented slices until their own specs are approved.

Implementation planning should use soft deletes for comparison notes, add a single comparison action link from the RFQ detail workspace, and keep requester/approver comparison visibility out of P1-30.
