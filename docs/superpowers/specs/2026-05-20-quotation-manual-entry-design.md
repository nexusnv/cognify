# Quotation Manual Entry Design

## Status

- Status: Draft for review
- Date: 2026-05-20
- Release scope: P1 Epic 6, slice 3 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-27`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 6, slice 3
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-20-vendor-portal-baseline-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`

## Purpose

This slice adds structured quotation entry for buyer-recorded and vendor-entered responses. It lets Cognify capture the commercial substance of a quotation even when the vendor response arrives outside the portal, arrives as an incomplete file, or needs buyer cleanup before later comparison.

The output is a quotation record with structured header terms and line items. This slice does not introduce version history or comparison behavior. Edits update the current quotation capture record until the versioning slice adds revision rules.

## Problem

File upload gives Cognify evidence, but files alone are not enough for procurement operations. Buyers need to enter price, quantity, currency, lead time, warranty, payment terms, exclusions, and compliance notes so quotation data can eventually be normalized and compared. Vendors should also be able to provide the same structured fields directly through the portal when they are ready to respond inside Cognify.

Manual entry must support partial and messy real-world responses without pretending Cognify has OCR or AI extraction yet.

## Goals

- Let buyers manually enter structured quotation data for an RFQ invitation.
- Let vendors enter structured quotation data through a valid portal token.
- Support quotation header terms, line items, totals, and commercial notes.
- Allow manual entry with or without uploaded quotation files.
- Preserve links to RFQ line items where possible, while allowing ad hoc quoted line items.
- Track save state and completeness without routing to comparison yet.
- Use generated API clients and OpenAPI-shaped MSW fixtures.
- Emit audit events for structured quotation data changes.

## Non-Goals

- Quotation revision history.
- Quotation normalization as an evaluation snapshot.
- Side-by-side comparison tables.
- Vendor scoring, recommendations, award decisions, award approval, or PO handoff.
- OCR extraction or AI-assisted field suggestions.
- Automatic currency conversion or tax calculation beyond explicit entered values.
- Full vendor profile management.

## Actors

- Buyer: records or cleans up structured quotation data received outside Cognify.
- Vendor contact: enters structured response details through the invitation portal.
- Admin: can correct or recover quotation entry where policy allows.
- System: validates data, persists the current quotation structure, and records audit events.

## Data Capture Model

Quotation header fields:

- `quotationReference`
- `quotedAt`
- `validUntil`
- `currency`
- `subtotalAmount`
- `taxAmount`
- `freightAmount`
- `discountAmount`
- `totalAmount`
- `paymentTerms`
- `deliveryTerms`
- `leadTimeDays`
- `warrantyTerms`
- `exclusions`
- `complianceNotes`
- `buyerNotes`
- `vendorNotes`

Quotation line item fields:

- `rfqLineItemId`
- `description`
- `quantity`
- `unit`
- `unitPrice`
- `subtotalAmount`
- `taxAmount`
- `totalAmount`
- `leadTimeDays`
- `manufacturer`
- `modelNumber`
- `alternateOffered`
- `complianceStatus`
- `notes`

The API should validate arithmetic enough to catch malformed data, but it should not silently recalculate buyer-entered or vendor-entered values unless the implementation plan deliberately adds derived preview totals. If derived totals are shown, server values remain authoritative.

## Workflow

Buyer entry:

```txt
buyer opens RFQ invitation quotation
  -> buyer opens structured entry form
  -> buyer enters header terms and line items
  -> system validates and saves current quotation data
  -> RFQ workspace shows structured data completeness
```

Vendor entry:

```txt
vendor opens portal token
  -> vendor opens quotation response section
  -> vendor enters header terms and line items
  -> system validates token and invitation state
  -> system saves current quotation data
  -> portal shows submitted structured response
```

Workflow rules:

- Manual entry requires an existing or creatable quotation for the RFQ invitation.
- Buyer entry is allowed for response-eligible invitations and buyer-managed correction states.
- Vendor entry is allowed only while portal access is valid and response-eligible.
- Vendors cannot edit buyer-only notes.
- Buyers can see vendor-entered fields and add buyer notes.
- Buyer edits must not erase uploaded evidence.
- Manual entry can be saved as incomplete until required fields are present.
- "Ready for evaluation" can be represented as a completeness flag in this slice, not a new comparison workflow.

## Backend Design

Backend ownership remains in `apps/api/Domains/Quotation`.

Add quotation detail storage either as first-class tables or JSON columns depending on implementation risk. The preferred durable shape is:

- `quotation_terms` or columns on `quotations` for header terms;
- `quotation_line_items` table for structured quoted line items;
- explicit relationships to `rfq_line_items` where applicable.

Domain actions should own:

- create or reveal quotation for manual entry;
- save buyer-entered quotation terms;
- save vendor-entered quotation terms through portal token access;
- replace current line item draft set in a transaction;
- validate invitation, tenant, RFQ, vendor, and source constraints;
- compute completeness indicators;
- record audit events.

Controllers should adapt requests and return resources only.

## API Contract

Proposed authenticated endpoints:

- `GET /api/quotations/{quotation}`
  Shows quotation header, line items, attachments, source, completeness, and permissions.
- `PUT /api/quotations/{quotation}/manual-entry`
  Saves buyer/admin structured entry for the current quotation.

Proposed vendor portal endpoints:

- `GET /api/vendor-portal/rfq-invitations/{token}/quotation`
  Extends the upload response to include structured entry data.
- `PUT /api/vendor-portal/rfq-invitations/{token}/quotation/manual-entry`
  Saves vendor-entered structured response fields.

Responses should include:

- quotation identity and status;
- RFQ, invitation, and vendor summaries;
- header terms;
- quoted line items;
- attachments;
- completeness summary;
- permissions.

Validation errors should be field-specific and map cleanly to frontend forms. Request and response schemas must be generated through `@cognify/api-client`.

## Frontend Design

Buyer UI stays in `apps/web/features/sourcing`:

- RFQ invitation panel gains "Enter quotation" or "Edit quotation" action;
- structured entry form uses existing form patterns and validation summary;
- line item editor can start from RFQ line items and allow ad hoc lines;
- form shows save state, incomplete state, and API validation errors;
- quotation evidence files remain visible beside structured entry.

Vendor UI stays in `apps/web/features/vendor-portal`:

- portal response section includes structured fields below uploaded evidence;
- vendor sees only vendor-safe RFQ context and vendor-editable fields;
- invalid or terminal invitation states render read-only or unavailable states;
- file upload and manual entry share one quotation response summary.

The UI should remain dense and operational. It should not introduce a comparison dashboard or award-oriented scoring surface.

## Permissions And Tenancy

Buyer entry requires authenticated tenant access and quotation management permission for the RFQ invitation. Vendor entry derives authority from the portal token and cannot accept tenant headers as authority.

Field-level behavior:

- vendor can edit vendor-facing commercial fields;
- buyer can edit buyer-entered and cleanup fields;
- buyer notes are never exposed in vendor portal responses;
- vendor notes can be exposed to buyers.

Cross-tenant RFQ, quotation, vendor, line item, and attachment references must be rejected.

## Audit And Notifications

Audit events should include:

- `quotation.manual_entry_saved`
- `quotation.line_items_saved`
- `quotation.completeness_changed`

Metadata should include source (`buyer` or `vendor_portal`), quotation ID, RFQ ID, invitation ID, vendor ID, changed field groups, and actor context. Full before/after payloads should be kept concise to avoid storing excessive commercial data unless existing audit standards require it.

Notifications can be limited to buyer-facing "vendor submitted quotation details" if low risk.

## Testing Strategy

Backend tests:

- buyer can save structured quotation terms and line items;
- vendor can save structured terms through a valid portal token;
- vendor cannot edit buyer-only fields;
- invalid token and terminal invitation states cannot save;
- cross-tenant quotation, RFQ line item, and vendor references are rejected;
- validation errors are returned for malformed money, quantity, date, and line item data;
- audit events are recorded.

Frontend tests:

- buyer manual entry form loads current quotation data;
- buyer can save terms and line items;
- vendor portal can save vendor-editable terms;
- validation errors map to fields;
- terminal states render read-only controls;
- uploaded evidence remains visible beside manual entry.

Contract checks:

- update `apps/api/storage/openapi/openapi.json`;
- regenerate `packages/api-client/src/generated/**`;
- run `pnpm check:api-contract`.

## Exit Criteria

- Buyers can record structured quotation data for an RFQ invitation.
- Vendors can enter structured quotation data through the portal.
- Manual entry works with or without uploaded files.
- Data is tenant-scoped, invitation-scoped, and audit-visible.
- Uploaded evidence and structured fields appear together as one quotation response.
- The implementation leaves revision history and normalization for later slices.
