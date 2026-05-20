# Quotation Versioning Design

## Status

- Status: Draft for review
- Date: 2026-05-20
- Release scope: P1 Epic 6, slice 4 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-28`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 6, slice 4
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-manual-entry-design.md`

## Purpose

This slice adds quotation revision history so revised vendor responses do not overwrite previously reviewed information. It preserves prior uploaded evidence, structured terms, line items, and evaluation-time snapshots before later comparison, scoring, and award workflows depend on the quotation data.

The versioning model should make one current quotation easy to inspect while keeping previous versions available for audit and future evaluation references.

## Problem

Vendors frequently submit revised prices, corrected files, alternate products, or updated delivery terms. Buyers also correct manually entered values after receiving better information. If Cognify mutates the current quotation in place, later comparison and award decisions cannot prove which vendor price and terms were evaluated at decision time.

The system needs a revision model before quotation normalization and comparison begin using quotation data as evidence.

## Goals

- Preserve every submitted quotation revision for an RFQ invitation.
- Mark exactly one current version per quotation.
- Snapshot uploaded attachments, header terms, line items, totals, source, and submitted timestamps per version.
- Let buyers and vendors create a new revision from the current quotation where policy allows.
- Keep previous versions read-only.
- Show version history in the buyer RFQ workspace and vendor portal.
- Prepare a stable evaluation snapshot boundary for later normalization and comparison.
- Emit audit events for version creation and current-version changes.

## Non-Goals

- Quotation normalization into comparable evaluation fields.
- Side-by-side comparison tables.
- Vendor scoring, recommendation, award decision, award approval, or PO handoff.
- OCR or AI extraction.
- Contracting, negotiation rounds, reverse auction behavior, or multi-round sourcing.
- Full vendor account management.

## Versioning Model

Keep `Quotation` as the durable response container for one vendor invitation. Add `QuotationVersion` as the immutable revision unit.

Suggested `quotations` fields:

- `tenant_id`
- `rfq_id`
- `rfq_invitation_id`
- `vendor_id`
- `number`
- `status`
- `current_version_id`
- `version_count`
- `latest_received_at`
- timestamps

Suggested `quotation_versions` fields:

- `tenant_id`
- `quotation_id`
- `version_number`
- `status`
- `submission_source`
- `submitted_at`
- `submitted_by_user_id`
- `submitted_by_vendor_contact`
- `quotation_reference`
- header terms snapshot
- line item snapshot or related version line items
- attachment snapshot references
- `superseded_at`
- `metadata`
- timestamps

Previous versions must remain readable but not editable. The current version can be superseded only by creating a new version in a transaction.

## Workflow

Initial version:

```txt
quotation upload or manual entry exists
  -> system creates version 1
  -> quotation.current_version_id points to version 1
```

Vendor revision:

```txt
vendor opens eligible portal token
  -> vendor starts revision from current version
  -> vendor uploads replacement/additional evidence or updates structured fields
  -> system creates next version
  -> previous version becomes superseded
  -> new version becomes current
```

Buyer revision:

```txt
buyer opens RFQ invitation quotation
  -> buyer starts buyer-entered correction or revised vendor response
  -> system creates next version
  -> previous version remains read-only
  -> current version updates
```

Workflow rules:

- Version creation requires an existing quotation.
- Initial upload/manual-entry data should be migrated or represented as version 1.
- Versions are sequential per quotation.
- Only the current version can be used as the default source for later normalization.
- Previous versions remain available for audit and future decision review.
- Vendor-created revisions require valid portal access and response-eligible invitation state.
- Buyer-created revisions require authenticated tenant permission.
- Terminal RFQ or invitation states should block new versions unless the implementation plan defines an admin correction exception.

## Backend Design

Backend ownership remains in `apps/api/Domains/Quotation`.

Add domain actions:

- create initial quotation version from existing upload/manual entry data;
- create buyer revision from current version;
- create vendor portal revision from current version;
- supersede previous current version in a transaction;
- snapshot attachments and structured fields;
- expose version history resources;
- block edits to non-current versions.

The version snapshot must be stable enough for later evaluation. Attachment records can remain in `apps/api/Domains/Attachment`, but a version must record which attachments belonged to that version when it was submitted. If attachments are soft-deleted later, the version should still show historical metadata and a policy-aware unavailable file state.

## API Contract

Proposed authenticated endpoints:

- `GET /api/quotations/{quotation}/versions`
  Lists versions with status, source, submitted timestamp, totals, and current marker.
- `GET /api/quotations/{quotation}/versions/{version}`
  Shows one version snapshot.
- `POST /api/quotations/{quotation}/versions`
  Creates a buyer/admin revision from uploaded files and/or structured fields.

Proposed vendor portal endpoints:

- `GET /api/vendor-portal/rfq-invitations/{token}/quotation/versions`
  Lists vendor-safe versions for the invitation quotation.
- `POST /api/vendor-portal/rfq-invitations/{token}/quotation/versions`
  Creates a vendor-submitted revision.

Responses should include:

- quotation summary;
- version number and current marker;
- source and submitted metadata;
- header terms snapshot;
- line item snapshot;
- attachment snapshot metadata;
- permissions.

OpenAPI schemas should avoid duplicating quotation response types in app code. Frontend wrappers can map generated schemas into view models.

## Frontend Design

Buyer RFQ workspace:

- quotation panel shows current version summary;
- version selector or history list exposes previous versions;
- "New revision" action is available where permissions allow;
- previous versions render read-only;
- attachment and structured field sections are scoped to the selected version.

Vendor portal:

- vendor sees current submitted version and allowed prior versions for their invitation only;
- "Submit revision" appears only while portal access and invitation state allow it;
- previous versions are read-only;
- buyer-only notes and internal evaluation context are never exposed.

This UI should remain inside the RFQ and portal response surfaces. A standalone quotation comparison workspace belongs to Epic 7.

## Permissions And Tenancy

Buyer/admin revision requires authenticated tenant context and quotation management permission. Vendor revision derives from portal token access. Every version query must prove the quotation, RFQ, invitation, vendor, version, and attachment metadata belong to the same tenant.

Field exposure rules:

- vendor portal can see only vendor-safe fields and attachments for its invitation;
- buyer workspace can see all buyer-visible quotation version data;
- previous versions cannot be mutated by buyer or vendor endpoints.

## Audit And Notifications

Audit events should include:

- `quotation.version_created`
- `quotation.version_superseded`
- `quotation.current_version_changed`

Metadata should include quotation ID, version ID, version number, previous current version ID, RFQ ID, invitation ID, vendor ID, source, and actor context.

Buyer-facing notifications can be emitted when a vendor submits a revision if the implementation can reuse the existing notification foundation narrowly.

## Testing Strategy

Backend tests:

- initial quotation data creates version 1;
- buyer revision creates version 2 and marks version 1 superseded;
- vendor portal revision creates a new current version through valid token access;
- previous versions are read-only;
- current version is unique per quotation;
- cross-tenant version and attachment access is blocked;
- terminal invitation states block vendor revisions;
- audit events are recorded.

Frontend tests:

- buyer workspace renders current version and version history;
- buyer can start a revision where permissions allow;
- previous versions render read-only;
- vendor portal can submit a revision for an eligible invitation;
- unavailable token or terminal state hides revision actions;
- selected version changes visible terms and attachments.

Contract checks:

- update `apps/api/storage/openapi/openapi.json`;
- regenerate `packages/api-client/src/generated/**`;
- run `pnpm check:api-contract`.

## Exit Criteria

- Each quotation has immutable version history.
- New buyer or vendor revisions create a new current version instead of overwriting prior submitted data.
- Previous versions preserve their file and structured data snapshots.
- Buyer and vendor surfaces expose version history with correct field visibility.
- The model is ready for `P1-29` quotation normalization to consume the current version as an evaluation snapshot.
