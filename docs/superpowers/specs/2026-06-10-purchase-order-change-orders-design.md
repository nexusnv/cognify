# Purchase Order Change Orders Design

## Status

- Status: Accepted for implementation
- Date: 2026-06-10
- Release scope: P1 core procure-to-pay lifecycle, slice P1-39 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-39`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-09-purchase-order-creation-design.md`
  - `docs/superpowers/specs/2026-06-09-purchase-order-review-approval-design.md`
  - `docs/superpowers/specs/2026-06-10-purchase-order-issue-to-supplier-design.md`

## Roadmap Analysis

P1-39 asks Cognify to allow controlled purchase order revisions such as quantity changes, price changes, delivery date changes, cancellation, partial cancellation, and re-approval when policy requires it. P1-40 receiving and later invoice matching depend on a stable current commitment and a clear history of how that commitment changed. The change-order slice must therefore introduce line-level current commitment state before receiving starts.

The existing purchase order aggregate already supports draft creation, review and approval, supplier issue, supplier acknowledgement, and immutable supplier-facing issue snapshots. P1-38 intentionally left issued and acknowledged POs terminal until P1-39. This design opens those states through auditable change orders while preserving the original PO number and prior issued versions.

## Problem

After a purchase order has been issued, real procurement teams still need to revise commitments. Supplier constraints, buyer scope changes, delivery changes, tax corrections, partial cancellations, and price adjustments all happen after issue. If Cognify mutates issued POs directly, downstream receiving and invoice matching cannot prove which commitment was active at a point in time. If Cognify forces cancellation and recreation for every revision, it loses continuity across supplier acknowledgement, receipts, invoices, and budget commitments.

## Goals

- Add durable purchase order change orders as explicit child records of a purchase order.
- Support draft change orders against `issued` and `acknowledged` purchase orders.
- Support header changes for expected delivery date, delivery terms, payment terms, buyer note, finance note, and change reason.
- Support line changes for quantity, unit price, expected delivery date, delivery location, notes, and line cancellation.
- Preserve before and after snapshots for every changed field and line.
- Classify material changes. Quantity, price, totals, payment terms, delivery terms, and line cancellation require re-approval.
- Route material change orders through the existing Approval domain using the current purchase order approval policy and subject handler.
- Apply approved change orders to the current purchase order and lines in one transaction.
- Keep non-material delivery/note changes approvable by buyer/admin without approval routing.
- Generate a new supplier-facing version after an approved change order so the supplier issue history remains versioned.
- Add workspace UI for drafting, submitting, approving-state visibility, history, and current commitment comparison.
- Record audit events for draft creation/update, submit, approval routed, approved/applied, rejected, cancelled, and supplier-version supersession.
- Expose OpenAPI endpoints and consume generated API client endpoints from `@cognify/api-client`.

## Non-Goals

- Receiving, goods receipt, delivery tracking, invoice capture, matching, payment, budget encumbrance, or AP handoff.
- Vendor self-service change-order acknowledgement.
- Supplier email delivery, PDF templates, EDI, cXML, ERP sync, or accounting integration.
- Multi-change-order negotiation loops with supplier counteroffers.
- Split awards, vendor replacement, currency changes, or adding entirely new vendors to an existing PO.
- Changing source RFQ, award, quotation, or PO handoff records.
- Retroactively editing prior supplier issue snapshots.

## Approaches Considered

### 1. Directly Mutate Issued Purchase Orders

This is rejected. Direct mutation is fast to build but destroys the evidence chain between original issue, supplier acknowledgement, current commitment, later receipts, and invoice matching.

### 2. Cancel And Recreate Purchase Orders

This is rejected as the default path. It is appropriate for full cancellation, but not for routine quantity, price, and delivery changes. Recreate-only workflows break continuity and make later P2P reporting less credible.

### 3. Explicit Change Orders With Conditional Re-Approval

This is selected. A change order records requested changes, snapshots before and after values, and either applies immediately when non-material or routes through the shared Approval domain when material. The current PO remains the operational source of truth after approval, while the change-order history proves how it got there.

## Workflow

### Actors

- Buyer: drafts change orders, submits material changes, applies non-material changes, cancels draft change orders, and records full or partial PO cancellation.
- Admin: has the same operational permissions as buyer.
- Approver: reviews material change orders through the existing approval task queue.
- Supplier: receives revised supplier-facing versions outside Cognify in this slice.
- System: validates state, calculates deltas, routes approval when needed, applies approved changes, increments supplier-facing versions, and records audit events.

### Purchase Order States

Extend `PurchaseOrderStatus`:

- `change_pending`: a material change order is routed or awaiting approval; current commitment remains the last approved/current PO values.
- `cancelled`: can now also represent post-issue full cancellation through an approved or non-material cancellation change order.

Existing `issued` and `acknowledged` become change-order eligible. Draft, ready-for-review, in-review, changes-requested, rejected, approved, and cancelled purchase orders are not eligible for P1-39 change orders.

### Change Order States

Add `PurchaseOrderChangeOrderStatus`:

- `draft`: editable by buyer/admin; no commitment changed.
- `pending_approval`: material change order is routed through Approval; no commitment changed.
- `changes_requested`: approver requested corrections; buyer/admin can revise and resubmit.
- `approved`: approved and applied to the purchase order.
- `rejected`: rejected by approver; no commitment changed.
- `cancelled`: buyer/admin cancelled the draft or changes-requested change order.

Only one active change order may exist per purchase order at a time. Active means `draft`, `pending_approval`, or `changes_requested`.

### Main Flow

1. Buyer/admin opens an issued or acknowledged purchase order.
2. The workspace shows the current commitment, latest supplier version, and change-order history.
3. Buyer/admin starts a change order with reason and optional header/line changes.
4. The API locks the purchase order, validates state and lock version, and creates or updates a draft change order with:
   - original PO header snapshot
   - original line snapshots
   - proposed header and line values
   - calculated deltas
   - materiality flags
5. Buyer/admin submits the change order.
6. If the change order is non-material, the API applies it immediately, records `purchase_order.change_order.applied`, increments PO `lock_version`, and creates a new supplier version.
7. If the change order is material, the API routes the purchase order through the existing Approval domain and moves the PO to `change_pending`.
8. Approvers review the purchase order approval task. The task summary includes change-order metadata so approvers know it is a change-order review.
9. Approval outcome:
   - approved: apply the change order to PO header and lines, set PO status back to `issued` or `acknowledged`, increment supplier version, record audit.
   - rejected: mark change order rejected, set PO status back to the prior operational status, record audit.
   - changes requested: mark change order changes requested, keep PO `change_pending`, allow buyer/admin revision and resubmission.
10. Buyer/admin can export the revised supplier version using the existing supplier export endpoints after approval/application.

### Full And Partial Cancellation

Full cancellation is represented by a change order with `changeType = full_cancellation`. It requires a reason and is material. Once approved/applied, the PO status becomes `cancelled`, all open lines are marked cancelled, and later receiving/invoice work must treat it as terminal.

Partial cancellation is represented by line-level cancellation on selected lines or reducing line quantity. It is material because it changes commitment amount or fulfillment scope. Approved partial cancellation keeps the PO in its previous operational status and marks affected lines as cancelled or reduced.

### Materiality Rules

Material changes require re-approval:

- header `paymentTerms`
- header `deliveryTerms`
- any line `quantity`
- any line `unitPrice`
- any line cancellation
- any total amount increase or decrease
- full cancellation

Non-material changes apply immediately:

- header `expectedDeliveryDate`
- line `expectedDeliveryDate`
- line `deliveryLocation`
- buyer note
- finance note
- line notes

The implementation should calculate materiality server-side. The web app can display the classification but must not decide it.

## Backend Design

### Data Model

Add `purchase_order_change_orders`:

- `id` UUID primary key
- `tenant_id`
- `purchase_order_id`
- `approval_instance_id` nullable
- `number`, tenant-scoped sequence such as `PO-2026-000001-CO-001`
- `status`
- `change_type`: `amendment`, `partial_cancellation`, `full_cancellation`
- `from_purchase_order_status`
- `to_purchase_order_status` nullable until applied
- `reason`
- `material_change` boolean
- `requires_approval` boolean
- `requested_by_user_id`
- `requested_at`
- `submitted_by_user_id` nullable
- `submitted_at` nullable
- `approved_by_user_id` nullable
- `approved_at` nullable
- `rejected_by_user_id` nullable
- `rejected_at` nullable
- `rejected_reason` nullable
- `changes_requested_by_user_id` nullable
- `changes_requested_at` nullable
- `changes_requested_reason` nullable
- `cancelled_by_user_id` nullable
- `cancelled_at` nullable
- `cancelled_reason` nullable
- `before_snapshot` JSON
- `after_snapshot` JSON
- `delta_snapshot` JSON
- `supplier_version_number` nullable
- `lock_version` integer default 1

Add `purchase_order_change_order_lines`:

- `id` UUID primary key
- `tenant_id`
- `purchase_order_change_order_id`
- `purchase_order_line_id`
- `line_number`
- `change_action`: `update`, `cancel`
- before and after fields for quantity, unit price, subtotal, tax, freight, discount, total, expected delivery date, delivery location, and notes
- `delta_snapshot` JSON

Extend `purchase_order_lines`:

- `status`: `open`, `cancelled`
- `current_version_number` unsigned integer default `1`
- `cancelled_by_change_order_id` nullable
- `cancelled_at` nullable
- `cancelled_reason` nullable

Extend `purchase_orders`:

- `current_change_order_id` nullable
- `current_supplier_version_number` unsigned integer default `1`
- `change_order_count` unsigned integer default `0`

### Domain Actions

Add `CreateOrUpdatePurchaseOrderChangeOrder`:

- Locks the PO and active change order.
- Requires PO status `issued` or `acknowledged`, or `change_pending` only when revising a `changes_requested` active change order.
- Blocks creation if another active change order exists.
- Validates line IDs belong to the PO and tenant.
- Validates positive quantities and non-negative prices/amount components.
- Recalculates line totals and PO totals from proposed line values.
- Builds before, after, and delta snapshots.
- Calculates materiality.
- Records `purchase_order.change_order.drafted` or `purchase_order.change_order.updated`.

Add `SubmitPurchaseOrderChangeOrder`:

- Locks PO and change order.
- Requires draft or changes requested.
- Requires a non-empty reason and at least one effective change.
- If non-material, applies immediately.
- If material, sets change order `pending_approval`, sets PO `change_pending`, routes through `RouteSubjectForApproval`, and records `purchase_order.change_order.submitted`.

Add `ApplyPurchaseOrderChangeOrder`:

- Updates PO header fields, line fields, statuses, totals, `change_order_count`, `current_change_order_id`, `current_supplier_version_number`, and `lock_version`.
- For acknowledged POs, preserves acknowledgement metadata but marks the supplier issue as superseded by a newer supplier version.
- Builds a new `supplier_version` snapshot from the updated current PO.
- Marks change order approved/applied with approval actor and timestamps.
- Records `purchase_order.change_order.applied`.

Add `RejectPurchaseOrderChangeOrder`, `RequestPurchaseOrderChangeOrderChanges`, and `CancelPurchaseOrderChangeOrder`:

- Mirror existing PO approval outcome behavior.
- Restore PO status from `change_pending` to the prior operational status for rejected or cancelled material changes.
- Keep draft/correction history on the change order.

### Approval Integration

Reuse `PurchaseOrderApprovalSubjectHandler` rather than creating a new Approval subject type in this slice. The handler should detect `current_change_order_id` and `status = change_pending` and add change-order metadata to approval context and task summaries:

- change order number
- change type
- material change flag
- total delta
- reason
- changed fields

Approval policies still target `purchase_order`, which avoids creating duplicate PO approval policy administration for change orders.

When an approval task approves a `change_pending` PO, `MarkPurchaseOrderApproved` should apply the active change order instead of treating the purchase order as newly approved for supplier issue. Rejection and changes-requested should delegate to the active change-order outcome actions.

## API Contract

Add tenant-scoped authenticated routes:

```txt
GET  /api/purchase-orders/{purchaseOrder}/change-orders
POST /api/purchase-orders/{purchaseOrder}/change-orders
GET  /api/purchase-order-change-orders/{changeOrder}
PATCH /api/purchase-order-change-orders/{changeOrder}
POST /api/purchase-order-change-orders/{changeOrder}/submit
POST /api/purchase-order-change-orders/{changeOrder}/cancel
```

Request schemas:

- `SavePurchaseOrderChangeOrderRequest`
  - `lockVersion`
  - `reason`
  - `changeType`
  - optional header fields
  - `lines[]` with line ID, action, quantity, unit price, expected delivery date, delivery location, notes
- `SubmitPurchaseOrderChangeOrderRequest`
  - `lockVersion`
- `CancelPurchaseOrderChangeOrderRequest`
  - `lockVersion`
  - `reason`

Resource schemas:

- `PurchaseOrderChangeOrder`
- `PurchaseOrderChangeOrderLine`
- `PurchaseOrder.changeOrdersSummary`
- `PurchaseOrder.permissions.canCreateChangeOrder`
- `PurchaseOrder.permissions.canUpdateChangeOrder`
- `PurchaseOrder.permissions.canSubmitChangeOrder`
- `PurchaseOrder.permissions.canCancelChangeOrder`

## Web Design

Add a change-order panel to `apps/web/features/purchase-orders`:

- For issued/acknowledged POs, show a compact current commitment summary, latest supplier version, and primary action to create a change order.
- Draft editor uses the existing PO workspace density:
  - reason and change type
  - header fields for delivery/payment terms and dates
  - line table with editable quantity, unit price, expected delivery date, delivery location, notes, and cancel toggle
  - materiality summary calculated from API response
  - submit and cancel actions
- Pending approval state shows approval status in the existing approval panel plus a change-order delta summary.
- History table shows number, status, type, reason, material flag, total delta, submitted/approved/rejected/cancelled timestamps, and supplier version number.

Do not use mock fixtures in production components. Components consume generated-client hooks and local MSW handlers only in tests.

## Permissions

Buyer/admin can create, update, submit, and cancel draft or changes-requested change orders for tenant-scoped issued/acknowledged POs. Approver permissions remain governed by the Approval domain. Non-buyer/admin users cannot mutate change orders through purchase-order endpoints.

The resource computes permissions from policy checks plus backend status rules. Backend actions remain authoritative.

## Audit And Observability

Audit actions:

- `purchase_order.change_order.drafted`
- `purchase_order.change_order.updated`
- `purchase_order.change_order.submitted`
- `purchase_order.change_order.approval_routed`
- `purchase_order.change_order.changes_requested`
- `purchase_order.change_order.rejected`
- `purchase_order.change_order.cancelled`
- `purchase_order.change_order.applied`
- `purchase_order.supplier_version.superseded`

Audit metadata should include tenant ID, PO ID and number, change order ID and number, actor ID, from/to PO status, materiality, total delta, changed fields, affected line IDs, approval instance ID when present, and supplier version number when a new supplier version is created.

## Demo Data

Seed at least:

- issued PO with no change orders
- acknowledged PO with an approved material quantity/price change order
- issued PO with a pending approval change order
- issued PO with a non-material delivery-date change order already applied

These examples should exercise the list, workspace, approval queue, and supplier version summary.

## Testing And Verification

Backend tests:

- buyer/admin can draft and submit a non-material change order and it applies immediately
- material quantity/price/payment-term changes route for approval and leave current commitment unchanged until approval
- approval applies material change order and updates PO totals, lines, supplier version, and audit
- rejection and changes-requested restore or preserve correct PO/change-order states
- full cancellation and partial line cancellation are represented as change orders
- stale lock versions return conflict
- cross-tenant PO or line IDs are denied
- non-buyer/admin mutation is forbidden
- only one active change order can exist for a PO

Web tests:

- workspace shows change-order history and create action for issued/acknowledged POs
- draft editor displays materiality and total deltas
- non-material submit refreshes the current commitment
- material submit shows pending approval state
- history renders approved, pending, rejected, and cancelled states
- stale/conflict and validation errors surface in the panel

Verification commands:

```bash
php artisan test --filter=PurchaseOrderChangeOrderApiTest
php artisan test --filter=DemoSeederTest
pnpm generate:api
pnpm check:api-contract
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
pnpm --filter @cognify/web typecheck
git diff --check
```

Because this adds a visible PO workspace panel, desktop visual verification is required against the real local API-backed app. Mobile screenshots are intentionally skipped because mobile support was dropped from Cognify.

## Acceptance Criteria

- Issued and acknowledged POs can be revised only through change orders.
- Material changes require approval before current PO commitments mutate.
- Non-material changes apply immediately with audit and supplier-version tracking.
- Full and partial cancellation are represented as auditable change orders.
- Current PO line state is ready for P1-40 receiving and P1-42 invoice matching.
- OpenAPI and generated client expose change-order routes, schemas, and permissions.
- The PO workspace makes current commitment, active change order, and change history visible.
- Roadmap P1-39 can be marked Fully Implemented after implementation, review, PR, and merge.
