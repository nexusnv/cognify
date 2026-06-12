# Supplier Invoice Capture Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-12
- Release scope: P1 core procure-to-pay lifecycle, slice P1-42 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-42`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-11-delivery-fulfillment-tracking-design.md`
  - `docs/superpowers/specs/2026-06-11-receiving-goods-receipt-design.md`
  - `docs/superpowers/specs/2026-06-10-purchase-order-change-orders-design.md`
  - `docs/superpowers/specs/2026-06-10-purchase-order-issue-to-supplier-design.md`

## Roadmap Analysis

P1-42 asks Cognify to let AP users, buyers, or suppliers submit invoices against a purchase order. The roadmap scope explicitly includes invoice number, invoice date, due date, tax, freight, line details, attachments, and duplicate invoice checks.

The codebase already has the purchase-order workspace, issue-to-supplier flow, receiving, and fulfillment tracking. That gives us a stable PO-centric surface where invoice capture can live before the later invoice review, matching, exception, approval, and AP handoff slices.

This slice intentionally targets manual AP/buyer capture against an existing purchase order. Supplier-submitted invoice intake is real roadmap scope, but it is a separate workflow surface and would pull in portal/auth concerns that are not needed for the first usable invoice capture slice.

## Problem

After a purchase order is issued, Cognify still has no durable invoice record tied to that PO. Without invoice capture:
- AP cannot record supplier invoice metadata inside Cognify.
- Buyers cannot see which invoices have arrived for a PO.
- Later slices for invoice review, matching, exceptions, and approval have no base record to operate on.
- Duplicate invoice detection has no persisted invoice identity to compare against.
- The P2P record graph cannot connect PO, receiving, invoice, and payment readiness.

## Goals

- Add a durable supplier invoice record that references a purchase order and its lines.
- Allow AP users or buyers to capture an invoice against an existing purchase order from the PO workspace.
- Capture invoice number, invoice date, due date, tax, freight, notes, and line-level invoice details.
- Support attachments through the existing attachment domain so supporting files can be associated with the invoice record.
- Detect duplicate invoice attempts for the same tenant and supplier/PO context before persisting a duplicate record.
- Show invoice capture status and invoice summary inside the purchase-order workspace.
- Expose OpenAPI endpoints and consume them from `@cognify/api-client`.
- Record audit events for invoice capture.

## Non-Goals

- Supplier portal invoice submission.
- Invoice review workspace, queueing, triage, or approval routing.
- Two-way or three-way matching, discrepancy resolution, or payment handoff.
- Payment status tracking, credit memo handling, or budget encumbrance.
- OCR extraction, document parsing, or automated invoice normalization.
- ERP/accounting integrations.
- Multi-currency and formal tax rule standardization beyond storing the invoice values captured for this PO.

## Approaches Considered

### 1. Standalone AP invoice module

This would create a separate invoice workspace detached from purchase orders. It is flexible, but it forces AP users to search for the correct PO before capturing anything and makes the first slice less integrated with the current procurement flow.

### 2. Supplier portal invoice intake first

This would prioritize vendor-submitted invoices through an external portal. It matches one part of the roadmap text, but it adds invitation, auth, and portal UX work that is not necessary to satisfy the first operational invoice-capture slice.

### 3. PO-centric invoice capture panel

This is selected. Invoice capture is exposed inside the existing purchase-order workspace as a new panel. AP users can record the invoice directly against the PO they are already reviewing, and later invoice-review and matching slices can reuse the same invoice records.

## Workflow

### Actors

- AP user: captures an invoice against an issued or acknowledged purchase order.
- Buyer: can also capture an invoice when acting on behalf of AP or supplier operations.
- Admin: can capture invoices for tenant operations.
- System: validates duplicates, records audit events, and computes invoice summaries.

### Main Flow

1. User opens an issued, acknowledged, or change-pending purchase order.
2. The workspace shows a new `Supplier invoices` section.
3. User clicks `Capture invoice`.
4. User enters:
   - supplier invoice number
   - invoice date
   - due date
   - optional tax amount
   - optional freight amount
   - optional notes
   - line-level invoice quantities and unit prices mapped to purchase-order lines
5. User saves the invoice.
6. Server validates:
   - the PO is in an invoice-eligible state
   - invoice number is present
   - invoice date is valid
   - quantities and amounts are non-negative
   - at least one line is present
   - the referenced PO lines belong to the purchase order and tenant
   - no duplicate invoice exists for the same supplier/PO invoice number in the tenant
7. Server creates a durable invoice record and line records.
8. Attachments can be associated with the invoice record using the existing attachment domain.
9. Audit event `supplier_invoice.captured` is recorded.

### Invoice Capture Semantics

- The first slice treats capture as a persisted operational record, not as a draft that needs separate submission.
- Later invoice review and approval slices can introduce workflow states such as `review_pending`, `needs_attention`, `approved`, or `paid` without changing the core capture model.
- The capture screen should not try to solve matching or payment readiness. It should only record the invoice faithfully and expose it to later slices.

### Duplicate Invoice Checks

- Exact duplicate prevention is the minimum requirement.
- An invoice is considered a duplicate when the same tenant and supplier context already has an active invoice with the same invoice number for the same purchase order.
- If a duplicate is found, the server rejects the create request with a conflict-style error that includes the matching invoice reference.
- The UI should show a clear duplicate message instead of allowing a silent overwrite.

## Backend Design

### Data Model

New table `supplier_invoices`:

- `id` UUID primary key
- `tenant_id` foreign key
- `purchase_order_id` foreign key
- `number` tenant-scoped operational invoice number such as `INV-2026-000001`
- `status` string: `captured`, `cancelled`
- `invoice_number` string
- `invoice_date` date
- `due_date` nullable date
- `currency` char(3)
- `subtotal_amount` decimal(18,4)
- `tax_amount` decimal(18,4) default 0
- `freight_amount` decimal(18,4) default 0
- `total_amount` decimal(18,4)
- `notes` nullable text
- `captured_by_user_id` foreign key
- `captured_at` timestamp
- `lock_version` integer default 1
- timestamps

New table `supplier_invoice_lines`:

- `id` UUID primary key
- `tenant_id` foreign key
- `supplier_invoice_id` foreign key
- `purchase_order_line_id` foreign key
- `line_number` integer
- `description_snapshot` nullable string
- `quantity_invoiced` decimal(18,4)
- `unit_price` decimal(18,4)
- `line_subtotal` decimal(18,4)
- `notes` nullable text
- timestamps

### Domain Structure

```
apps/api/Domains/Invoice/
  States/
    SupplierInvoiceStatus.php
  Models/
    SupplierInvoice.php
    SupplierInvoiceLine.php
  Actions/
    CaptureSupplierInvoice.php
    UpdateSupplierInvoice.php (future slice boundary; not required for capture)
  Support/
    SupplierInvoiceNumber.php
    SupplierInvoiceDuplicateChecker.php
  Policies/
    SupplierInvoicePolicy.php
  Http/
    Controllers/
      SupplierInvoiceController.php
    Requests/
      CaptureSupplierInvoiceRequest.php
    Resources/
      SupplierInvoiceResource.php
      SupplierInvoiceLineResource.php
```

### Domain Behavior

**`CaptureSupplierInvoice`**

- Accepts a purchase order, actor, invoice metadata, and line items.
- Locks the purchase order and referenced PO lines.
- Validates that the purchase order is in an invoice-eligible state.
- Validates the invoice number is unique for the tenant and purchase-order supplier context.
- Creates a supplier invoice record with line records and audit metadata.
- Computes subtotal and total values from the captured lines and header amounts.
- Records `supplier_invoice.captured`.

**`SupplierInvoiceDuplicateChecker`**

- Encapsulates exact duplicate detection logic.
- Compares invoice number, tenant, and purchase-order supplier context.
- Returns a matching invoice reference for conflict responses.

**`SupplierInvoiceNumber`**

- Produces tenant-scoped invoice numbers using the existing year/sequence pattern.
- Uses `INV-{year}-{sequence}` or a comparable tenant-readable prefix.

### Purchase Order and Line Extensions

The purchase-order workspace needs capture-ready summary fields:

- `invoiceSummary.totalInvoiceCount`
- `invoiceSummary.latestInvoiceDate`
- `invoiceSummary.totalInvoicedAmount`
- `invoiceSummary.currency`

Purchase order lines do not need a new stored invoice state for this slice. The invoice records themselves are the durable source of truth, and later matching slices can derive line-level invoice coverage from those records.

## API Contract

Add authenticated tenant-scoped routes:

```txt
GET    /api/purchase-orders/{purchaseOrder}/supplier-invoices
POST   /api/purchase-orders/{purchaseOrder}/supplier-invoices
GET    /api/supplier-invoices/{supplierInvoice}
```

Request schema:

- `CaptureSupplierInvoiceRequest`
  - `lockVersion`: required integer
  - `invoiceNumber`: required string max 100
  - `invoiceDate`: required date
  - `dueDate`: nullable date
  - `taxAmount`: nullable decimal string >= 0
  - `freightAmount`: nullable decimal string >= 0
  - `notes`: nullable string max 2000
  - `lines[]`: array of
    - `purchaseOrderLineId`: required UUID
    - `quantityInvoiced`: required decimal > 0
    - `unitPrice`: required decimal >= 0
    - `notes`: nullable string max 2000

Response schemas:

- `SupplierInvoice`
  - `id`, `number`, `status`
  - `invoiceNumber`, `invoiceDate`, `dueDate`
  - `currency`, `subtotalAmount`, `taxAmount`, `freightAmount`, `totalAmount`
  - `notes`
  - `capturedByUserId`, `capturedAt`
  - `purchaseOrderId`
  - `lockVersion`
  - `lines: SupplierInvoiceLine[]`
- `SupplierInvoiceLine`
  - `id`, `lineNumber`, `purchaseOrderLineId`
  - `descriptionSnapshot`
  - `quantityInvoiced`, `unitPrice`, `lineSubtotal`
  - `notes`
- `PurchaseOrder.invoiceSummary`
  - `totalInvoiceCount`
  - `latestInvoiceDate`
  - `totalInvoicedAmount`
  - `currency`

Purchase-order resources and permission summaries should include a `canCaptureInvoice` flag so the workspace can gate the new panel without another permission lookup.

Duplicate conflict responses should surface a clear error payload that can be rendered in the invoice capture form.

## Web Design

### Workspace Placement

- Add a `Supplier invoices` section to the purchase-order workspace.
- Render it alongside the existing fulfillment and goods-receipt panels so AP users can move through operational PO follow-up in one place.
- Keep the panel compact and operational, not dashboard-like.

### Capture Form

- Header fields:
  - invoice number
  - invoice date
  - due date
  - tax amount
  - freight amount
  - notes
- Line fields:
  - per-PO-line invoice quantity
  - unit price
  - line notes
- The form should default invoice currency to the purchase order currency and not expose currency editing in this slice.
- The line editor should be PO-line keyed, not index keyed, so it remains stable if the purchase-order lines are reordered or edited elsewhere.

### Invoice List and Summary

- Show count, latest invoice date, and total invoiced amount.
- Render each captured invoice as a compact card with invoice number, invoice date, due date, total, and captured-by metadata.
- Surface duplicate conflict errors in the form and keep the already-entered values intact so the user can correct only the problematic field.

### Attachments

- Use the existing attachment domain and UI patterns so the invoice record can hold supporting files without inventing a new upload subsystem.
- If attachment upload cannot be done in the same create step, the capture flow should still expose the invoice record immediately and allow attachments through the shared attachment surface afterward.

## Testing And Verification

- Backend feature tests for invoice capture, duplicate rejection, tenant isolation, and lock-version conflicts.
- Contract regeneration and API client typecheck after OpenAPI changes.
- Web workflow tests for capture form validation, duplicate error handling, and invoice list rendering.
- Purchase-order workspace typecheck and focused Vitest coverage for the new panel.
- Visual inspection against the real local API-backed app because this adds a new operational workspace section.

## Open Questions

- Should invoice capture allow direct AP editing after creation, or should edits wait for the invoice review slice?
- Do we want exact duplicate prevention only, or should the first slice also warn on same supplier plus same date plus same total?
- Should attachments be uploaded during capture or attached immediately after creation through the shared attachment surface?
