# Quotation Upload Design

## Status

- Status: Draft for review
- Date: 2026-05-20
- Release scope: P1 Epic 6, slice 2 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-26`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 6, slice 2
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-19-rfq-draft-creation-design.md`
  - `docs/superpowers/specs/2026-05-19-vendor-invitation-to-rfq-design.md`
  - `docs/superpowers/specs/2026-05-20-vendor-portal-baseline-design.md`
  - `docs/superpowers/specs/2026-05-14-file-attachment-baseline-design.md`

## Purpose

This slice lets buyers and invited vendors upload quotation files against an RFQ invitation. It turns the existing read-only vendor portal into the first response capture surface and gives buyers a controlled way to record quotation evidence received outside Cognify.

The result is a durable tenant-scoped `Quotation` record linked to an RFQ, vendor, invitation, and attachments uploaded one file at a time. This slice deliberately stops at file-backed quotation capture. Structured commercial fields, manual line item entry, quotation versioning, normalization, comparison, scoring, award decisions, award approvals, and purchase order handoff are later slices.

## Problem

The vendor portal now exposes RFQ packages, but vendors cannot submit evidence and buyers cannot attach vendor quotation files to the sourcing record. Without this slice, the RFQ workflow still breaks at the point where procurement teams receive vendor responses. Files would remain in email or local storage, and later comparison or audit workflows would lack a trustworthy source record.

The upload design must preserve two access paths:

- vendor contact submits through the public token-backed portal;
- buyer or admin uploads on behalf of a vendor from the authenticated RFQ workspace.

Both paths must produce the same quotation record shape and audit history.

## Goals

- Let an invited vendor upload quotation files through a valid vendor portal token.
- Let a buyer or admin upload quotation files against an RFQ invitation from the authenticated RFQ workspace.
- Create or reveal one draft/submitted quotation record per RFQ invitation for this slice.
- Attach uploaded files to the quotation record using the existing attachment baseline.
- Preserve tenant, RFQ, invitation, and vendor consistency.
- Expose quotation upload status in the buyer RFQ invitation panel and vendor portal.
- Emit audit events for quotation creation and file upload.
- Update OpenAPI and consume generated `@cognify/api-client` endpoints.

## Non-Goals

- Manual quotation line item entry.
- Quotation versioning or revision history.
- Quotation normalization into comparable fields.
- OCR, AI extraction, confidence scoring, or review queues.
- Quotation comparison tables, scoring matrices, recommendations, awards, award approvals, or PO handoff.
- Vendor accounts, vendor login, email delivery, or two-way vendor messaging.
- A standalone quotation workspace outside the RFQ context.

## Actors

- Vendor contact: uses a valid invitation portal token to upload quotation evidence for that invitation.
- Buyer: uploads quotation evidence received by email or another channel and reviews upload status from the RFQ workspace.
- Admin: can recover or record quotation evidence where tenant policy allows.
- System: validates token or session access, creates quotation records, stores attachments, and records audit events.

## Workflow

Vendor upload:

```txt
vendor opens portal token
  -> system validates invitation state and token expiry
  -> vendor selects one quotation file
  -> system validates the file
  -> system creates or reuses the invitation quotation
  -> system stores the attachment against the quotation
  -> portal shows submitted evidence status
```

Buyer upload:

```txt
buyer opens RFQ workspace
  -> buyer selects an invitation
  -> buyer uploads one vendor quotation file
  -> system validates buyer permission, RFQ, invitation, and vendor
  -> system creates or reuses the invitation quotation
  -> system stores the attachment against the quotation
  -> RFQ workspace shows quotation evidence status
```

Workflow rules:

- Upload is allowed only for response-eligible invitations: `sent` and `acknowledged`.
- Cancelled, declined, expired, or token-expired invitations cannot accept vendor uploads.
- Buyer uploads require authenticated tenant context and RFQ invitation management permission.
- Vendor uploads derive tenant context from the portal token and never trust `X-Tenant-Id`.
- Each invitation can have one active quotation capture record in this slice.
- Multiple files can be attached to the same quotation by repeating single-file uploads.
- Uploading another file to an existing quotation appends evidence. It does not create a new version in this slice.
- File delete behavior should follow the attachment policy. If delete is allowed, deletion must be audited.

## Backend Design

Backend ownership belongs in `apps/api/Domains/Quotation`. The existing `apps/api/Domains/Attachment` domain remains the storage and file metadata owner. `apps/api/Domains/Vendor` and `apps/api/Domains/Requisition` are supporting context only.

Extend `Quotation` from the current lightweight demo/search model into a product workflow model:

- `tenant_id`
- `rfq_id`
- `rfq_invitation_id`
- `vendor_id`
- `number`
- `status`
- `submission_source`
- `submitted_at`
- `submitted_by_user_id`
- `submitted_by_vendor_contact`
- `file_count`
- `latest_received_at`
- `metadata`
- timestamps

Suggested quotation statuses for this slice:

- `draft`: created but not yet submitted or visible as received.
- `received`: at least one valid quotation file has been uploaded.
- `withdrawn`: reserved for a later vendor correction workflow if needed.
- `superseded`: reserved for the versioning slice.

Domain actions should own behavior:

- resolve a vendor portal token for quotation upload;
- create or reveal the quotation for an RFQ invitation;
- upload quotation attachments through the attachment service;
- record buyer-submitted and vendor-submitted source metadata;
- update file count and received timestamp;
- reject invalid invitation state, tenant mismatch, or unauthorized access.

Controllers should stay thin and delegate workflow decisions to domain actions.

## API Contract

OpenAPI should be updated before frontend integration hardens.

Proposed vendor portal endpoints:

- `GET /api/vendor-portal/rfq-invitations/{token}/quotation`
  Returns current quotation evidence status for the invitation.
- `POST /api/vendor-portal/rfq-invitations/{token}/quotation/attachments`
  Uploads one file per request as vendor-submitted quotation evidence.

Proposed authenticated endpoints:

- `GET /api/rfq-invitations/{invitation}/quotation`
  Returns current quotation evidence status for buyer/admin review.
- `POST /api/rfq-invitations/{invitation}/quotation/attachments`
  Uploads one file per request as buyer-submitted quotation evidence.
- `GET /api/quotations/{quotation}/attachments`
  Lists quotation attachments where the actor is authorized.

Responses should include:

- quotation ID, number, status, source, and timestamps;
- RFQ and invitation summaries;
- vendor summary;
- attachment summaries;
- permissions for buyer upload, vendor upload, preview, download, and delete where relevant.

Upload should use `multipart/form-data` and the existing attachment validation defaults unless quotation-specific limits are added in the implementation plan.

## Frontend Design

Buyer workflow stays in `apps/web/features/sourcing` because it is part of the RFQ workspace. Vendor workflow stays in `apps/web/features/vendor-portal` because it uses public token access. Shared generic attachment UI can remain under `apps/web/features/attachments`.

Buyer RFQ workspace additions:

- invitation panel shows quotation evidence status per vendor;
- upload action appears for eligible invitations when permissions allow;
- uploaded files render with preview/download actions;
- validation errors appear at file level;
- terminal invitation states render read-only status.

Vendor portal additions:

- RFQ package page includes a quotation evidence section;
- upload control appears only when the token and invitation are response-eligible;
- successful upload shows the received files and submitted timestamp;
- invalid, expired, cancelled, declined, or expired invitation states do not expose upload controls.

Components must use hooks backed by generated clients. Production components must not import MSW fixtures directly.

## Permissions And Tenancy

Authenticated buyer/admin upload requires:

- authenticated Sanctum session;
- resolved current tenant;
- actor membership in the tenant;
- invitation belongs to the current tenant;
- RFQ belongs to the current tenant;
- vendor belongs to the current tenant;
- policy allows quotation upload for that RFQ invitation.

Vendor portal upload requires:

- valid opaque portal token;
- invitation is response-eligible;
- token is not expired;
- RFQ and vendor are loaded only through the invitation relationship;
- no tenant header is accepted as authority.

Error responses must avoid tenant, RFQ, or vendor enumeration.

## Audit And Notifications

Audit events should include:

- `quotation.created`
- `quotation.attachment_uploaded`
- `quotation.attachment_deleted` if delete is in scope
- `rfq_invitation.quotation_received`

Metadata should include tenant ID, RFQ ID, invitation ID, vendor ID, quotation ID, attachment IDs, submission source, and actor context. Vendor portal actor fields should follow the system/null actor convention from the portal baseline.

In-app notifications can be limited to buyer-facing "quotation received" events if the implementation can reuse the existing notification foundation without widening scope.

## Testing Strategy

Backend tests:

- vendor token upload creates a quotation and attachment for a valid invitation;
- buyer upload creates the same quotation shape from authenticated context;
- repeated upload appends attachments to the existing quotation;
- invalid, expired, cancelled, and declined invitation token states are rejected without leaking details;
- cross-tenant RFQ, vendor, invitation, and attachment access is blocked;
- file validation errors use the API error contract;
- audit events are recorded.

Frontend tests:

- vendor portal renders upload controls for eligible invitations;
- vendor portal hides upload controls for unavailable invitations;
- buyer RFQ invitation panel shows quotation status and upload action;
- upload success refreshes quotation evidence status;
- file validation errors render at file level;
- preview/download actions use generated client wrappers.

Contract checks:

- update `apps/api/storage/openapi/openapi.json`;
- regenerate `packages/api-client/src/generated/**`;
- run `pnpm check:api-contract`.

## Exit Criteria

- Vendors can upload quotation evidence from a valid portal token.
- Buyers can upload quotation evidence for an invited vendor from the RFQ workspace.
- Uploaded files are linked to a durable quotation record and authorized attachment metadata.
- Cross-tenant and invalid invitation states are blocked.
- Buyer and vendor UI surfaces show received quotation evidence.
- The implementation leaves structured manual entry and versioning for the next slices.
