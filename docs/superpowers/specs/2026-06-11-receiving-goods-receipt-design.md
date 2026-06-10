# Receiving and Goods Receipt Design

## Status

- Status: Accepted for implementation
- Date: 2026-06-11
- Release scope: P1 core procure-to-pay lifecycle, slice P1-40 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-40`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-10-purchase-order-change-orders-design.md`
  - `docs/superpowers/specs/2026-06-10-purchase-order-issue-to-supplier-design.md`

## Roadmap Analysis

P1-40 asks Cognify to record goods receipt or service acceptance against purchase order lines with support for partial receipt, over/under receipt tolerance, receiving notes, attachments, requester confirmation, buyer confirmation, and audit events.

P1-39 change orders already established line-level `open`/`cancelled` status and current commitment tracking. P1-40 introduces a durable goods receipt record that references purchase order lines and captures what was actually delivered or performed. This record becomes the foundation for P1-41 fulfillment tracking, P1-44 invoice matching, P1-45 invoice exceptions, and P1-53 P2P record graph.

## Problem

After a purchase order has been issued, acknowledged, or revised through change orders, Cognify has no way to record that goods were received or services were performed. Without goods receipt:
- Invoice matching (P1-44) cannot compare received quantities against invoiced quantities.
- Fulfillment tracking (P1-41) cannot show delivery completeness.
- Budget encumbrance (P1-50) cannot relieve commitment when goods arrive.
- The P2P record graph (P1-53) has no receiving node.

## Goals

- Add `GoodsReceipt` and `GoodsReceiptLine` as durable records under a new `Receiving` domain.
- Support recording receipt against `issued`, `acknowledged`, or `change_pending` purchase order lines.
- Support partial receipt with line-level quantity received and quantity accepted/rejected.
- Support over-receipt and under-receipt with configurable tolerance percentages (server-calculated, UI-displayed).
- Support receiving notes per receipt and per line.
- Support file attachments on receipts via the existing Attachment domain.
- Support requester confirmation and buyer confirmation as distinct optional steps.
- Record audit events for receipt creation, requester confirmation, and buyer confirmation.
- Track received quantity and accepted quantity on purchase order lines for query and matching readiness.
- Expose OpenAPI endpoints and consume generated API client endpoints from `@cognify/api-client`.

## Non-Goals

- Delivery and fulfillment tracking dashboard, shipment status, late delivery alerts, backorder tracking (P1-41).
- Invoice capture, matching, exceptions, or payment readiness (P1-42 through P1-47).
- Budget commitment or encumbrance updates (P1-50).
- Credit memos, invoice adjustments (P1-49).
- EDI, ASN, barcode scanning, or warehouse integration.
- Automated three-way matching; this slice records receipt data for later matching by P1-44.
- Vendor-facing delivery notification or portal updates.
- Mobile receiving interface.

## Approaches Considered

### 1. Directly Update Purchase Order Lines With Received Quantity

This is rejected. Direct mutation would lose the receipt event, attachment relationships, audit trail, and the ability to track multiple partial receipts over time. Invoice matching (P1-44) needs individual receipt records to support exception workflows.

### 2. Single Goods Receipt Per Purchase Order

This is rejected for service/partial scenarios. Real procurement operations may receive goods in multiple shipments against the same PO. A single receipt record would force combining or overwriting.

### 3. Multiple Goods Receipts With Per-Line Detail

This is selected. Each receipt is an independent record referencing a purchase order with one or more receipt lines. This supports partial shipments, multiple receipts over time, and clear audit history. Purchase order lines track cumulative received and accepted quantities derived from receipt records.

## Workflow

### Actors

- Receiver: records goods receipt against purchase order lines. The receiver role maps to the existing `buyer` or a dedicated `receiver` role. For this slice, buyer and admin can record receipts.
- Requester: optionally confirms receipt, verifying that goods or services meet the request need.
- Buyer: optionally confirms or reviews receipt, reconciling any receiving notes.
- System: calculates tolerances, updates PO line received/accepted quantities, records audit events.

### Purchase Order Line Receiving States

Purchase order lines already have `status` (`open`, `cancelled`) from P1-39. Add receiving-derived state tracking:

- `open`: line not fully received, no cancellation.
- `partially_received`: some quantity received but not all.
- `fully_received`: ordered quantity fully received within tolerance.
- `over_received`: received quantity exceeds ordered quantity beyond tolerance.
- `cancelled`: line cancelled through change order.

The receiving state is computed from ordered quantity versus cumulatively received quantity, not stored directly.

### Goods Receipt States

- `draft`: receiver is entering receipt details; not yet recorded.
- `completed`: receipt is finalised with quantities, notes, and optional attachments.
- `requester_confirmed`: requester has confirmed or acknowledged the receipt.
- `buyer_confirmed`: buyer has reviewed and confirmed the receipt.

### Main Flow

1. Receiver opens an issued, acknowledged, or change-pending purchase order.
2. The workspace shows current line quantities, expected delivery dates, and a "Record receipt" action.
3. Receiver creates a goods receipt with:
   - receipt date (defaults to today)
   - optional receipt reference (e.g., delivery note number, D/O number)
   - optional notes
   - optional attachments (via existing Attachment domain)
4. For each line, receiver enters:
   - quantity received (required, positive decimal)
   - quantity accepted (defaults to quantity received; can be less if damaged/defective)
   - rejection reason if quantity accepted < quantity received
   - optional line notes
5. Server validates:
   - PO line is `open` or `partially_received` (not cancelled)
   - quantities are non-negative
   - received quantity minus cumulative previously received <= tolerance
   - tolerance is calculated as `ordered_quantity * (1 + over_receipt_tolerance_percent / 100)` with a default of 10%
6. Receipt is created with `completed` status.
7. PO line cumulative received/accepted quantities are updated.
8. Audit event `goods_receipt.recorded` is created.

### Over/Under Receipt Tolerance

- Default over-receipt tolerance: 10% of line quantity.
- Tolerance is calculated server-side and can be overridden per PO via a tolerance field.
- Under-receipt (receiving less than ordered) is always allowed.
- Over-receipt beyond tolerance is rejected with a clear error message.
- Tolerance is stored on the PO line for future reference.

### Requester And Buyer Confirmation

- After receipt is completed, the requester (user who created the linked requisition) can optionally confirm receipt.
- Buyer can also optionally confirm receipt after requester confirmation or independently.
- Confirmation records an audit event and updates receipt status.
- Confirmation is not required to proceed; it is an optional governance step.

## Backend Design

### Data Model

New table `goods_receipts`:

- `id` UUID primary key
- `tenant_id` foreign key
- `purchase_order_id` foreign key
- `number` tenant-scoped sequence such as `GR-2026-000001`
- `status` string: `completed`, `requester_confirmed`, `buyer_confirmed`
- `receipt_date` date
- `receipt_reference` nullable string (e.g., delivery order number)
- `notes` nullable text
- `recorded_by_user_id` foreign key
- `recorded_at` timestamp
- `requester_confirmed_by_user_id` nullable
- `requester_confirmed_at` nullable
- `buyer_confirmed_by_user_id` nullable
- `buyer_confirmed_at` nullable
- `lock_version` integer default 1
- timestamps

New table `goods_receipt_lines`:

- `id` UUID primary key
- `tenant_id` foreign key
- `goods_receipt_id` foreign key
- `purchase_order_line_id` foreign key
- `line_number` integer
- `quantity_ordered` decimal (18,4) – snapshot from PO line at time of receipt
- `quantity_received` decimal (18,4)
- `quantity_accepted` decimal (18,4)
- `rejection_reason` nullable text
- `notes` nullable text
- timestamps

Extend `purchase_order_lines`:

- `cumulative_quantity_received` decimal (18,4) default 0
- `cumulative_quantity_accepted` decimal (18,4) default 0
- `over_receipt_tolerance_percent` decimal (5,2) default 10.00
- `last_receipt_at` nullable timestamp

### Domain Actions

Add `Receiving` domain under `apps/api/Domains/Receiving/`:

**`RecordGoodsReceipt`**:

- Accepts PO ID, actor, receipt date, optional reference, optional notes, line receipts with quantity received/accepted.
- Locks PO and lines.
- Validates PO status in `['issued', 'acknowledged', 'change_pending']`.
- Validates each line is `open` or `partially_received`.
- Validates quantities and tolerance.
- Calculates cumulative received and accepted quantities.
- Creates `GoodsReceipt` and `GoodsReceiptLine` records.
- Updates PO line cumulative quantities and `last_receipt_at`.
- Records `goods_receipt.recorded` audit event.

**`ConfirmGoodsReceiptByRequester`**:

- Updates receipt status to `requester_confirmed`.
- Records `goods_receipt.requester_confirmed` audit event.

**`ConfirmGoodsReceiptByBuyer`**:

- Updates receipt status to `buyer_confirmed`.
- Records `goods_receipt.buyer_confirmed` audit event.

### Receiving Number Generator

`ReceivingNumber::nextFor(PurchaseOrder $purchaseOrder): string` that locks tenant receipt records for the PO and returns `GR-{year}-{sequence}`.

## API Contract

Add tenant-scoped authenticated routes under a new controller:

```txt
GET    /api/purchase-orders/{purchaseOrder}/goods-receipts
POST   /api/purchase-orders/{purchaseOrder}/goods-receipts
GET    /api/goods-receipts/{goodsReceipt}
POST   /api/goods-receipts/{goodsReceipt}/confirm-requester
POST   /api/goods-receipts/{goodsReceipt}/confirm-buyer
```

Request schemas:

- `RecordGoodsReceiptRequest`
  - `receiptDate`: required date
  - `receiptReference`: nullable string max 100
  - `notes`: nullable string max 2000
  - `lockVersion`: required integer
  - `lines[]`: array of
    - `purchaseOrderLineId`: required UUID
    - `quantityReceived`: required decimal > 0
    - `quantityAccepted`: required decimal >= 0
    - `rejectionReason`: nullable string max 1000
    - `notes`: nullable string max 2000

- `ConfirmGoodsReceiptRequest`
  - `lockVersion`: required integer

Resource schemas:

- `GoodsReceipt`
  - id, number, status, receiptDate, receiptReference, notes
  - recordedBy, recordedAt, requesterConfirmedBy, requesterConfirmedAt, buyerConfirmedBy, buyerConfirmedAt
  - lines: GoodsReceiptLine[]
  - purchaseOrderId
  - lockVersion

- `GoodsReceiptLine`
  - id, lineNumber, purchaseOrderLineId
  - quantityOrdered, quantityReceived, quantityAccepted
  - rejectionReason, notes

- `PurchaseOrder.receivingSummary`
  - totalReceiptCount
  - latestReceiptDate
  - lineSummaries with cumulative quantities

- `PurchaseOrderLine` extended with:
  - `cumulativeQuantityReceived`
  - `cumulativeQuantityAccepted`
  - `receivingStatus` (computed: open/partially_received/fully_received/over_received)
  - `overReceiptTolerancePercent`

## Web Design

Add a Receiving feature at `apps/web/features/receiving/`:

### Workspace Panel

Insert a goods-receipt panel into the PO workspace page, placed after supplier issue and change orders, before approval panel:

- Shows a list of completed receipts for the PO with number, date, line count, status, recorded-by info.
- Primary action "Record receipt" opens a form panel.
- Each receipt row expands to show line details.

### Record Receipt Form

- Receipt date picker (defaults today).
- Receipt reference text input.
- Notes textarea.
- Line-by-line entry with:
  - PO line description and ordered quantity (read-only labels).
  - Quantity received input (defaults to remaining quantity).
  - Quantity accepted input (defaults to same as received).
  - Rejection reason textarea (shown when accepted < received).
  - Line notes textarea.
- Tolerance warnings shown when approaching or exceeding tolerance.
- Submit button with loading state.
- Validation errors surface inline.

### Receipt List And Confirmation

- Completed receipts show "Confirm as requester" and "Confirm as buyer" actions based on user role and current status.
- Confirmation is a simple button with confirmation dialog.
- Cancel/delete of receipts is not in scope for this slice.

## Permissions

- Buyer and admin can record goods receipts for tenant-scoped purchase orders.
- The requester of the linked requisition can confirm receipt as requester.
- Buyer and admin can confirm receipt as buyer.
- Non-buyer/non-admin/non-requester users cannot record or confirm receipts.

New policy methods on a `GoodsReceiptPolicy` or within `PurchaseOrderPolicy`:
- `recordGoodsReceipt`: buyer/admin for issued/acknowledged/change-pending POs
- `confirmRequesterReceipt`: requester of linked requisition
- `confirmBuyerReceipt`: buyer/admin

## Audit And Observability

Audit actions:

- `goods_receipt.recorded`
- `goods_receipt.requester_confirmed`
- `goods_receipt.buyer_confirmed`

Audit metadata should include tenant ID, PO ID and number, receipt ID and number, actor ID, line count, total quantity received, and confirmation metadata when applicable.

## Demo Data

Seed at least:

- issued PO with one completed goods receipt (full quantity).
- issued PO with multiple partial receipts.
- issued PO with a receipt where some quantity was rejected.
- PO approaching over-receipt tolerance.
- PO with no receipts yet.

These examples should exercise the list, workspace, tolerance calculation, and confirmation flows.

## Testing And Verification

Backend tests:

- buyer/admin can record a goods receipt and PO line cumulative quantities update
- partial receipts accumulate correctly
- over-receipt beyond tolerance is rejected
- receipt against cancelled lines is rejected
- confirmation by requester and buyer updates status and records audit
- cross-tenant PO access is denied
- non-buyer/non-admin cannot record receipts
- stale lock versions return conflict

Web tests:

- PO workspace shows goods receipt section when PO is issued/acknowledged
- record receipt form validates input and submits
- receipt list renders completed receipts with line details
- confirmation actions work for authorised roles
- validation errors surface through role=alert

Verification commands:

```bash
php artisan test --filter=GoodsReceiptApiTest
php artisan test --filter=DemoSeederTest
pnpm generate:api
pnpm check:api-contract
pnpm --dir apps/web exec vitest run features/receiving/tests/receiving-workflow.test.tsx
pnpm --filter @cognify/web typecheck
git diff --check
```

Because this adds a visible PO workspace panel, desktop visual verification is required against the real local API-backed app.

## Acceptance Criteria

- Issued, acknowledged, and change-pending purchase orders can receive goods receipt records.
- Multiple partial receipts can be recorded for the same PO line, with cumulative quantity tracking.
- Over-receipt beyond configured tolerance is rejected with a clear error.
- Rejected quantities are tracked separately from accepted quantities.
- Requester and buyer confirmation are optional audit events.
- PO lines expose cumulative received/accepted quantities and computed receiving status for downstream matching and visibility.
- OpenAPI and generated client expose goods receipt routes and schemas.
- The PO workspace makes receipt history and record-receipt action available.
- Roadmap P1-40 can be marked Fully Implemented after implementation, review, PR, and merge.
