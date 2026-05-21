# Quotation Normalization Design

## Status

- Status: Draft for review
- Date: 2026-05-21
- Release scope: P1 Epic 7, slice 1 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-29`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 7, slice 1
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-manual-entry-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-versioning-design.md`

## Purpose

This slice turns submitted quotation versions into auditable, comparable normalization records. Versioning preserves what vendors or buyers submitted; normalization records Cognify's buyer-approved interpretation of that submitted data so later comparison, scoring, recommendation, and award decisions can use stable inputs.

The slice includes structured quotation data, line-item mapping to RFQ line items, bundle pricing, attachment evidence metadata, buyer correction workflow, audit events, and notifications. It deliberately stops before PDF/XLS content extraction, OCR, AI extraction, scoring, recommendation, and award decisioning.

## Problem

Raw quotation versions are not directly comparable. Vendors may use different units, bundle several requested items into one price, omit optional commercial fields, submit incomplete attachment metadata, or provide structured values that require buyer interpretation. If comparison reads raw quotation fields directly, every downstream workflow duplicates normalization logic and cannot prove which interpreted values were used later.

Cognify needs an explicit normalization layer with traceable transformations, issue review, and immutable approval snapshots.

## Goals

- Automatically queue normalization for every new current quotation version.
- Normalize structured quotation fields and attachment evidence metadata.
- Store transformation provenance for every normalized field that matters to comparison.
- Support manual RFQ line-item mapping, including many-to-many bundle mappings.
- Support bundled pricing without forcing artificial per-line allocation.
- Classify normalization issues by severity.
- Let buyers/admins correct and approve normalization records in a separate review workspace.
- Preserve approved normalization snapshots immutably.
- Supersede old unapproved normalization records when a newer quotation version becomes current.
- Emit audit events and buyer/admin notifications for important lifecycle transitions.
- Add a roadmap loopback for future PDF/XLS quotation content extraction.

## Non-Goals

- PDF, XLS, CSV, DOC, or DOCX content parsing into line items.
- OCR or AI extraction.
- Reusable tenant-wide or vendor-specific normalization rules.
- Alternate-offer workflow semantics.
- Side-by-side comparison table.
- Vendor scoring, recommendation, award decision, award approval, or PO handoff.
- Vendor-facing normalization review or correction visibility.
- Configurable RBAC.

## Design Decisions

### Persist Per-Version Normalization

Persist a normalization workflow record per `QuotationVersion`. Do not compute comparable values on demand from raw quotation versions. Persisting the normalization record gives comparison and audit flows stable inputs even if normalization rules evolve later.

### Queue Normalization Automatically

Every new quotation version dispatches a normalization job. The job should create a draft normalization record, normalize what it can, record issues, and transition the record to a review status.

Suggested statuses:

- `pending`
- `processing`
- `needs_review`
- `ready_for_approval`
- `approved`
- `approved_with_warnings`
- `failed`
- `superseded`

### Preserve Traceability

Do not delete normalization records created from submitted quotation versions. If a newer quotation version arrives, previous unapproved normalization records become `superseded` and are hidden from active queues. They remain retrievable from version history, audit, or support/debug views.

Every transformation should be explainable with:

- source quotation version ID;
- source field path;
- raw value;
- normalized value;
- normalizer/rule version;
- issue status;
- correction metadata;
- actor metadata for corrections and approval;
- job attempt metadata;
- timestamps.

### Use Revisioned Approval Snapshots

Draft normalization records are mutable during review. Approval freezes an immutable approved normalization revision. Later buyer changes create a new normalization revision for the same quotation version instead of mutating the approved revision. Downstream comparison consumes the latest approved normalization revision for the current quotation version.

### Keep Corrections Version-Scoped

Buyer corrections apply only to the specific quotation version normalization. Do not create reusable normalization rules in this slice. Reusable rules are useful later, but they require governance, conflict handling, rule scoping, and separate audit behavior.

### Keep Normalization Internal

Normalization corrections are internal buyer/admin interpretation. Vendor portal APIs must not expose normalized corrections, review issues, or approval status. If a correction reveals a true vendor mistake, a future clarification/revision request workflow should handle vendor communication.

## Data Model

Backend ownership remains in `apps/api/Domains/Quotation`.

Suggested tables or equivalent models:

### `quotation_normalizations`

- `id`
- `tenant_id`
- `quotation_id`
- `quotation_version_id`
- `normalization_revision`
- `status`
- `is_current_for_version`
- `superseded_at`
- `normalized_at`
- `approved_at`
- `approved_by_user_id`
- `approval_note`
- `algorithm_version`
- `job_attempt_count`
- `last_job_error`
- timestamps

### `quotation_normalization_fields`

Stores comparable header and commercial fields.

- `normalization_id`
- `field_path`
- `raw_value`
- `normalized_value`
- `data_type`
- `currency`
- `confidence`
- `source`
- `provenance`
- timestamps

Examples:

- `manualEntry.currency`
- `manualEntry.totalAmount`
- `manualEntry.paymentTerms`
- `manualEntry.deliveryTerms`
- `manualEntry.leadTimeDays`
- `manualEntry.warrantyTerms`
- `completeness.lineItemCount`

### `quotation_normalization_line_groups`

Represents comparable groups built from RFQ line-item mappings and vendor quotation lines.

- `normalization_id`
- `group_number`
- `pricing_mode`
- `description`
- `currency`
- `bundle_total_amount`
- `notes`
- timestamps

Supported `pricing_mode` values:

- `per_line`: mapped RFQ lines have separate normalized line prices.
- `bundle`: one price covers the mapped group.
- `included`: item has no separate price because it is included in another bundle.
- `unknown`: comparison-critical price is unresolved.

### `quotation_normalization_line_mappings`

Supports many-to-many mapping.

- `normalization_line_group_id`
- `rfq_line_item_id`
- `quotation_version_line_item_id`
- `mapping_type`
- `quantity`
- `unit`
- `unit_price`
- `line_total`
- `buyer_note`
- timestamps

Supported `mapping_type` values:

- `full`
- `partial`
- `bundled`

Alternate-offer semantics are deferred. If a vendor line looks like a substitute, buyers may record a note or warning, but the system should not implement alternate offer state in this slice.

### `quotation_normalization_attachments`

Normalizes evidence metadata, not document content.

- `normalization_id`
- `quotation_version_attachment_id`
- `filename`
- `mime_type`
- `extension`
- `size_bytes`
- `checksum_sha256`
- `available`
- `source`
- `uploaded_at`
- `evidence_role`
- `issue_summary`
- timestamps

### `quotation_normalization_issues`

- `normalization_id`
- `severity`
- `field_path`
- `issue_code`
- `message`
- `raw_value`
- `suggested_value`
- `status`
- `resolved_by_user_id`
- `resolved_at`
- `resolution_note`
- timestamps

Severity values:

- `blocking`: must be resolved before approval.
- `warning`: can be approved with explicit acknowledgement.
- `info`: visible context, no action required.

### `quotation_normalization_corrections`

- `normalization_id`
- `issue_id`
- `field_path`
- `original_raw_value`
- `previous_normalized_value`
- `corrected_value`
- `corrected_by_user_id`
- `correction_note`
- timestamps

Corrections should update the draft normalized view but never mutate the source `QuotationVersion`.

## Normalization Rules

Initial deterministic normalizer should handle:

- currency normalization to 3-letter uppercase code;
- decimal amount normalization for subtotal, tax, freight, discount, total, unit price, and line totals;
- quantity normalization as decimal-compatible text or numeric value;
- lead time normalization as integer days;
- line-item count and required-line coverage;
- payment, delivery, warranty, exclusions, and compliance notes as preserved text with optional warnings;
- attachment evidence metadata;
- basic total reconciliation where enough values exist.

Blocking issue examples:

- missing or invalid currency;
- missing or invalid total amount;
- missing comparable line items;
- required RFQ line item has no mapping;
- line quantity cannot be parsed;
- line unit is missing or ambiguous where comparison requires it;
- line total cannot be parsed when required;
- bundle pricing mode is unknown;
- normalized total materially conflicts with line totals.

Warning examples:

- warranty terms missing;
- payment terms are unstructured text;
- delivery terms are unstructured text;
- manufacturer/model missing unless the RFQ requires it;
- attachment checksum unavailable;
- optional evidence metadata incomplete;
- extra vendor line is unmapped but does not affect required RFQ coverage.

Info examples:

- normalized text casing changed;
- optional note preserved without classification;
- attachment is present and available.

## Workflow

### Automatic Normalization

```txt
quotation version created
  -> dispatch NormalizeQuotationVersion job
  -> create normalization record in pending/processing
  -> normalize structured fields, line items, mappings, and attachment metadata
  -> record issues
  -> status becomes ready_for_approval, needs_review, or failed
  -> notify buyer/admin users for needs_review or failed
```

### New Version Supersedes Old Drafts

```txt
new current quotation version created
  -> previous current version is no longer active
  -> unapproved normalization records for older version become superseded
  -> active review queues show only current quotation version normalizations
  -> superseded records remain retrievable for traceability
```

### Buyer/Admin Review

```txt
buyer/admin opens Normalization Review Workspace for one quotation version
  -> inspect original submitted values, normalized values, issues, and attachment metadata
  -> accept suggestions or enter corrections
  -> map vendor lines to RFQ lines
  -> choose bundle pricing mode where needed
  -> resolve blocking issues
  -> approve or approve with warnings
  -> system freezes approved normalization revision
```

Approval requires all blocking issues to be resolved. Warnings can remain only when the buyer/admin explicitly approves with warnings.

## Review Workspace

Create a separate Normalization Review Workspace instead of embedding this flow inside quotation evidence/version history panels.

Initial UI scope:

- one quotation version at a time;
- current quotation version only by default;
- source quotation/version summary;
- status and issue severity summary;
- header field review table;
- line-item mapping panel;
- bundled pricing controls;
- attachment evidence metadata panel;
- issue list with corrections/resolution controls;
- approval action area.

The workspace should show enough provenance to satisfy normal business users without turning the primary UI into an audit debugger. Recommended approach:

- always show source value and normalized value side by side;
- expose field-level "details" drawers/popovers for provenance;
- include a dedicated provenance/detail section for advanced review;
- keep full provenance in the API/database even if only summarized in UI.

Visual direction is pending the user's image inspiration. Do not finalize layout composition until that reference is attached and reviewed.

## Permissions And Tenancy

Cognify currently uses fixed tenant roles and hardcoded permission mapping, not configurable RBAC. This slice should add a clear permission flag such as `canReviewQuotationNormalization`, resolved true for:

- `buyer`
- `admin`

Resolved false for:

- `requester`
- `approver`
- vendor portal access

Backend policies must still enforce tenant ownership. Every normalization query or mutation must prove that the normalization, quotation version, quotation, RFQ, invitation, vendor, line items, attachments, and actor belong to the current tenant.

Vendor portal endpoints must not expose normalization data.

## API Contract

Proposed authenticated endpoints:

- `GET /api/quotation-normalizations`
  Lists active normalization review records. Default filters should show current quotation versions that are `needs_review`, `failed`, or `ready_for_approval`.
- `GET /api/quotation-normalizations/{normalization}`
  Shows one normalization review workspace payload.
- `POST /api/quotation-normalizations/{normalization}/corrections`
  Saves field corrections and issue resolutions.
- `POST /api/quotation-normalizations/{normalization}/line-mappings`
  Saves line-item mapping and bundle pricing decisions.
- `POST /api/quotation-normalizations/{normalization}/approve`
  Approves when no blocking issues remain.
- `POST /api/quotation-normalizations/{normalization}/approve-with-warnings`
  Approves with unresolved warning issues and records acknowledgement.
- `POST /api/quotation-normalizations/{normalization}/revisions`
  Creates a new draft normalization revision from an approved one.
- `POST /api/quotation-versions/{version}/normalization/retry`
  Retries failed normalization where permission allows.

OpenAPI schemas should be generated and consumed through `@cognify/api-client`. Frontend app code should not duplicate response types.

## Audit And Notifications

Audit events:

- `quotation_normalization.started`
- `quotation_normalization.completed`
- `quotation_normalization.failed`
- `quotation_normalization.issue_recorded`
- `quotation_normalization.correction_saved`
- `quotation_normalization.line_mapping_saved`
- `quotation_normalization.approved`
- `quotation_normalization.approved_with_warnings`
- `quotation_normalization.revision_created`
- `quotation_normalization.superseded`

Audit metadata should include tenant ID, RFQ ID, quotation ID, quotation version ID, normalization ID, normalization revision, actor ID where applicable, issue IDs, field paths, previous values, corrected values, and job attempt details.

Notifications:

- Notify buyer/admin users when normalization fails.
- Notify buyer/admin users when normalization needs review.
- Notify buyer/admin users when normalization is approved or approved with warnings.
- Do not notify vendors.
- Avoid notification noise for every individual correction unless a later workflow needs granular subscriptions.

## Roadmap Loopback

The roadmap should be updated during implementation to mark P1-29 when complete and to preserve document extraction as a future slice. Add a new future roadmap row near the existing P2 extraction items named `Quotation Document Extraction`. That future item should cover parsing PDF, XLS, XLSX, CSV, DOC, and DOCX quotation evidence into structured candidate fields, human review of extracted data, and conflict handling.

Do not hide document extraction inside P1-29. P1-29 normalizes existing structured fields and attachment evidence metadata only. `P2-04 OCR Extraction Pipeline` and `P2-05 OCR Review Queue` should remain broader governance/AI features; the new quotation-specific extraction row gives the procurement quotation workflow a precise follow-up target.

## Testing Strategy

Backend tests:

- new quotation version dispatches normalization job;
- normalization creates pending/processing/review records;
- deterministic normalizer records fields, line mappings, attachment metadata, and issues;
- blocking issues prevent approval;
- warnings allow explicit approval with warnings;
- corrections are version-scoped and do not mutate quotation versions;
- approved normalization revisions are immutable;
- new normalization revision can be created from an approved record;
- newer quotation version supersedes old unapproved normalization records;
- buyer/admin can review and approve;
- requester/approver/vendor cannot access normalization review endpoints;
- cross-tenant access fails;
- audit events are recorded;
- notifications are emitted for failed/needs-review/approval states.

Frontend tests:

- review workspace loads one current quotation version normalization;
- field issue summary renders blocking/warning/info states;
- buyer can correct a blocking field and resolve the issue;
- buyer can map one vendor line to multiple RFQ lines as a bundle;
- buyer can approve once blocking issues are resolved;
- buyer can approve with warnings;
- vendor portal does not expose normalization data;
- MSW state mirrors API-shaped normalization lifecycle.

Contract and integration tests:

- OpenAPI includes normalization schemas and endpoints;
- generated client exports are used by frontend hooks;
- API contract check passes;
- targeted backend and frontend suites pass;
- root lint, typecheck, build, and whitespace checks pass.

## Open Design Inputs

- User will attach visual inspiration for the Normalization Review Workspace before UI layout is finalized.
- Exact roadmap treatment for PDF/XLS extraction should be decided during implementation planning.
- Whether approvers later receive read-only normalization visibility should be revisited when P1-30 comparison begins.
