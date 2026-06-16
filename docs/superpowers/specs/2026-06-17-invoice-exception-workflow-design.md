# Invoice Exception Workflow Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-17
- Release scope: P1 core procure-to-pay lifecycle, slice P1-45 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-45`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-12-supplier-invoice-capture-design.md`
  - `docs/superpowers/specs/2026-06-13-invoice-review-workspace-design.md`
  - `docs/superpowers/specs/2026-06-17-two-way-three-way-matching-design.md`
  - `docs/superpowers/specs/2026-06-10-purchase-order-change-orders-design.md`
  - `docs/superpowers/specs/2026-06-11-receiving-goods-receipt-design.md`

## Roadmap Analysis

P1-45 asks Cognify to route invoice mismatches to the right owner: requester, buyer, receiver, finance, or vendor. It must capture resolution notes, adjusted values, supporting evidence, approval impact, and audit events.

The repository now has durable supplier invoice capture, AP review state, and invoice matching results. `SupplierInvoiceMatchResult` stores per-line and header-level match dimensions, while `SupplierInvoice.matching_status` distinguishes pending, matched, and mismatch states. That gives P1-45 a concrete source of truth for exception creation: failed match results are the exception candidates, not free-text review notes.

This slice turns matching failures into an operational workflow. It does not approve invoices, pay invoices, mutate PO commitments, or create credit memos. It creates a durable exception record for each unresolved mismatch, assigns the best available owner, captures a resolution proposal, records evidence, and marks whether the invoice can safely proceed to the later invoice approval slice.

## Problem

After P1-44 matching, AP users can see that an invoice has mismatches, but Cognify still lacks a governed path for deciding what to do about them. Without an invoice exception workflow:

- Mismatches remain AP queue rows instead of owned work items.
- Requesters, buyers, receivers, finance users, and suppliers cannot be routed based on the mismatch type.
- Resolution notes, proposed adjusted values, and evidence are scattered across comments, attachments, or external systems.
- Invoice approval has no reliable signal that exceptions are unresolved.
- Auditors cannot prove who accepted a variance or why an invoice was allowed to continue.
- Re-running matching can lose context about prior exception handling.

## Goals

- Create durable invoice exception records from failed `SupplierInvoiceMatchResult` rows.
- Keep one active exception per invoice, match dimension, level, invoice line, and PO line.
- Route exceptions to the most appropriate owner based on match dimension and available P2P context.
- Support internal owners (`requester`, `buyer`, `receiver`, `finance`) and an external `vendor` owner label without building a vendor portal.
- Capture resolution method, notes, adjusted values, evidence attachments, and approval impact.
- Add exception statuses that distinguish open, assigned, awaiting evidence, resolved, accepted, rejected, and cancelled exceptions.
- Add invoice-level exception summaries and matching states so P1-46 can block unresolved exceptions.
- Allow AP/buyer/admin users to assign, request evidence, accept, reject, and cancel exceptions.
- Allow assigned internal owners to submit resolution proposals for their exceptions.
- Reconcile exceptions when matching is re-run: update still-failing exceptions, cancel superseded exceptions, and preserve accepted history.
- Record audit events and in-app notifications for exception lifecycle transitions.
- Expose OpenAPI endpoints and consume generated `@cognify/api-client` endpoints and schemas.
- Add seeded demo data with requester, buyer, receiver, finance, vendor, and accepted-exception scenarios.

## Non-Goals

- Invoice approval through the shared Approval domain. That remains P1-46.
- Payment readiness, AP export, payment status, credit memo, debit memo, or invoice reversal. Those remain P1-47 and P1-49.
- Direct PO line mutation. PO commitment changes must go through the P1-39 purchase order change-order workflow.
- Direct invoice amount mutation. Invoice corrections remain explicit invoice update or credit memo work after this slice.
- Vendor portal authentication, supplier login, or supplier self-service exception actions.
- Email delivery, supplier acknowledgement tracking, or automated supplier follow-up.
- Configurable owner routing policies. P1-45 uses deterministic server-side routing with manual reassignment.
- A dedicated finance role. This slice uses the `finance` owner label mapped to approver users or buyer/admin fallback until finance roles are introduced.

## Approaches Considered

### 1. Free-Text Review Notes On Supplier Invoice

This would add exception notes to `SupplierInvoice.review_notes` or `review_blockers`. It is rejected because matching exceptions need durable ownership, evidence, adjusted values, approval impact, and per-dimension history. Review notes are invoice-level and cannot represent multiple open mismatch owners cleanly.

### 2. Approval Tasks For Every Mismatch

This would route each mismatch through the shared Approval domain. It is rejected for P1-45 because most exceptions are not approval decisions. They are operational clarification, receipt confirmation, supplier evidence, or correction tasks. P1-46 will later introduce invoice approval; P1-45 should prepare exception context for that approval without overloading approval tasks.

### 3. Durable Exception Records With Owner Routing

This is selected. Each failed match result becomes a `SupplierInvoiceException` with owner, status, resolution metadata, evidence, and approval impact. The workflow is invoice-domain-owned, integrates with existing attachments and notifications, and gives P1-46 a clear precondition: unresolved exceptions block invoice approval.

## Workflow

### Actors

- AP user: AP/buyer/admin operator who creates, assigns, reassigns, accepts, rejects, and cancels exceptions. This slice maps AP capability to existing buyer/admin permissions.
- Buyer: owns commercial mismatches, vendor identity questions, and PO context.
- Requester: confirms business need, scope, or receipt of services when the exception is tied to their requisition or PO.
- Receiver: confirms goods or service receipt when the exception is tied to quantity, receipt, or acceptance.
- Finance: reviews tax, freight, invoice total, payment readiness, and amount-adjustment impact. This slice represents finance as approver users or buyer/admin fallback until a dedicated role exists.
- Vendor: external supplier owner that must provide evidence, credit note, corrected invoice, or acknowledgement. Vendor owner is recorded as contact metadata, not a logged-in user.
- System: creates or updates exceptions from match results, computes owner routing, calculates approval impact, reconciles exceptions after re-run, and records audit events.

### Exception Source

Exceptions are created from failed `SupplierInvoiceMatchResult` rows after invoice matching runs. Header-level failed results create header-level exceptions with null line IDs. Line-level failed results create line-level exceptions with `supplier_invoice_line_id` and `purchase_order_line_id`.

A manual create action is available for AP/buyer/admin users when a user sees a failed match result that was not automatically converted because of an older match run. Manual creation requires a failed match result reference or enough dimension context to create the same stable exception key.

### Stable Exception Identity

The system should prevent duplicate active exceptions by deriving a stable key from:

- `supplier_invoice_id`
- `match_type`
- `match_level`
- `dimension`
- `supplier_invoice_line_id` nullable
- `purchase_order_line_id` nullable

When matching is re-run, the physical `SupplierInvoiceMatchResult.id` changes because P1-44 deletes and recreates results. The exception key must not depend on the match result ID. Re-runs update the latest failed match result reference and exception summary while preserving owner, status, and resolution history unless the exception is superseded.

### Owner Routing Matrix

Owner routing is server-side and deterministic. AP/buyer/admin users can reassign exceptions when the default owner is stale or unavailable.

| Failed dimension | Match level | Default owner | Owner fallback | Rationale |
| --- | --- | --- | --- | --- |
| `vendor_identity` | header | buyer | admin | Buyer owns supplier/PO relationship validation. |
| `unit_price` | line | buyer | admin | Buyer owns commercial terms against the PO. |
| `line_total` | line | buyer | finance | Buyer validates scope/line context; finance validates amount impact. |
| `invoice_total` | header | finance | buyer | Header total variance is primarily payment readiness risk. |
| `tax` | header or line | finance | buyer | Tax variance affects payable amount and compliance. |
| `freight` | header or line | finance | buyer | Freight is a payable charge and often supplier evidence. |
| `quantity` two-way | line | buyer | requester | Buyer validates ordered quantity and commitment context. |
| `quantity` three-way | line | receiver | requester | Receiver validates actual goods or service acceptance. |
| Missing PO line | line | buyer | admin | Buyer validates invoice-to-PO mapping. |
| Any dimension requiring supplier proof | line or header | vendor | buyer | Vendor must provide corrected invoice, credit note, or explanation. |

Internal owner resolution:

- `requester`: `purchase_order.requisition.requester_id`.
- `buyer`: `purchase_order.created_by_user_id`, then any tenant buyer, then admin.
- `receiver`: latest goods receipt `recorded_by_user_id`, then `requester_confirmed_by_user_id`, then requester, then buyer.
- `finance`: tenant users with `approver` role, then buyer/admin fallback.

External vendor owner resolution:

- `owner_type = vendor`
- `owner_user_id = null`
- `vendor_contact_name` from purchase order supplier contact or vendor primary contact.
- `vendor_contact_email` from purchase order supplier contact or vendor contact.
- No vendor portal route is exposed in this slice. Buyer/AP records supplier input on the vendor's behalf.

### Main Flow

1. Invoice reaches `reviewed` status and matching runs through the existing P1-44 matching action.
2. Matching produces failed `SupplierInvoiceMatchResult` rows and sets `matching_status = mismatch`.
3. `CreateOrUpdateSupplierInvoiceExceptionsFromMatchResults` creates or updates one exception per failed result.
4. Each exception receives default owner, status `open`, dimension summary, approval impact, and audit event.
5. The system records an in-app notification for the assigned owner and AP/buyer/admin watchers.
6. AP/buyer/admin opens the invoice workspace and sees the exception panel below matching results.
7. AP/buyer/admin can assign, reassign, request evidence, or move an exception to `awaiting_evidence`.
8. Assigned internal owner submits a resolution with method, notes, adjusted values, and optional evidence.
9. AP/buyer/admin reviews the resolution:
   - accept: exception becomes `accepted`; invoice exception status can become `resolved` if all exceptions are accepted or cancelled.
   - reject: exception becomes `rejected`; AP/buyer/admin can reassign or request more evidence.
10. If matching is re-run and a previously failed dimension passes, open exceptions for that key become `cancelled` with reason `match_result_passed`.
11. If matching is re-run and a failed dimension still fails, the existing exception is updated with the latest result and remains active.
12. Invoice approval (P1-46) must block invoices with any exception that is not `accepted` or `cancelled`.

### State Model

Extend `SupplierInvoiceStatus` matching concerns:

| Invoice review status | Matching status | Exception status | Meaning |
| --- | --- | --- | --- |
| `reviewed` | `pending` | none | Reviewed, matching not yet attempted. |
| `reviewed` | `matched` | none | Reviewed and all match dimensions pass. |
| `reviewed` | `mismatch` | none | Matching failed but exceptions have not been generated yet. |
| `reviewed` | `exception_pending` | one or more active exceptions | Matching failed and one or more exceptions require action. |
| `reviewed` | `resolved` | all exceptions accepted or cancelled | Matching still has historical failures, but all exceptions are resolved. |
| `needs_information` | any | preserved | Invoice returned for review information; matching is not actionable until `reviewed`. |

Allowed matching transitions:

| From | Action | To |
| --- | --- | --- |
| `mismatch` | create exceptions | `exception_pending` |
| `exception_pending` | all exceptions accepted/cancelled | `resolved` |
| `resolved` | new failed match result | `exception_pending` |
| `exception_pending` | re-run produces all pass | `matched` |
| `matched` | re-run produces failure | `exception_pending` |

Exception states:

| State | Meaning | Who can transition |
| --- | --- | --- |
| `open` | Exception exists and needs an owner or first action. | AP/buyer/admin |
| `assigned` | Owner is assigned and expected to respond. | AP/buyer/admin |
| `awaiting_evidence` | Owner/AP requested evidence, supplier input, or receipt confirmation. | AP/buyer/admin |
| `resolved` | Owner submitted a resolution for acceptance. | Assigned owner or AP/buyer/admin |
| `accepted` | AP/buyer/admin accepted the resolution. | AP/buyer/admin |
| `rejected` | Resolution was rejected and needs rework. | AP/buyer/admin |
| `cancelled` | Exception is no longer relevant because matching passed, duplicate was removed, or AP/buyer/admin cancelled it. | System or AP/buyer/admin |

Active exception means any state except `accepted` and `cancelled`.

### Resolution Methods

Each resolution uses one fixed method:

| Method | Meaning | Required fields |
| --- | --- | --- |
| `accepted_variance` | Business accepts the mismatch without changing invoice or PO values. | resolution note |
| `invoice_correction_required` | Captured invoice values should be corrected before approval. | adjusted value fields, resolution note |
| `po_change_order_required` | PO commitment must change before invoice can be approved. | adjusted value fields, PO change-order note |
| `receipt_confirmation_required` | Receiver/requester confirms actual receipt or service acceptance. | confirmation note, optional adjusted quantity |
| `vendor_credit_required` | Supplier must issue credit note, corrected invoice, or explanation. | requested vendor action, due date optional |
| `vendor_identity_verified` | Vendor identity mismatch was caused by mapping or naming issue. | resolution note |
| `other` | Other documented resolution. | resolution note |

Resolution values are proposed values, not committed values. P1-45 records them for approval impact and audit context. Actual invoice correction, PO change order, or credit memo mutation remains downstream scope.

### Approval Impact

`approval_impact` is a JSON array computed from dimension and resolution method:

| Impact key | Meaning |
| --- | --- |
| `none` | Exception can be accepted without changing invoice or PO facts. |
| `invoice_amount_adjustment` | Invoice amount, tax, freight, or line total may need correction before approval. |
| `po_change_order_required` | PO quantity, price, delivery, or commitment value must change through P1-39. |
| `receipt_confirmation_required` | Receiver/requester confirmation is required before approval. |
| `vendor_evidence_required` | Supplier evidence, corrected invoice, or credit note is required. |
| `vendor_identity_review` | Vendor identity must be verified before approval. |

P1-46 invoice approval must read `approval_impact` and block approval when any active exception has impact other than `none`, or when any exception is not `accepted` or `cancelled`.

## Backend Design

### Domain Ownership

The owning backend domain remains `apps/api/Domains/Invoice`.

Supporting domains:

- `Domains/PurchaseOrder`: source of PO, PO line, vendor, requisition, and PO creator context.
- `Domains/Receiving`: source of receiver and receipt confirmation context.
- `Domains/Requisition`: source of requester context.
- `Domains/Attachment`: evidence attachments for exceptions.
- `app/Audit`: audit recording.
- `app/Notifications`: owner assignment and resolution notifications.
- `app/Tenancy`: tenant resolution and membership enforcement.

### Data Model

Add table `supplier_invoice_exceptions`:

```
id                              UUID PK
tenant_id                       FK to tenants
supplier_invoice_id             FK to supplier_invoices
supplier_invoice_match_result_id FK to supplier_invoice_match_results nullable
purchase_order_id               FK to purchase_orders
supplier_invoice_line_id        FK to supplier_invoice_lines nullable
purchase_order_line_id          FK to purchase_order_lines nullable
number                          tenant-scoped sequence such as SI-2026-000001-EX-001
match_type                      ENUM: two_way, three_way
match_level                     ENUM: header, line
dimension                       ENUM: vendor_identity, quantity, unit_price, line_total, tax, freight, invoice_total
status                          ENUM: open, assigned, awaiting_evidence, resolved, accepted, rejected, cancelled
owner_type                      ENUM: requester, buyer, receiver, finance, vendor
owner_user_id                   FK to users nullable
owner_vendor_contact_name       nullable string
owner_vendor_contact_email      nullable string
priority                        ENUM: low, normal, high nullable
approval_impact                 JSON nullable
resolution_method               ENUM nullable
resolution_note                 TEXT nullable
resolution_snapshot             JSON nullable
accepted_by_user_id             FK to users nullable
accepted_at                     TIMESTAMP nullable
rejected_by_user_id             FK to users nullable
rejected_at                     TIMESTAMP nullable
rejected_reason                 TEXT nullable
cancelled_by_user_id            FK to users nullable
cancelled_at                    TIMESTAMP nullable
cancelled_reason                TEXT nullable
created_by_user_id              FK to users nullable
created_at                      TIMESTAMP
updated_at                      TIMESTAMP
```

Add table `supplier_invoice_exception_updates`:

```
id                         UUID PK
tenant_id                  FK to tenants
supplier_invoice_exception_id FK to supplier_invoice_exceptions
actor_user_id              FK to users nullable
action                     ENUM: created, assigned, evidence_requested, resolution_submitted, accepted, rejected, cancelled
from_status                ENUM nullable
to_status                  ENUM nullable
note                       TEXT nullable
resolution_snapshot        JSON nullable
attachment_ids             JSON nullable
created_at                 TIMESTAMP
```

Add indexes on:

- `tenant_id`, `supplier_invoice_id`, `status`
- `tenant_id`, `owner_type`, `owner_user_id`, `status`
- `tenant_id`, `supplier_invoice_id`, `match_type`, `match_level`, `dimension`, `supplier_invoice_line_id`, `purchase_order_line_id`
- `tenant_id`, `approval_impact` JSON where supported
- `tenant_id`, `created_at`

Extend `supplier_invoices`:

```
exception_status              VARCHAR nullable — none, pending, resolved
open_exception_count          UNSIGNED INTEGER default 0
accepted_exception_count      UNSIGNED INTEGER default 0
exception_summary             JSON nullable
```

Exception summary JSON:

```
{
  "open": 2,
  "assigned": 1,
  "awaitingEvidence": 0,
  "resolved": 1,
  "accepted": 0,
  "rejected": 0,
  "cancelled": 0,
  "dimensionsWithIssues": ["unit_price", "quantity"],
  "approvalImpacts": ["invoice_amount_adjustment", "receipt_confirmation_required"]
}
```

`SupplierInvoiceException` keeps its existing morphMany attachment relationship through the attachment domain. No separate evidence table is needed for this slice.

### Domain Structure

```
apps/api/Domains/Invoice/
  Actions/
    CreateOrUpdateSupplierInvoiceExceptions.php
    AssignSupplierInvoiceExceptionOwner.php
    RequestSupplierInvoiceExceptionEvidence.php
    SubmitSupplierInvoiceExceptionResolution.php
    AcceptSupplierInvoiceExceptionResolution.php
    RejectSupplierInvoiceExceptionResolution.php
    CancelSupplierInvoiceException.php
  Data/
    SupplierInvoiceExceptionOwnerData.php
    SupplierInvoiceExceptionResolutionData.php
    SupplierInvoiceExceptionSummaryData.php
  Http/
    Controllers/
      SupplierInvoiceExceptionController.php
    Requests/
      AssignSupplierInvoiceExceptionOwnerRequest.php
      RequestSupplierInvoiceExceptionEvidenceRequest.php
      SubmitSupplierInvoiceExceptionResolutionRequest.php
      AcceptSupplierInvoiceExceptionResolutionRequest.php
      RejectSupplierInvoiceExceptionResolutionRequest.php
      CancelSupplierInvoiceExceptionRequest.php
    Resources/
      SupplierInvoiceExceptionResource.php
      SupplierInvoiceExceptionUpdateResource.php
      SupplierInvoiceExceptionSummaryResource.php
  Models/
    SupplierInvoiceException.php
    SupplierInvoiceExceptionUpdate.php
  Policies/
    SupplierInvoiceExceptionPolicy.php
  Services/
    SupplierInvoiceExceptionOwnerResolver.php
    SupplierInvoiceExceptionApprovalImpactResolver.php
```

Use only the files the implementation needs. Empty folders should not be created.

### Domain Behavior

**`CreateOrUpdateSupplierInvoiceExceptionsFromMatchResults`:**

- Runs inside the existing matching transaction after failed results are persisted.
- Loads failed and passed match results for the invoice.
- Groups failed results by stable exception key.
- Creates new exceptions for failed results with no existing active exception.
- Updates existing active exceptions with latest match result reference, dimension summary, and approval impact.
- Cancels active exceptions whose stable key no longer has a failed result.
- Recomputes invoice `exception_status`, counts, and summary.
- Sets invoice `matching_status`:
  - `matched` when no failed results and no active exceptions.
  - `exception_pending` when failed results or active exceptions exist.
  - `resolved` when historical failures exist but all exceptions are accepted or cancelled.
- Increments `supplier_invoices.lock_version`.
- Records `supplier_invoice.exceptions_reconciled`.

**`AssignSupplierInvoiceExceptionOwner`:**

- Requires buyer/admin permission and tenant scope.
- Allows `open`, `assigned`, `awaiting_evidence`, or `rejected`.
- Validates owner type and owner user/contact.
- Updates owner fields and status `assigned` unless already `awaiting_evidence`.
- Records update and audit event.
- Sends owner assignment notification.

**`RequestSupplierInvoiceExceptionEvidence`:**

- Requires buyer/admin permission.
- Allows `assigned` or `awaiting_evidence`.
- Stores note and optional due date in `resolution_snapshot`.
- Sets status `awaiting_evidence`.
- Records update, audit event, and notification.

**`SubmitSupplierInvoiceExceptionResolution`:**

- Allows assigned owner or buyer/admin.
- Allows `assigned` or `awaiting_evidence`.
- Requires method-specific fields.
- Stores resolution method, note, adjusted values, evidence attachment IDs, and approval impact snapshot.
- Sets status `resolved`.
- Records update, audit event, and notification to AP/buyer/admin.

**`AcceptSupplierInvoiceExceptionResolution`:**

- Requires buyer/admin permission.
- Allows `resolved`.
- Requires `lockVersion`.
- Sets status `accepted`, stores acceptor and timestamp, recomputes invoice exception summary.
- Records audit event.

**`RejectSupplierInvoiceExceptionResolution`:**

- Requires buyer/admin permission.
- Allows `resolved`.
- Requires rejection reason.
- Sets status `rejected`, stores rejection metadata, recomputes invoice exception summary.
- Records audit event and notification to previous owner.

**`CancelSupplierInvoiceException`:**

- Requires buyer/admin permission or system trigger.
- Allows any non-`accepted` state.
- Requires cancellation reason for manual cancellation.
- Sets status `cancelled`, recomputes invoice exception summary.
- Records audit event.

### Authorization

View exceptions:

1. Authenticated Sanctum session.
2. Active tenant context.
3. Invoice belongs to current tenant.
4. Current tenant role is buyer, approver, or admin; or the user is the assigned owner; or the user is the requester on the linked requisition.

Mutate exceptions:

- Assign, request evidence, accept, reject, cancel: buyer/admin only.
- Submit resolution: assigned owner, buyer/admin, or AP-equivalent buyer/admin.
- Reassign away from self: buyer/admin only.
- Vendor owner cannot submit directly in this slice; buyer/admin records supplier input on the vendor owner's behalf.

### Audit Metadata

Audit events include:

- `supplier_invoice.exception_created`
- `supplier_invoice.exception_assigned`
- `supplier_invoice.exception_evidence_requested`
- `supplier_invoice.exception_resolution_submitted`
- `supplier_invoice.exception_resolution_accepted`
- `supplier_invoice.exception_resolution_rejected`
- `supplier_invoice.exception_cancelled`
- `supplier_invoice.exceptions_reconciled`

Each event includes invoice id and number, exception id and number, owner type, owner user or vendor contact, dimension, match level, match type, status transition, approval impacts, and attachment count.

## API Contract

Add authenticated tenant-scoped routes:

```txt
GET    /api/supplier-invoices/{supplierInvoice}/exceptions
POST   /api/supplier-invoices/{supplierInvoice}/exceptions
POST   /api/supplier-invoice-exceptions/{supplierInvoiceException}/assign
POST   /api/supplier-invoice-exceptions/{supplierInvoiceException}/request-evidence
POST   /api/supplier-invoice-exceptions/{supplierInvoiceException}/resolution
POST   /api/supplier-invoice-exceptions/{supplierInvoiceException}/accept
POST   /api/supplier-invoice-exceptions/{supplierInvoiceException}/reject
POST   /api/supplier-invoice-exceptions/{supplierInvoiceException}/cancel
GET    /api/supplier-invoice-exceptions/{supplierInvoiceException}/updates
GET    /api/supplier-invoice-exceptions/{supplierInvoiceException}/attachments
POST   /api/supplier-invoice-exceptions/{supplierInvoiceException}/attachments
```

Request schemas:

- `AssignSupplierInvoiceExceptionOwnerRequest`
  - `lockVersion: number`
  - `ownerType: requester | buyer | receiver | finance | vendor`
  - `ownerUserId?: string | null`
  - `vendorContactName?: string | null`
  - `vendorContactEmail?: string | null`
- `RequestSupplierInvoiceExceptionEvidenceRequest`
  - `lockVersion: number`
  - `note: string`
  - `dueAt?: string | null`
- `SubmitSupplierInvoiceExceptionResolutionRequest`
  - `lockVersion: number`
  - `method: accepted_variance | invoice_correction_required | po_change_order_required | receipt_confirmation_required | vendor_credit_required | vendor_identity_verified | other`
  - `note: string`
  - `adjustedValues?: { quantity?: string; unitPrice?: string; lineSubtotal?: string; taxAmount?: string; freightAmount?: string; totalAmount?: string }`
  - `attachmentIds?: string[]`
- `AcceptSupplierInvoiceExceptionResolutionRequest`
  - `lockVersion: number`
- `RejectSupplierInvoiceExceptionResolutionRequest`
  - `lockVersion: number`
  - `reason: string`
- `CancelSupplierInvoiceExceptionRequest`
  - `lockVersion: number`
  - `reason: string`

Response summaries:

- `GET /api/supplier-invoices/{supplierInvoice}/exceptions` returns:
  - `data`: exception list ordered by line number, match level, dimension.
  - `summary`: invoice exception summary.
  - `permissions`: canAssign, canRequestEvidence, canSubmitResolution, canAccept, canReject, canCancel.

- `POST /api/supplier-invoices/{supplierInvoice}/exceptions` creates an exception from a failed match result reference:
  - `matchResultId: string`
  - `ownerType?: requester | buyer | receiver | finance | vendor`
  - `ownerUserId?: string | null`
  - `vendorContactName?: string | null`
  - `vendorContactEmail?: string | null`

### Extended SupplierInvoice fields

Add to existing `SupplierInvoice` resource:

```json
{
  "exceptionStatus": "pending",
  "openExceptionCount": 2,
  "acceptedExceptionCount": 0,
  "exceptionSummary": {
    "open": 1,
    "assigned": 1,
    "awaitingEvidence": 0,
    "resolved": 0,
    "accepted": 0,
    "rejected": 0,
    "cancelled": 0,
    "dimensionsWithIssues": ["unit_price", "quantity"],
    "approvalImpacts": ["invoice_amount_adjustment"]
  }
}
```

### Extended queue filters

`GET /api/supplier-invoices` additions:

- `exceptionStatus`: `none`, `pending`, `resolved`
- `exceptionOwnerType`: `requester`, `buyer`, `receiver`, `finance`, `vendor`
- `hasOpenException`: boolean shorthand
- `approvalImpact`: one of `invoice_amount_adjustment`, `po_change_order_required`, `receipt_confirmation_required`, `vendor_evidence_required`, `vendor_identity_review`

## Frontend Design

### Routes

No new top-level route. Exception UX lives in:

- Existing AP invoice queue under `(workspace)/accounts-payable/invoices/`.
- Existing invoice detail view and review panel.

### Feature Structure

```txt
apps/web/features/accounts-payable/
  api/
    accounts-payable-exceptions-api.ts
  components/
    invoice-exception-status-badge.tsx
    invoice-exception-panel.tsx
    invoice-exception-list.tsx
    invoice-exception-detail.tsx
    invoice-exception-resolution-form.tsx
    invoice-exception-approval-impact-badge.tsx
  hooks/
    use-invoice-exceptions.ts
    use-invoice-exception-actions.ts
  tables/
    accounts-payable-invoice-queue-table.tsx
```

### Invoice Exception Panel

Added below the matching results panel in the invoice review panel.

- Header: "Invoice Exceptions" with exception status badge.
- Summary: open exception count, accepted count, approval impacts.
- List: one row per exception with line, dimension, owner, status, impact, evidence count, and last update.
- Detail drawer or inline expandable section:
  - match expected value, actual value, tolerance, notes
  - owner and notification status
  - resolution method and note
  - adjusted values
  - supporting evidence attachments
  - timeline of exception updates
- Actions:
  - AP/buyer/admin: assign owner, request evidence, accept, reject, cancel.
  - Assigned owner: submit resolution.
  - Vendor owner: no direct action; AP/buyer/admin records supplier input.

### AP Queue Extensions

- New column: `Exceptions` — open count and highest approval impact.
- New column: `Owner` — requester, buyer, receiver, finance, or vendor.
- New filter tabs: `All`, `Open exceptions`, `My exceptions`, `Resolved`, `Approval impact`.
- Queue summary includes open exception count alongside matching counts.

### States

- Loading: skeleton rows while exceptions load.
- Empty: "No invoice exceptions. Matching issues can be routed here after matching runs."
- Error: API error alert with retry action.
- Pending mutation: disabled action buttons and inline loading state.
- Conflict: stale `lockVersion` message with refresh guidance.

## Seed and Demo Data

Demo data should include at least:

- Reviewed invoice with one buyer-owned unit price exception.
- Reviewed invoice with one receiver-owned three-way quantity exception.
- Reviewed invoice with one finance-owned tax or freight exception.
- Reviewed invoice with one requester-owned quantity exception.
- Reviewed invoice with one vendor-owned exception requiring supplier evidence.
- Reviewed invoice with all exceptions accepted and `matchingStatus = resolved`.
- Reviewed invoice with a rejected resolution requiring rework.
- Reviewed invoice where re-running matching cancels a prior exception.

Seeded exceptions should use realistic notes, adjusted values, approval impacts, and attachment references where attachment fixtures are available.

## Testing and Verification

### API Tests

Add focused feature tests for:

- Failed match results create one exception per stable exception key.
- Header-level failed match results create header-level exceptions with null line IDs.
- Line-level failed match results include invoice line and PO line context.
- Re-running matching updates existing active exceptions instead of duplicating them.
- Re-running matching cancels exceptions for dimensions that now pass.
- Accepted exceptions remain historical when matching is re-run and still fails.
- Owner routing selects requester from linked requisition.
- Owner routing selects buyer from PO creator or tenant buyer fallback.
- Owner routing selects receiver from latest goods receipt recorder.
- Owner routing selects finance as approver users or buyer/admin fallback.
- Owner routing selects vendor contact metadata for supplier evidence exceptions.
- AP/buyer/admin can assign, request evidence, accept, reject, and cancel exceptions.
- Assigned internal owner can submit a resolution.
- Non-owner requester cannot submit another user's exception.
- Cross-tenant exception access is denied.
- Stale `lockVersion` returns conflict.
- Method-specific required fields are validated.
- `po_change_order_required` resolution records approval impact without mutating PO lines.
- `invoice_correction_required` resolution records adjusted values without mutating invoice totals.
- `accepted_variance` can be accepted without adjusted values.
- Exception acceptance recomputes invoice `exception_status`.
- Invoice with active exceptions reports `exceptionStatus = pending`.
- Invoice with all exceptions accepted or cancelled reports `exceptionStatus = resolved`.
- Audit events are recorded for create, assign, evidence request, resolution, accept, reject, cancel, and reconcile.
- Notifications are recorded for assignment and resolution submitted.
- Queue filters work for open exceptions, owner type, and approval impact.

### Web Tests

Add tests for:

- Exception panel shows open, assigned, awaiting evidence, resolved, accepted, rejected, and cancelled states.
- AP/buyer/admin action buttons are visible for exception lifecycle actions.
- Assigned owner can submit resolution.
- Non-owner cannot submit resolution.
- Vendor-owned exceptions show supplier contact metadata and no direct vendor action.
- Approval impact badges render for invoice amount, PO change order, receipt confirmation, vendor evidence, and vendor identity review.
- Queue table shows exception count and owner.
- Queue filters update query parameters.
- Stale lock conflict displays refresh guidance.
- Evidence attachments are listed in exception detail.

### Contract and Local Verification

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web typecheck
cd apps/api && php artisan test --filter=InvoiceException
```

## Future Evolution

- P1-46 can use `exceptionStatus` and `approvalImpact` as invoice approval preconditions.
- P1-47 can block payment readiness until exceptions are accepted.
- P1-49 can link vendor credit notes or corrected invoices to accepted `vendor_credit_required` exceptions.
- P1-39 can deep-link from `po_change_order_required` exceptions to a draft purchase order change order.
- A future finance role can replace the current approver-user finance fallback.
- A future vendor portal can let vendor owners submit evidence directly instead of having AP/buyer record it manually.
- Configurable owner routing policies can replace deterministic routing once tenant policy administration is broader.

## Exit Criteria

- Failed match results create durable invoice exceptions.
- Exceptions have stable identity across matching re-runs.
- Exceptions route to requester, buyer, receiver, finance, or vendor owner metadata.
- Exceptions capture resolution method, notes, adjusted values, evidence attachments, and approval impact.
- AP/buyer/admin can assign, request evidence, accept, reject, and cancel exceptions.
- Assigned internal owners can submit resolution proposals.
- Invoice queue and detail UI show exception status, owner, count, and approval impact.
- Invoice approval preconditions can distinguish unresolved from resolved exceptions.
- Audit events and notifications cover the exception lifecycle.
- OpenAPI schemas, generated API client endpoints, MSW fixtures, and seeded demo data exist.
- No PO, invoice, credit memo, payment, or vendor portal mutation is introduced in this slice.
