# Delivery and Fulfillment Tracking Design

## Status

- Status: Accepted for implementation
- Date: 2026-06-11
- Release scope: P1 core procure-to-pay lifecycle, slice P1-41 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-41`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-11-receiving-goods-receipt-design.md`
  - `docs/superpowers/specs/2026-06-10-purchase-order-change-orders-design.md`

## Roadmap Analysis

P1-41 asks Cognify to track expected delivery, shipment or fulfillment status, late deliveries, backorders, and receipt readiness. This connects procurement commitments to actual supplier performance and downstream invoice matching.

P1-40 goods receipt already records what was actually delivered. P1-41 adds the upstream tracking layer: what did the supplier ship, when, via which carrier, what is still backordered, and is the delivery on time or delayed. Together, P1-40 and P1-41 give procurement a complete picture from supplier dispatch through warehouse receipt.

## Problem

After a purchase order is issued and goods receipt records exist, Cognify has no way to:
- Track whether the supplier actually shipped the expected goods on time.
- Record carrier, tracking reference, and estimated arrival information.
- Detect late deliveries or compute delivery performance.
- Track backordered quantities and their expected availability.
- Provide a consolidated fulfillment status view that connects expected delivery dates, shipment events, and goods receipt records.

Without fulfillment tracking:
- Buyers must track shipments outside Cognify (email, spreadsheets, carrier portals).
- Late delivery detection is manual or non-existent.
- Invoice matching (P1-44) lacks shipment-level context for exception analysis.
- Supplier performance tracking (P2-28) has no baseline delivery data.
- The P2P record graph (P1-53) misses the fulfillment/shipment node.

## Goals

- Add `Shipment`, `ShipmentLine`, and `FulfillmentTrackingEvent` models under a new `Fulfillment` domain.
- Support recording shipments against `issued`, `acknowledged`, or `change_pending` purchase orders.
- Track carrier name, tracking reference, shipment date, and estimated arrival per shipment.
- Track per-line quantities shipped, delivered, and backordered.
- Record tracking events (status changes along the delivery journey).
- Compute delivery status per PO and per PO line from shipment data, goods receipt data, and expected delivery dates.
- Detect late deliveries (expected delivery date passed, goods not fully received).
- Surface fulfillment status in the PO workspace as a new panel.
- Expose OpenAPI endpoints and consume generated API client endpoints from `@cognify/api-client`.

## Non-Goals

- Supplier-facing shipment creation or portal updates (supplier portal scope is P1-25 baseline).
- EDI, ASN, or automated carrier integration.
- Barcode scanning, RFID, or warehouse system integration.
- Automated three-way matching; this slice records fulfillment data for later matching by P1-44.
- Mobile fulfillment interface.
- Supplier performance scoring or analytics (P2-28).
- Automated late-delivery notifications (P0-10 notification baseline extension).

## Approaches Considered

### 1. Computed Status Only (Rejected)

Compute delivery status purely from existing PO expected delivery dates and goods receipt records without adding new models or fields. This is rejected because it provides no mechanism to capture shipment-level detail (carrier, tracking, backorders) that procurement teams need for daily operations. The lack of shipment records makes it impossible to distinguish "supplier hasn't shipped yet" from "shipment is in transit."

### 2. New Fulfillment Domain With Shipment Lifecycle (Selected)

Create a dedicated `Fulfillment` domain with `Shipment`, `ShipmentLine`, and `FulfillmentTrackingEvent` models. This provides proper shipment lifecycle tracking with supplier dispatch info, carrier details, tracking events, and backorder management. The fulfillment status is a computed view over shipments, goods receipts, and expected dates. This is the right model for a real enterprise P2P system where procurement needs to track what suppliers are doing, not just what was received.

### 3. Extend PurchaseOrder With Fulfillment Fields (Rejected)

Add carrier/tracking fields directly to the PurchaseOrder model and backorder fields to PurchaseOrderLine. This avoids a new domain but mixes shipment logistics with the PO record. In real operations, a PO can have multiple shipments, so shipping data should be a separate child collection, not flattened onto the PO.

## Workflow

### Actors

- Buyer: creates and manages shipments, records tracking events, manages backorders.
- Admin: same as buyer for tenant shipments.
- Requester: read-only access to fulfillment status for their requisition's PO.
- System: computes delivery status, detects late deliveries, records audit events.

### Shipment Statuses

- `pending`: Shipment record created, no dispatch info yet.
- `confirmed`: Supplier confirmed shipment with carrier and tracking.
- `in_transit`: Shipment is in transit.
- `partially_delivered`: Some (but not all) shipment lines have been received.
- `delivered`: All shipment lines fully received.
- `delayed`: Shipment is past estimated arrival without full delivery.
- `cancelled`: Shipment cancelled by buyer or supplier.

### Delivery Statuses (Computed Per PO / Per PO Line)

These are computed from expected delivery dates, shipment data, and goods receipt cumulative quantities:

- `pending_shipment`: No shipments recorded, expected delivery date is in the future.
- `awaiting_delivery`: At least one shipment is confirmed or in_transit, no goods receipt yet.
- `partial`: Some lines or quantities have been received, but not all.
- `delivered`: All lines are fully received (cumulative quantity received >= ordered quantity).
- `delayed`: Expected delivery date has passed and goods are not fully received.
- `backordered`: Any line has backorder_quantity > 0.
- `overdue`: Expected delivery date passed by more than 7 days (configurable threshold) without full delivery.

### Main Flow: Recording a Shipment

1. Buyer opens an issued, acknowledged, or change-pending purchase order.
2. The fulfillment panel shows existing shipments and an overall delivery status.
3. Buyer clicks "Record shipment" to create a new shipment.
4. Buyer enters:
   - carrier name (optional, free text)
   - tracking reference (optional)
   - shipment date (defaults to today)
   - estimated arrival date (optional)
   - optional notes
5. For each PO line, buyer enters:
   - quantity shipped (required, positive decimal)
   - optional backorder quantity and expected date
   - optional line notes
6. System creates the `Shipment` with `confirmed` status and `ShipmentLine` records.
7. Audit event `fulfillment.shipment.recorded` is created.

### Main Flow: Recording Tracking Events

1. Buyer opens an existing shipment.
2. The tracking timeline shows all recorded events.
3. Buyer clicks "Add tracking event" and enters:
   - status (from enum: `shipped`, `in_transit`, `arrived`, `customs`, `out_for_delivery`, `delivered`, `delayed`, `exception`)
   - occurrence date/time (defaults to now)
   - optional location
   - optional notes
4. The event is added to the shipment's tracking timeline.
5. If the event status is `delivered`, the shipment status is updated to `delivered` (line-level delivery is still handled by P1-40 goods receipt).
6. Audit event `fulfillment.shipment.tracking_event` is created.

### Main Flow: Backorder Management

1. Buyer opens an existing shipment line that has backorder_quantity > 0.
2. Buyer can update backorder quantity and expected availability date as supplier provides updates.
3. Changes are recorded with audit events.

### Late Delivery Detection

The system compares expected delivery dates (from PO or PO line) against:
- Shipment estimated arrival dates
- Actual goods receipt dates (from P1-40)

A PO line is flagged as `delayed` when:
- `expected_delivery_date` has passed
- `cumulative_quantity_received` < `quantity` (from P1-40)

A shipment is flagged as `delayed` when:
- `estimated_arrival_date` has passed
- Not all shipment lines have corresponding goods receipts completing them

## Backend Design

### Data Model

New table `shipments`:

- `id` UUID primary key
- `tenant_id` foreign key
- `purchase_order_id` foreign key
- `number` string, tenant-scoped sequence `SH-{year}-{sequence}`
- `status` string: `pending`, `confirmed`, `in_transit`, `partially_delivered`, `delivered`, `delayed`, `cancelled`
- `carrier_name` nullable string max 200
- `tracking_reference` nullable string max 200
- `shipment_date` date
- `estimated_arrival_date` nullable date
- `actual_delivery_date` nullable date
- `notes` nullable text
- `created_by_user_id` foreign key
- `lock_version` integer default 1
- timestamps

New table `shipment_lines`:

- `id` UUID primary key
- `tenant_id` foreign key
- `shipment_id` foreign key
- `purchase_order_line_id` foreign key
- `line_number` integer
- `quantity_shipped` decimal(18,4)
- `quantity_delivered` decimal(18,4) default 0
- `backorder_quantity` decimal(18,4) default 0
- `backorder_expected_at` nullable date
- `notes` nullable text
- timestamps

New table `fulfillment_tracking_events`:

- `id` UUID primary key
- `tenant_id` foreign key
- `shipment_id` foreign key
- `status` string: `shipped`, `in_transit`, `arrived`, `customs`, `out_for_delivery`, `delivered`, `delayed`, `exception`
- `occurred_at` timestamp
- `location` nullable string max 200
- `notes` nullable text
- `created_by_user_id` foreign key
- timestamps

### Domain Structure

```
apps/api/Domains/Fulfillment/
  States/
    ShipmentStatus.php
    FulfillmentTrackingEventStatus.php
    DeliveryStatus.php (computed enum)
  Models/
    Shipment.php
    ShipmentLine.php
    FulfillmentTrackingEvent.php
  Actions/
    CreateShipment.php
    UpdateShipment.php
    CancelShipment.php
    AddTrackingEvent.php
    UpdateBackorder.php
  Support/
    FulfillmentNumber.php
    DeliveryStatusCalculator.php
  Policies/
    ShipmentPolicy.php
  Http/
    Controllers/
      FulfillmentController.php
      ShipmentController.php
      FulfillmentTrackingEventController.php
    Requests/
      CreateShipmentRequest.php
      UpdateShipmentRequest.php
      AddTrackingEventRequest.php
      UpdateBackorderRequest.php
    Resources/
      ShipmentResource.php
      ShipmentLineResource.php
      FulfillmentTrackingEventResource.php
      FulfillmentStatusResource.php
```

### Domain Actions

**`CreateShipment`**:

- Accepts PO ID, actor, carrier name, tracking reference, shipment date, estimated arrival date, notes, and line data (PO line ID, quantity shipped, backorder info).
- Validates PO status is `issued`, `acknowledged`, or `change_pending`.
- Validates each PO line is `open` or `partially_received`.
- Creates `Shipment` with `confirmed` status and `ShipmentLine` records.
- Records `fulfillment.shipment.recorded` audit event.

**`UpdateShipment`**:

- Accepts shipment ID, actor, and updatable fields (carrier, tracking, dates, notes).
- Lock version check.
- Records `fulfillment.shipment.updated` audit event.

**`CancelShipment`**:

- Accepts shipment ID, actor, optional reason.
- Validates shipment is not already `delivered` or `cancelled`.
- Sets status to `cancelled`.
- Records `fulfillment.shipment.cancelled` audit event.

**`AddTrackingEvent`**:

- Accepts shipment ID, actor, status, occurred_at, location, notes.
- Creates `FulfillmentTrackingEvent`.
- If status is `delivered`, updates shipment `status` to `delivered` and sets `actual_delivery_date`.
- If status is `delayed`, updates shipment `status` to `delayed`.
- Records `fulfillment.shipment.tracking_event` audit event.

**`UpdateBackorder`**:

- Accepts shipment line ID, actor, backorder quantity, expected date.
- Updates backorder fields on shipment line.
- Records `fulfillment.shipment.backorder_updated` audit event.

**`DeliveryStatusCalculator`**:

- Given a PO, computes per-line and overall delivery status.
- Inputs: PO lines (ordered qty, expected delivery date), goods receipt cumulative quantities, shipments (status, lines, dates).
- Returns structured status data for API and UI consumption.

### Fulfillment Number Generator

`FulfillmentNumber::nextFor(Tenant $tenant): string` — locks tenant shipment records, returns `SH-{year}-{sequence}`.

### Integration With P1-40 (Receiving)

When goods receipt is recorded (P1-40), the system optionally updates `ShipmentLine.quantity_delivered` for matching shipment lines. The matching is by `purchase_order_line_id`. A shipment line's `quantity_delivered` is the sum of goods receipt quantities for that PO line if any shipment references it.

The `DeliveryStatusCalculator` considers both shipment data and goods receipt data:
- If no shipments exist: delivery status is computed from expected dates and goods receipt data only.
- If shipments exist: delivery status combines shipment tracking status with goods receipt completeness.
- A line is "delivered" when `cumulative_quantity_received >= quantity` regardless of shipment state.

## API Contract

Add tenant-scoped authenticated routes:

```
GET    /api/purchase-orders/{purchaseOrder}/fulfillment
GET    /api/purchase-orders/{purchaseOrder}/shipments
POST   /api/purchase-orders/{purchaseOrder}/shipments
GET    /api/shipments/{shipment}
PATCH  /api/shipments/{shipment}
DELETE /api/shipments/{shipment}
GET    /api/shipments/{shipment}/tracking-events
POST   /api/shipments/{shipment}/tracking-events
PATCH  /api/shipments/{shipment}/lines/{line}/backorder
```

**GET /api/purchase-orders/{purchaseOrder}/fulfillment** — Returns computed fulfillment status:

```json
{
  "data": {
    "purchaseOrderId": "uuid",
    "overallStatus": "partial",
    "lineSummaries": [
      {
        "purchaseOrderLineId": "uuid",
        "lineNumber": 1,
        "orderedQuantity": "10.0000",
        "receivedQuantity": "4.0000",
        "deliveryStatus": "partial",
        "expectedDeliveryDate": "2026-07-02",
        "isDelayed": false,
        "backorderQuantity": "0.0000"
      }
    ],
    "lateDeliveryCount": 0,
    "totalLineCount": 3,
    "deliveredLineCount": 1,
    "shipmentCount": 1
  }
}
```

**POST /api/purchase-orders/{purchaseOrder}/shipments** — Create shipment:

```json
{
  "carrierName": "DHL",
  "trackingReference": "1Z999AA10123456784",
  "shipmentDate": "2026-06-20",
  "estimatedArrivalDate": "2026-06-28",
  "notes": "Partial shipment of rack bays",
  "lockVersion": 1,
  "lines": [
    {
      "purchaseOrderLineId": "uuid",
      "quantityShipped": "5.0000",
      "backorderQuantity": "5.0000",
      "backorderExpectedAt": "2026-07-15"
    }
  ]
}
```

**POST /api/shipments/{shipment}/tracking-events**:

```json
{
  "status": "in_transit",
  "occurredAt": "2026-06-21T10:00:00Z",
  "location": "Kuala Lumpur Hub",
  "notes": "Shipment arrived at sorting facility"
}
```

**PATCH /api/shipments/{shipment}/lines/{line}/backorder**:

```json
{
  "backorderQuantity": "3.0000",
  "backorderExpectedAt": "2026-07-20",
  "lockVersion": 1
}
```

### Resources

**ShipmentResource**:

- id, number, status, purchaseOrderId
- carrierName, trackingReference, shipmentDate, estimatedArrivalDate, actualDeliveryDate
- notes, createdByUserId, lockVersion
- lines: ShipmentLineResource[]

**ShipmentLineResource**:

- id, lineNumber, purchaseOrderLineId
- quantityShipped, quantityDelivered, backorderQuantity, backorderExpectedAt, notes

**FulfillmentTrackingEventResource**:

- id, status, occurredAt, location, notes, createdByUserId

**FulfillmentStatusResource**:

- purchaseOrderId, overallStatus, lineSummaries[], lateDeliveryCount, totalLineCount, deliveredLineCount, shipmentCount

## Web Design

Add a Fulfillment feature at `apps/web/features/fulfillment/`.

### PO Workspace Fulfillment Panel

Insert a fulfillment tracking panel into the PO workspace page, placed after goods receipt and before approval. Conditions: shown for `issued`, `acknowledged`, `change_pending` POs.

**Panel sections:**

1. **Delivery Status Header**: Overall status badge (pending_shipment/awaiting/partial/delivered/delayed/backordered) with color coding and text. Late delivery count if any.

2. **Shipments List**: Each shipment shows carrier, tracking reference, status badge, shipment date, estimated arrival, and an expandable line detail section. Actions: "Add tracking event", "Edit", "Cancel" based on permissions and current status.

3. **Create Shipment Form**: Modal or inline form with carrier info, dates, notes, and line-by-line quantity entry with optional backorder fields.

4. **Per-Line Delivery Status Table**: All PO lines with delivery status, expected delivery date, quantities (ordered/received/remaining), backorder info, and late indicator.

5. **Tracking Event Timeline**: Within a shipment detail view, a chronological list of tracking events with status, location, and notes.

### States

**Empty state**: "No shipments recorded. Expected delivery by {date}."

**Loading state**: Skeleton panels matching layout.

**Error state**: "Unable to load fulfillment data" with retry action.

**Edge cases**:
- PO with no expected delivery date set.
- PO with expected delivery date in the past and no receipts (delayed).
- Multiple shipments for the same PO.
- PO lines with partial receipts across multiple shipments.
- Cancelled shipments shown with strikethrough or muted styling.

## Permissions

- `createShipment`: buyer/admin for issued/acknowledged/change-pending POs.
- `viewFulfillment`: all tenant members who can view the PO.
- `updateShipment`: buyer/admin.
- `cancelShipment`: buyer/admin.
- `addTrackingEvent`: buyer/admin.
- `updateBackorder`: buyer/admin.

New policy methods on `ShipmentPolicy` or within `PurchaseOrderPolicy`.

## Audit And Observability

Audit actions:
- `fulfillment.shipment.recorded`
- `fulfillment.shipment.updated`
- `fulfillment.shipment.cancelled`
- `fulfillment.shipment.tracking_event`
- `fulfillment.shipment.backorder_updated`

Audit metadata includes tenant ID, PO ID and number, shipment ID and number, actor ID, line count, and event-specific details.

## Demo Data

Seed at least:
- Issued PO with one confirmed shipment in transit, some lines partially received.
- Issued PO with a fully delivered shipment (all lines received).
- Issued PO with delayed shipment (past estimated arrival, no receipt).
- Issued PO with a shipment that has a backorder on one line.
- Issued PO with multiple partial shipments.
- Issued PO with no shipments yet (pending expected delivery).

## Testing And Verification

Backend tests:
- buyer/admin can create shipment and verify shipment/line records.
- partial shipments accumulate correctly.
- tracking events update shipment status correctly.
- backorder update preserves existing data.
- cancellation of delivered shipment is rejected.
- cross-tenant PO access is denied.
- non-buyer/non-admin cannot create shipments.
- stale lock versions return conflict.
- late delivery detection flags overdue POs.
- fulfillment status computation returns correct status for various scenarios.

Web tests:
- PO workspace shows fulfillment section when PO is issued/acknowledged.
- create shipment form validates input and submits.
- shipment list renders with status badges.
- tracking event timeline displays events in order.
- late delivery indicators display correctly.
- empty/loading/error states render.
- backorder form updates and reflects changes.

Verification commands:

```bash
php artisan test --filter=FulfillmentApiTest
php artisan test --filter=DemoSeederTest
pnpm generate:api
pnpm check:api-contract
pnpm --dir apps/web exec vitest run features/fulfillment/tests/fulfillment-workflow.test.tsx
pnpm --filter @cognify/web typecheck
git diff --check
```

Because this adds a visible PO workspace panel, desktop visual verification is required against the real local API-backed app.

## Acceptance Criteria

- Buyers can create shipments against issued/acknowledged/change-pending POs with per-line quantities.
- Tracking events can be recorded per shipment with status, location, and timestamps.
- Delivery status is computed from shipments, goods receipts, and expected dates.
- Late delivery detection flags POs where expected delivery date has passed without full receipt.
- Backorders can be recorded and updated per shipment line.
- Multiple shipments can exist for a single PO.
- OpenAPI and generated client expose fulfillment routes and schemas.
- The PO workspace shows fulfillment status, shipments, and per-line delivery tracking.
- Roadmap P1-41 can be marked Fully Implemented after implementation, review, PR, and merge.
