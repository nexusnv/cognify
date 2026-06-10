# Purchase Order Issue To Supplier Design

## Status

- Status: Accepted for implementation
- Date: 2026-06-10
- Release scope: P1 core procure-to-pay lifecycle, slice P1-38 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-38`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-26-purchase-order-request-handoff-design.md`
  - `docs/superpowers/specs/2026-06-09-purchase-order-creation-design.md`
  - `docs/superpowers/specs/2026-06-09-purchase-order-review-approval-design.md`

## Roadmap Analysis

`P1-38 Purchase Order Issue to Supplier` is the next unfinished P1 feature after P1-37. The current purchase order aggregate can be created from a ready/exported handoff, routed through approval, and marked `approved`. That `approved` state is intentionally the gate for supplier issue.

The roadmap asks Cognify to send or expose the approved purchase order to the supplier through a vendor portal, email, or export, and to track issue date, supplier acknowledgement, supplier-facing version, and audit history. The smallest complete enterprise slice is to record official issue through a supplier-facing export package and manual acknowledgement. Real email delivery and vendor portal PO access remain downstream extensions because they require external delivery infrastructure and supplier authentication work that would widen this slice beyond the next PO lifecycle transition.

## Problem

Cognify can now approve purchase orders, but an approved PO is not yet a commitment that has been sent to the supplier. Teams need an auditable point where the internal PO becomes supplier-facing: the buyer confirms the final supplier-facing version, exports or sends it through their current channel, records who issued it, and later records whether the supplier acknowledged it. Without this boundary, receiving and invoice workflows cannot tell whether a PO was actually issued.

## Goals

- Allow buyer/admin users to issue only `approved` purchase orders.
- Persist supplier issue metadata on the purchase order: issued status, issue method, issued by, issued at, supplier contact name/email, message, supplier-facing version snapshot, and last export metadata.
- Generate a stable supplier-facing PO payload for JSON and CSV download from the issued snapshot.
- Allow buyer/admin users to record supplier acknowledgement with acknowledged by/contact/reference/note and timestamp.
- Add explicit PO states `issued` and `acknowledged` so later receiving and invoice workflows can depend on issued commitments.
- Add workspace UI that makes issue readiness, issue history, export/download, and acknowledgement status visible.
- Record audit events for issue, export, and acknowledgement.
- Expose OpenAPI endpoints and consume generated API client endpoints from `@cognify/api-client`.
- Preserve tenant isolation, buyer/admin authorization, lock-version conflict handling, and backend-owned state transitions.

## Non-Goals

- Sending email from Cognify or handling bounced/delivered email events.
- Vendor portal authentication or supplier self-service PO pages.
- Supplier-side acceptance/rejection workflows beyond buyer/admin recording acknowledgement.
- Purchase order change orders, revision supersession, cancellation after issue, or re-approval after issue. These belong to P1-39.
- Receiving, delivery tracking, invoice capture, matching, budget encumbrance, or payment readiness.
- PDF generation, branded print templates, e-signature, EDI, cXML, ERP sync, or accounting integration.
- Configurable tenant-specific issue methods beyond the fixed method enum in this slice.

## Approaches Considered

### 1. Email Issue First

This would make Cognify send the PO to the supplier and track delivery events. It is deferred because the repository does not yet have production email delivery workflows for supplier-facing procurement documents, and delivery semantics would require retry, bounce, recipient, and template decisions. It would delay the core PO lifecycle state that downstream P2P features need.

### 2. Vendor Portal PO Exposure First

This would expose issued POs through the existing vendor portal style. It is deferred because current vendor portal routes are RFQ-invitation-token based, not vendor-account based. A supplier PO portal needs a durable access model, token expiry, supplier contacts, and acknowledgement actions. Those are valuable, but they are larger than the first issue-to-supplier slice.

### 3. Recorded Issue With Supplier-Facing Export And Manual Acknowledgement

This is the selected approach. It creates the durable operational boundary: an approved PO becomes officially issued, Cognify stores the exact supplier-facing snapshot, users can export that snapshot, and acknowledgement can be recorded. This keeps the slice thin while making later email, portal, receiving, invoice, and change-order work build on real state instead of a placeholder.

## Workflow

### Actors

- Buyer: issues approved purchase orders, downloads supplier-facing payloads, sends them through the organization's current channel, and records supplier acknowledgement.
- Admin: has the same abilities as buyer for tenant operations.
- Finance/procurement approver: completes approval before issue but does not issue unless also a buyer/admin by tenant role.
- Supplier: receives the PO outside Cognify in this slice and can acknowledge through email, phone, or other external channel that buyer/admin records.
- System: validates state, snapshots supplier-facing PO content, records issue/export/acknowledgement audit events, and locks issued commercial facts.

### States

Extend `PurchaseOrderStatus`:

- `approved`: approval is complete; eligible for supplier issue.
- `issued`: supplier-facing PO has been officially issued but acknowledgement has not been recorded.
- `acknowledged`: supplier acknowledgement has been recorded.

State rules:

- Only `approved` can move to `issued`.
- Only `issued` can move to `acknowledged`.
- `issued` and `acknowledged` are terminal for this slice until P1-39 introduces change orders and post-issue controls.
- `draft`, `ready_for_review`, `in_review`, `changes_requested`, `rejected`, and `cancelled` cannot be issued.
- Draft update, cancel, and approval submission remain unavailable after approval/issue as they are today.

### Main Flow

1. Buyer/admin opens an `approved` purchase order.
2. The workspace shows an issue panel with supplier, contact, commercial totals, delivery terms, payment terms, and a warning that issuing freezes the supplier-facing version.
3. Buyer/admin records issue with:
   - `lockVersion`
   - `method`: `manual_email`, `portal_upload`, `external_system`, or `manual_export`
   - optional supplier contact name and email
   - optional issue message/note
4. The API locks the PO, validates tenant, role, `approved` status, lock version, line presence, vendor, totals, payment terms, delivery terms, and shipping details.
5. The API builds a supplier-facing version snapshot from the current PO header, vendor, lines, source links, approval metadata, and issue metadata.
6. The API sets `status = issued`, stores issue metadata and snapshot, increments `lock_version`, and records `purchase_order.issued`.
7. Buyer/admin can download JSON or CSV generated from the stored snapshot. Downloads can be preview-only or recorded exports; recorded exports update `last_supplier_exported_*` metadata and record `purchase_order.supplier_exported`.
8. When the supplier acknowledges outside Cognify, buyer/admin records acknowledgement with contact/reference/note and `lockVersion`.
9. The API moves the PO to `acknowledged`, stores acknowledgement metadata, increments `lock_version`, and records `purchase_order.acknowledged`.

### Failure Paths

- Wrong state: return `409` with a state-specific message.
- Stale lock version: return `409`.
- Missing supplier-facing required fields: return `409` with specific field names.
- Cross-tenant PO or vendor relation: deny through tenant-scoped route lookup and model guards.
- Non-buyer/admin issue or acknowledgement: return `403`.
- Export before issue: return `409`; supplier exports must come from the stored issue snapshot.
- Acknowledgement without supplier contact/reference/note: return validation error requiring at least one acknowledgement evidence field.

## Backend Design

### Purchase Order Columns

Add a migration extending `purchase_orders`:

- `issued_by_user_id` nullable foreign key to users
- `issued_at` nullable timestamp
- `issue_method` nullable string
- `supplier_contact_name` nullable string
- `supplier_contact_email` nullable string
- `issue_message` nullable text
- `supplier_version` nullable JSON
- `supplier_version_number` unsigned integer default `0`
- `last_supplier_exported_by_user_id` nullable foreign key to users
- `last_supplier_exported_at` nullable timestamp
- `last_supplier_export_format` nullable string
- `acknowledged_by_user_id` nullable foreign key to users
- `acknowledged_at` nullable timestamp
- `acknowledged_contact_name` nullable string
- `acknowledgement_reference` nullable string
- `acknowledgement_note` nullable text

Add indexes on:

- `tenant_id`, `status`, `issued_at`
- `tenant_id`, `vendor_id`, `issued_at`
- `tenant_id`, `acknowledged_at`

### Domain Actions

Add `IssuePurchaseOrderToSupplier`:

- Locks the PO row by tenant.
- Requires `approved`.
- Asserts lock version.
- Validates required supplier-facing fields:
  - at least one line
  - vendor id/name
  - currency and total
  - payment terms
  - delivery terms
  - shipping name or shipping address
- Builds `supplier_version` from the current PO and loaded lines.
- Sets `status = issued`, issue metadata, `supplier_version_number = 1`, and increments `lock_version`.
- Records `purchase_order.issued`.

Add `ExportIssuedPurchaseOrder`:

- Requires `issued` or `acknowledged`.
- Generates JSON or CSV from `supplier_version`, not live mutable fields.
- Supports `recordExport = false` for safe preview/download and `recordExport = true` for official export evidence.
- Records `purchase_order.supplier_exported` only for recorded exports.

Add `AcknowledgeIssuedPurchaseOrder`:

- Locks the PO row by tenant.
- Requires `issued`.
- Asserts lock version.
- Requires at least one acknowledgement evidence field from contact name, reference, or note.
- Sets `status = acknowledged`, acknowledgement metadata, and increments `lock_version`.
- Records `purchase_order.acknowledged`.

### Policy

Extend `PurchaseOrderPolicy`:

- `issueToSupplier`: buyer/admin, tenant-scoped, and PO is `approved`.
- `exportSupplierVersion`: buyer/admin, tenant-scoped, and PO is `issued` or `acknowledged`.
- `acknowledgeSupplier`: buyer/admin, tenant-scoped, and PO is `issued`.

The resource should still compute final permissions by combining policy checks with backend status rules.

## API Contract

Add tenant-scoped authenticated routes:

```txt
POST /api/purchase-orders/{purchaseOrder}/issue
GET  /api/purchase-orders/{purchaseOrder}/supplier-export.json
POST /api/purchase-orders/{purchaseOrder}/supplier-export.json
GET  /api/purchase-orders/{purchaseOrder}/supplier-export.csv
POST /api/purchase-orders/{purchaseOrder}/supplier-export.csv
POST /api/purchase-orders/{purchaseOrder}/acknowledge
```

Request schemas:

- `IssuePurchaseOrderRequest`
  - `lockVersion: number`
  - `method: manual_email | portal_upload | external_system | manual_export`
  - `supplierContactName?: string | null`
  - `supplierContactEmail?: string | null`
  - `message?: string | null`
- `AcknowledgePurchaseOrderRequest`
  - `lockVersion: number`
  - `acknowledgedContactName?: string | null`
  - `acknowledgementReference?: string | null`
  - `acknowledgementNote?: string | null`

Schema updates:

- `PurchaseOrderStatus`: add `issued`, `acknowledged`.
- `PurchaseOrder`: add `supplierIssue` object and permissions:
  - `canIssueToSupplier`
  - `canExportSupplierVersion`
  - `canAcknowledgeSupplier`

The web must consume generated endpoints and schemas from `@cognify/api-client`.

## Supplier Version Snapshot

The supplier-facing version is immutable for P1-38 once issued. It should contain:

- PO id, number, status at issue, version number, issued at, issue method.
- Supplier contact name/email and issue message.
- Vendor id/name and source vendor snapshot.
- Currency, subtotal, tax, freight, discount, total.
- Requested PO date and expected delivery date.
- Billing and shipping names/addresses.
- Delivery attention, payment terms, delivery terms.
- Lines with line id, line number, description, item code/SKU/category where available, quantity, unit, unit price, taxes/freight/discount, line total, currency, delivery metadata, and notes.
- Source links to handoff, RFQ, recommendation, requisition, project, quotation, and quotation version.
- Approval metadata including approval instance id and approved at/by when present.

Exports must be generated from this snapshot to prove what was actually issued, even if future change-order slices later create revised versions.

## Web Design

Use `apps/web/features/purchase-orders`.

Workspace changes:

- Add a `Supplier issue` panel after approval status and before draft fields.
- For `approved`, show the issue form:
  - issue method selector
  - supplier contact name/email
  - message/note
  - `Issue to supplier` action
- For `issued`, show issued facts and export buttons for JSON/CSV, plus acknowledgement form.
- For `acknowledged`, show issued facts, latest export facts, acknowledgement facts, and export buttons.
- For pre-approval states, show a compact disabled panel explaining that supplier issue unlocks after approval.
- Show inline conflict/validation errors.
- Keep the existing draft fields disabled after approval/issue.

Purchase order list changes:

- Include `issued` and `acknowledged` status rendering through existing status badge behavior.
- Existing status filter should accept generated enum values after OpenAPI regeneration.

No shared UI primitives are needed. This is Cognify-specific workflow UI and must remain in `apps/web`.

## Demo Data

Extend the demo procurement lifecycle seed data with:

- One `approved` PO ready to issue.
- One `issued` PO with supplier version snapshot and last export metadata.
- One `acknowledged` PO with acknowledgement metadata.

This supports local real-API visual verification and future receiving/invoice demos.

## Audit And Permissions

Audit actions:

- `purchase_order.issued`
- `purchase_order.supplier_exported`
- `purchase_order.acknowledged`

Audit metadata should include issue method, export format, supplier contact/email when provided, acknowledgement reference, and supplier version number. Do not include full supplier version line payload in audit metadata; it already lives on the PO and can be inspected through authorized record access.

Permissions:

- Buyer/admin can issue, export, and acknowledge within the active tenant.
- Other tenant roles cannot issue or acknowledge in this slice.
- Supplier users do not get API access in P1-38.

## Tests And Verification

Backend tests:

- Buyer/admin can issue an approved PO and the supplier version snapshot is persisted.
- Issue requires `approved` status and rejects draft, ready, in-review, changes-requested, rejected, cancelled, issued, and acknowledged.
- Issue requires lock version and returns conflict on stale version.
- Issue rejects missing required supplier-facing fields.
- Cross-tenant issue/export/acknowledgement is denied.
- JSON and CSV supplier exports are generated from the stored snapshot.
- Recorded export updates last export metadata and writes audit.
- Buyer/admin can acknowledge an issued PO with evidence.
- Acknowledgement requires `issued` status, lock version, and at least one evidence field.
- Audit events are recorded for issue, export, and acknowledgement.

Web tests:

- Approved PO shows issue form and calls generated issue endpoint.
- Issued PO shows export controls and acknowledgement form.
- Acknowledged PO shows acknowledgement facts and hides acknowledgement submit.
- Conflict and validation errors render inline.
- Pre-approval states explain that issue is blocked until approval.

Verification commands:

```bash
php artisan test --filter=PurchaseOrderIssueToSupplierApiTest
php artisan test --filter=DemoSeederTest
pnpm generate:api
pnpm check:api-contract
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
pnpm --filter @cognify/web typecheck
git diff --check
```

Because this slice adds visible PO workflow states and controls, visual inspection against the real API-backed app is required before PR completion. Capture desktop and mobile screenshots for approved, issued, and acknowledged POs.

## Rollout And Roadmap Update

When implementation, verification, visual inspection, CodeRabbit review, PR review, and merge are complete, update `docs/01-product/feature-roadmap.md` row `P1-38` to `Fully Implemented` with this spec path, implementation plan path, PR number, and a concise note that supplier issue is implemented as audited issue/export/acknowledgement state, with real email and portal delivery left for later integration slices.
