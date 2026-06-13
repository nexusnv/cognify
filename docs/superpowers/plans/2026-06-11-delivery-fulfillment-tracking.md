# Delivery and Fulfillment Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement P1-41 so buyers can track shipments, carriers, tracking events, backorders, and delivery status against issued purchase orders.

**Architecture:** Add a `Fulfillment` domain under `apps/api/Domains/Fulfillment/` with `Shipment`, `ShipmentLine`, and `FulfillmentTrackingEvent` models, actions for creating/updating shipments and recording tracking events, and a `DeliveryStatusCalculator` for computed delivery status. Insert a fulfillment panel in the PO workspace.

**Tech Stack:** Laravel 12, Eloquent, Laravel Sanctum route-stack tests, OpenAPI JSON, Orval `@cognify/api-client`, Next.js App Router, React, TanStack Query, MSW, Vitest, shadcn/Radix primitives from `@cognify/ui`.

---

## File Map

Backend:
- Create `apps/api/Domains/Fulfillment/States/ShipmentStatus.php`
- Create `apps/api/Domains/Fulfillment/States/FulfillmentTrackingEventStatus.php`
- Create `apps/api/Domains/Fulfillment/Models/Shipment.php`
- Create `apps/api/Domains/Fulfillment/Models/ShipmentLine.php`
- Create `apps/api/Domains/Fulfillment/Models/FulfillmentTrackingEvent.php`
- Create `apps/api/Domains/Fulfillment/Support/FulfillmentNumber.php`
- Create `apps/api/Domains/Fulfillment/Support/DeliveryStatusCalculator.php`
- Create `apps/api/Domains/Fulfillment/Actions/CreateShipment.php`
- Create `apps/api/Domains/Fulfillment/Actions/UpdateShipment.php`
- Create `apps/api/Domains/Fulfillment/Actions/CancelShipment.php`
- Create `apps/api/Domains/Fulfillment/Actions/AddTrackingEvent.php`
- Create `apps/api/Domains/Fulfillment/Actions/UpdateBackorder.php`
- Create `apps/api/Domains/Fulfillment/Policies/ShipmentPolicy.php`
- Create `apps/api/Domains/Fulfillment/Http/Controllers/ShipmentController.php`
- Create `apps/api/Domains/Fulfillment/Http/Controllers/FulfillmentTrackingEventController.php`
- Create `apps/api/Domains/Fulfillment/Http/Controllers/FulfillmentStatusController.php`
- Create `apps/api/Domains/Fulfillment/Http/Requests/CreateShipmentRequest.php`
- Create `apps/api/Domains/Fulfillment/Http/Requests/UpdateShipmentRequest.php`
- Create `apps/api/Domains/Fulfillment/Http/Requests/AddTrackingEventRequest.php`
- Create `apps/api/Domains/Fulfillment/Http/Requests/UpdateBackorderRequest.php`
- Create `apps/api/Domains/Fulfillment/Http/Resources/ShipmentResource.php`
- Create `apps/api/Domains/Fulfillment/Http/Resources/ShipmentLineResource.php`
- Create `apps/api/Domains/Fulfillment/Http/Resources/FulfillmentTrackingEventResource.php`
- Create `apps/api/Domains/Fulfillment/Http/Resources/FulfillmentStatusResource.php`
- Create `apps/api/database/migrations/2026_06_11_020000_create_shipments_table.php`
- Create `apps/api/database/migrations/2026_06_11_020100_create_shipment_lines_table.php`
- Create `apps/api/database/migrations/2026_06_11_020200_create_fulfillment_tracking_events_table.php`
- Modify `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Modify `apps/api/routes/api.php`
- Create `apps/api/tests/Feature/FulfillmentApiTest.php`
- Modify `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify `apps/api/storage/openapi/openapi.json`

Web:
- Regenerate `packages/api-client/src/generated/*`
- Create `apps/web/features/fulfillment/api/fulfillment-api.ts`
- Create `apps/web/features/fulfillment/hooks/use-fulfillment.ts`
- Create `apps/web/features/fulfillment/components/shipment-panel.tsx`
- Create `apps/web/features/fulfillment/components/create-shipment-form.tsx`
- Create `apps/web/features/fulfillment/components/tracking-timeline.tsx`
- Create `apps/web/features/fulfillment/components/delivery-status-badge.tsx`
- Create `apps/web/features/fulfillment/mocks/fulfillment-fixtures.ts`
- Create `apps/web/features/fulfillment/mocks/fulfillment-handlers.ts`
- Create `apps/web/features/fulfillment/tests/fulfillment-workflow.test.tsx`
- Modify `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Modify `docs/01-product/feature-roadmap.md`

---

## Task 1: Backend Red Tests For Fulfillment Workflow

**Files:**
- Create: `apps/api/tests/Feature/FulfillmentApiTest.php`

- [ ] **Step 1: Create the failing API test file**

`apps/api/tests/Feature/FulfillmentApiTest.php`:
- Follow the exact pattern from `apps/api/tests/Feature/GoodsReceiptApiTest.php`.
- Use `RefreshDatabase`, `tenantUserPair`, `issuedPurchaseOrder`, `purchaseOrder` private helpers matching the GoodsReceipt test pattern.
- Test cases to include:
  1. `test_buyer_can_create_shipment` — POST to `/api/purchase-orders/{po}/shipments` with carrier, tracking, dates, lines; assert 201, assert shipment status 'confirmed', assert audit event `fulfillment.shipment.recorded`
  2. `test_multiple_shipments_allowed` — create 2 shipments for same PO, assert 2 records
  3. `test_add_tracking_event` — create shipment, POST to `/api/shipments/{id}/tracking-events`, assert 201, assert audit event
  4. `test_delivered_tracking_event_updates_shipment_status` — add 'delivered' tracking event, assert shipment status changed to 'delivered'
  5. `test_update_backorder` — PATCH `/api/shipments/{id}/lines/{lineId}/backorder`, assert response and audit event
  6. `test_cancel_shipment` — DELETE `/api/shipments/{id}`, assert status 'cancelled'
  7. `test_fulfillment_status_computed_correctly` — GET `/api/purchase-orders/{po}/fulfillment`, assert 'pending_shipment' with no shipments, 'awaiting_delivery' after creating shipment
  8. `test_cross_tenant_access_is_denied`
  9. `test_non_buyer_cannot_create_shipment`
  10. `test_cancel_delivered_shipment_is_rejected`
  11. `test_lock_version_conflict`
  12. `test_fulfillment_status_detects_delayed` — set past expected_delivery_date, assert isDelayed

- [ ] **Step 2: Run red test**
```bash
php artisan test --filter=FulfillmentApiTest
```
Expected: FAIL because routes, models, migrations, and controllers do not exist.

- [ ] **Step 3: Commit red test**
```bash
git add apps/api/tests/Feature/FulfillmentApiTest.php
git commit -m "test: define fulfillment workflow"
```

---

## Task 2: Database, Models, States

**Files:**
- Create dirs under `apps/api/Domains/Fulfillment/`
- Create status enums, models, migrations, number generator
- Modify PurchaseOrder model

- [ ] **Step 1: Create domain directory structure**
```bash
mkdir -p apps/api/Domains/Fulfillment/{States,Models,Actions,Support,Policies,Http/Controllers,Http/Requests,Http/Resources}
```

- [ ] **Step 2: Create status enums**

`apps/api/Domains/Fulfillment/States/ShipmentStatus.php`:
```php
<?php
namespace Domains\Fulfillment\States;
enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InTransit = 'in_transit';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Delayed = 'delayed';
    case Cancelled = 'cancelled';
}
```

`apps/api/Domains/Fulfillment/States/FulfillmentTrackingEventStatus.php`:
```php
<?php
namespace Domains\Fulfillment\States;
enum FulfillmentTrackingEventStatus: string
{
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Customs = 'customs';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Delayed = 'delayed';
    case Exception = 'exception';
}
```

- [ ] **Step 3: Create migrations**

`2026_06_11_020000_create_shipments_table.php`: Table with id (uuid PK), tenant_id, purchase_order_id, number, status, carrier_name, tracking_reference, shipment_date, estimated_arrival_date, actual_delivery_date, notes, created_by_user_id, lock_version, timestamps. Unique (tenant_id, number). Index (tenant_id, purchase_order_id) and (tenant_id, status, shipment_date).

`2026_06_11_020100_create_shipment_lines_table.php`: Table with id (uuid PK), tenant_id, shipment_id, purchase_order_line_id, line_number, quantity_shipped (decimal 18,4), quantity_delivered (decimal 18,4, default 0), backorder_quantity (decimal 18,4, default 0), backorder_expected_at, notes, timestamps. Unique (shipment_id, purchase_order_line_id).

`2026_06_11_020200_create_fulfillment_tracking_events_table.php`: Table with id (uuid PK), tenant_id, shipment_id, status, occurred_at, location, notes, created_by_user_id, timestamps. Index (tenant_id, shipment_id, occurred_at).

- [ ] **Step 4: Create models**

`Shipment.php`: Extends Model, HasUuids. Fillable: tenant_id, purchase_order_id, number, status, carrier_name, tracking_reference, shipment_date, estimated_arrival_date, actual_delivery_date, notes, created_by_user_id, lock_version. Casts: status => ShipmentStatus::class, dates as immutable_date, lock_version as integer. Relations: tenant(), purchaseOrder(), lines() => HasMany ShipmentLine, trackingEvents() => HasMany FulfillmentTrackingEvent, createdByUser().

`ShipmentLine.php`: Extends Model, HasUuids. Fillable: tenant_id, shipment_id, purchase_order_line_id, line_number, quantity_shipped, quantity_delivered, backorder_quantity, backorder_expected_at, notes. Casts decimals, backorder_expected_at as immutable_date. Relations: tenant(), shipment(), purchaseOrderLine().

`FulfillmentTrackingEvent.php`: Extends Model, HasUuids. Fillable: tenant_id, shipment_id, status, occurred_at, location, notes, created_by_user_id. Casts: status => FulfillmentTrackingEventStatus, occurred_at => datetime. Relations: tenant(), shipment(), createdByUser().

- [ ] **Step 5: Create FulfillmentNumber generator**

`apps/api/Domains/Fulfillment/Support/FulfillmentNumber.php`:
```php
<?php
namespace Domains\Fulfillment\Support;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

class FulfillmentNumber
{
    public static function nextFor(Tenant $tenant): string
    {
        $year = now()->format('Y');
        $sequence = DB::transaction(function () use ($tenant, $year): int {
            $row = DB::table('shipments')
                ->where('tenant_id', $tenant->id)
                ->where('number', 'like', "SH-{$year}-%")
                ->lockForUpdate()
                ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(number, LENGTH(?) + 2) AS UNSIGNED)), 0) + 1 AS next_seq', ["SH-{$year}-"])
                ->first();
            return (int) $row->next_seq;
        });
        return sprintf('SH-%s-%06d', $year, $sequence);
    }
}
```

- [ ] **Step 6: Add `shipments()` relation to PurchaseOrder model**

Add to `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`:
```php
use Domains\Fulfillment\Models\Shipment;

/** @return HasMany<Shipment, $this> */
public function shipments(): HasMany
{
    return $this->hasMany(Shipment::class);
}
```

- [ ] **Step 7: Run migration and test**
```bash
php artisan migrate
php artisan test --filter=FulfillmentApiTest
```

- [ ] **Step 8: Commit schema foundation**
```bash
git add apps/api/Domains/Fulfillment/States apps/api/Domains/Fulfillment/Models apps/api/Domains/Fulfillment/Support apps/api/database/migrations apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php
git commit -m "feat: add fulfillment schema, models, and number generator"
```

---

## Task 3: Backend Actions

**Files:**
- Create: `apps/api/Domains/Fulfillment/Actions/CreateShipment.php`
- Create: `apps/api/Domains/Fulfillment/Actions/UpdateShipment.php`
- Create: `apps/api/Domains/Fulfillment/Actions/CancelShipment.php`
- Create: `apps/api/Domains/Fulfillment/Actions/AddTrackingEvent.php`
- Create: `apps/api/Domains/Fulfillment/Actions/UpdateBackorder.php`
- Create: `apps/api/Domains/Fulfillment/Support/DeliveryStatusCalculator.php`

- [ ] **Step 1: Implement CreateShipment action**

`CreateShipment.php`:
- Constructor takes `AuditRecorder`.
- `handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): Shipment`
- DB transaction: lock PO, validate status in [Issued, Acknowledged, ChangePending], assertLockVersion.
- Generate shipment number via `FulfillmentNumber::nextFor()`.
- Create `Shipment` with `confirmed` status.
- For each line in payload: validate PO line exists and is not cancelled, create `ShipmentLine`.
- Increment PO lock_version.
- Record audit event `fulfillment.shipment.recorded`.
- Return shipment->fresh('lines').

- [ ] **Step 2: Implement UpdateShipment action**

`UpdateShipment.php`:
- `handle(Shipment $shipment, User $actor, array $payload): Shipment`
- DB transaction: lock shipment, assertLockVersion.
- Update carrier, tracking, dates, notes if present in payload.
- Increment lock_version.
- Record audit event `fulfillment.shipment.updated`.
- Return shipment->fresh('lines').

- [ ] **Step 3: Implement CancelShipment action**

`CancelShipment.php`:
- `handle(Shipment $shipment, User $actor, array $payload): Shipment`
- DB transaction: lock shipment, assertLockVersion.
- Validate shipment is not delivered.
- Set status to `cancelled`, increment lock_version.
- Record audit event `fulfillment.shipment.cancelled`.
- Return shipment->fresh('lines').

- [ ] **Step 4: Implement AddTrackingEvent action**

`AddTrackingEvent.php`:
- `handle(Shipment $shipment, User $actor, array $payload): FulfillmentTrackingEvent`
- DB transaction: lock shipment.
- Create `FulfillmentTrackingEvent` from payload.
- If event status is `delivered`: set shipment status to `delivered`, set `actual_delivery_date`.
- If event status is `delayed`: set shipment status to `delayed`.
- If event status is `in_transit` and current status is `confirmed`: set to `in_transit`.
- Increment shipment lock_version.
- Record audit event `fulfillment.shipment.tracking_event`.
- Return event.

- [ ] **Step 5: Implement UpdateBackorder action**

`UpdateBackorder.php`:
- `handle(ShipmentLine $line, User $actor, array $payload): ShipmentLine`
- DB transaction: lock shipment line.
- Update backorder_quantity and backorder_expected_at.
- Record audit event `fulfillment.shipment.backorder_updated`.
- Return line->fresh().

- [ ] **Step 6: Implement DeliveryStatusCalculator**

`DeliveryStatusCalculator.php`:
- `calculate(PurchaseOrder $purchaseOrder): array`
- Load PO with lines, shipments.shipmentLines, goodsReceipts.
- For each line: compute delivery status based on received quantity vs ordered, expected delivery date (past due = delayed), backorder quantity.
- Statuses: pending_shipment, awaiting_delivery, partial, delivered, delayed, backordered.
- Return: overallStatus, lineSummaries[], lateDeliveryCount, totalLineCount, deliveredLineCount, shipmentCount.

- [ ] **Step 7: Run test**
```bash
php artisan test --filter=FulfillmentApiTest
```

- [ ] **Step 8: Commit actions**
```bash
git add apps/api/Domains/Fulfillment/Actions apps/api/Domains/Fulfillment/Support
git commit -m "feat: add fulfillment domain actions and delivery status calculator"
```

---

## Task 4: API Requests, Resources, Controller, Routes, Policy

**Files:**
- Create all Request, Resource, Policy files
- Create 3 Controllers: ShipmentController, FulfillmentTrackingEventController, FulfillmentStatusController
- Modify routes/api.php

- [ ] **Step 1: Create request validation classes**

Requests: `CreateShipmentRequest`, `UpdateShipmentRequest`, `AddTrackingEventRequest`, `UpdateBackorderRequest`.
- Each extends FormRequest.
- `authorize()` checks user can via appropriate policy gate.
- `rules()` validates fields per the design spec.

- [ ] **Step 2: Create resource classes**

Resources: `ShipmentResource` (includes lines from ShipmentLineResource), `ShipmentLineResource`, `FulfillmentTrackingEventResource`, `FulfillmentStatusResource`.
- Follow camelCase JSON convention matching existing resources.

- [ ] **Step 3: Create ShipmentPolicy**

`ShipmentPolicy.php`: methods `updateShipment`, `addTrackingEvent`, `updateBackorder` — each checks buyer or admin role on the shipment's tenant.

Add `createShipment` method to PurchaseOrderPolicy:
```php
public function createShipment(User $user, PurchaseOrder $purchaseOrder): bool
{
    return in_array($purchaseOrder->statusState(), [
        PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged, PurchaseOrderStatus::ChangePending,
    ], true)
        && ($user->hasTenantRole($purchaseOrder->tenant_id, TenantRole::Buyer)
            || $user->hasTenantRole($purchaseOrder->tenant_id, TenantRole::Admin));
}
```

- [ ] **Step 4: Create controllers**

`ShipmentController`: index (list PO shipments), store (create), show (single), update (patch), destroy (cancel), updateBackorder.

`FulfillmentTrackingEventController`: index (list by shipment), store (create).

`FulfillmentStatusController`: show (get computed status).

- [ ] **Step 5: Add routes**

In the `RequireTenantHeader` group in `routes/api.php`, after goods-receipts routes:
```php
Route::get('/purchase-orders/{purchaseOrder}/fulfillment', [\Domains\Fulfillment\Http\Controllers\FulfillmentStatusController::class, 'show']);
Route::get('/purchase-orders/{purchaseOrder}/shipments', [\Domains\Fulfillment\Http\Controllers\ShipmentController::class, 'index']);
Route::post('/purchase-orders/{purchaseOrder}/shipments', [\Domains\Fulfillment\Http\Controllers\ShipmentController::class, 'store']);
Route::get('/shipments/{shipment}', [\Domains\Fulfillment\Http\Controllers\ShipmentController::class, 'show']);
Route::patch('/shipments/{shipment}', [\Domains\Fulfillment\Http\Controllers\ShipmentController::class, 'update']);
Route::delete('/shipments/{shipment}', [\Domains\Fulfillment\Http\Controllers\ShipmentController::class, 'destroy']);
Route::get('/shipments/{shipment}/tracking-events', [\Domains\Fulfillment\Http\Controllers\FulfillmentTrackingEventController::class, 'index']);
Route::post('/shipments/{shipment}/tracking-events', [\Domains\Fulfillment\Http\Controllers\FulfillmentTrackingEventController::class, 'store']);
Route::patch('/shipments/{shipment}/lines/{line}/backorder', [\Domains\Fulfillment\Http\Controllers\ShipmentController::class, 'updateBackorder']);
```

Add imports at the top of routes/api.php.

- [ ] **Step 6: Run tests**
```bash
php artisan test --filter=FulfillmentApiTest
```

- [ ] **Step 7: Commit API layer**
```bash
git add apps/api/Domains/Fulfillment/Http apps/api/Domains/Fulfillment/Policies apps/api/routes/api.php
git commit -m "feat: add fulfillment API layer"
```

---

## Task 5: OpenAPI Spec Update

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`

- [ ] **Step 1: Add fulfillment schemas and endpoints**

Add schemas: `FulfillmentStatus`, `FulfillmentLineSummary`, `Shipment`, `ShipmentLine`, `FulfillmentTrackingEvent`, `CreateShipmentRequest`, `CreateShipmentLineItem`, `AddTrackingEventRequest`.

Add paths: GET/POST `/api/purchase-orders/{purchaseOrder}/fulfillment`, GET/POST `/api/purchase-orders/{purchaseOrder}/shipments`, GET/PATCH/DELETE `/api/shipments/{shipment}`, GET/POST `/api/shipments/{shipment}/tracking-events`.

Follow the exact JSON structure pattern from existing schemas in the file.

- [ ] **Step 2: Run contract check**
```bash
pnpm check:api-contract
```

- [ ] **Step 3: Commit OpenAPI update**
```bash
git add apps/api/storage/openapi/openapi.json
git commit -m "chore: add fulfillment endpoints and schemas to OpenAPI spec"
```

---

## Task 6: Client Generation And Web API

**Files:**
- Regenerate `packages/api-client/src/generated/*`
- Create web API wrapper and hooks

- [ ] **Step 1: Regenerate API client**
```bash
pnpm generate:api
```

- [ ] **Step 2: Create web API wrapper**

`apps/web/features/fulfillment/api/fulfillment-api.ts`:
- Interfaces: `ShipmentLine`, `Shipment`, `FulfillmentStatus`, `FulfillmentLineSummary`, `FulfillmentTrackingEvent`.
- Functions: `fetchFulfillmentStatus`, `fetchShipments`, `createShipment`, `fetchShipment`, `cancelShipment`, `fetchTrackingEvents`, `addTrackingEvent`, `updateBackorder`.
- Each function uses `apiClient.GET/POST/PATCH/DELETE` with tenant headers.

- [ ] **Step 3: Create fulfillment hooks**

`apps/web/features/fulfillment/hooks/use-fulfillment.ts`:
- `useFulfillmentStatus(purchaseOrderId)`
- `useShipments(purchaseOrderId)`
- `useCreateShipment(purchaseOrderId)`
- `useTrackingEvents(shipmentId)`
- `useAddTrackingEvent(shipmentId)`
- `useUpdateBackorder(shipmentId)`
- `useCancelShipment(purchaseOrderId)`

Each uses TanStack Query with query keys scoped to tenant + resource IDs. Mutations invalidate relevant query keys on success.

- [ ] **Step 4: Commit web API and hooks**
```bash
git add apps/web/features/fulfillment/api apps/web/features/fulfillment/hooks
git commit -m "feat: add fulfillment API client and hooks"
```

---

## Task 7: Web Components

**Files:**
- Create 4 components under `apps/web/features/fulfillment/components/`

- [ ] **Step 1: Create DeliveryStatusBadge**

Simple component that renders a colored badge with status label. Maps status to color: pending_shipment=blue, awaiting_delivery=yellow, partial=orange, delivered=green, delayed=red, backordered=purple. Uses shadcn `cn` for class merging.

- [ ] **Step 2: Create CreateShipmentForm**

Inline form with carrier, tracking, dates, notes fields. On submit calls `useCreateShipment` mutation. Handles loading/error states.

- [ ] **Step 3: Create TrackingTimeline**

Shows chronological list of tracking events with dot indicators. Has inline "Add event" form with status dropdown and location input. Uses `useTrackingEvents` and `useAddTrackingEvent`.

- [ ] **Step 4: Create ShipmentPanel**

Main panel component for the PO workspace. Shows:
- Header with title and overall delivery status badge
- Late delivery warning banner if applicable
- "Record shipment" button
- Shipments list with expandable details (carrier, tracking, dates, line details)
- TrackingTimeline within expanded shipment
- Footer stats (X of Y lines delivered, N shipments)
- Loading skeleton state
- Empty state when no shipments

- [ ] **Step 5: Commit components**
```bash
git add apps/web/features/fulfillment/components
git commit -m "feat: add fulfillment tracking UI components"
```

---

## Task 8: PO Workspace Integration

**Files:**
- Modify: `purchase-order-workspace-page.tsx`

- [ ] **Step 1: Integrate ShipmentPanel**

Import `ShipmentPanel` from `../../fulfillment/components/shipment-panel`.
Add `canShowFulfillment` condition (same as goods receipt: issued/acknowledged/change_pending).
Add fulfillment nav section after goods receipt.
Render `<ShipmentPanel>` after `<PurchaseOrderGoodsReceiptPanel>`.

- [ ] **Step 2: Commit**
```bash
git add apps/web/features/purchase-orders/workflows
git commit -m "feat: add fulfillment tracking panel to PO workspace"
```

---

## Task 9: MSW Fixtures And Web Tests

**Files:**
- Create mock fixtures, handlers, and test file

- [ ] **Step 1: Create MSW fixtures**

`fulfillment-fixtures.ts`: Export `mockFulfillmentStatus` and `mockShipments` with realistic data matching the API interfaces.

- [ ] **Step 2: Create MSW handlers**

`fulfillment-handlers.ts`: MSW handlers for GET fulfillment status, GET/POST shipments, GET shipment, POST tracking events. Return fixture data.

- [ ] **Step 3: Create web tests**

`fulfillment-workflow.test.tsx`: Vitest tests with mocked tenant hook. Test: renders title, shows status badge, displays shipment number, shows Record shipment button.

- [ ] **Step 4: Commit mocks and tests**
```bash
git add apps/web/features/fulfillment/mocks apps/web/features/fulfillment/tests
git commit -m "test: add fulfillment MSW fixtures and web tests"
```

---

## Task 10: Demo Seeds

**Files:**
- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`

- [ ] **Step 1: Add fulfillment demo data**

Add shipment + tracking events + backorder for the issued PO that has goods receipts.
Add a delayed shipment for a PO with past expected delivery date and no receipts.

- [ ] **Step 2: Commit seeds**
```bash
git add apps/api/database/seeders
git commit -m "feat: add fulfillment demo seed data"
```

---

## Task 11: Verification

- [ ] **Run backend tests:**
```bash
php artisan test --filter=FulfillmentApiTest
```

- [ ] **Run seed test:**
```bash
php artisan test --filter=DemoSeederTest
```

- [ ] **Run API contract check:**
```bash
pnpm check:api-contract
```

- [ ] **Run web tests:**
```bash
pnpm --filter @cognify/web exec vitest run features/fulfillment/tests/fulfillment-workflow.test.tsx
```

- [ ] **Run typecheck:**
```bash
pnpm --filter @cognify/web typecheck
```

- [ ] **Fix any failures and iterate until green.**

---

## PR Completion Checklist

- [ ] All backend tests pass (FulfillmentApiTest, DemoSeederTest)
- [ ] API contract check passes
- [ ] Web typecheck passes
- [ ] Web tests pass
- [ ] Migration runs clean (up and down)
- [ ] OpenAPI spec updated with all new schemas and endpoints
- [ ] Generated client regenerated
- [ ] PO workspace shows fulfillment panel for issued POs
- [ ] Demo data seeds fulfillment records (shipments, tracking events, backorders)
- [ ] Visual inspection done: fulfillment panel renders correctly with real API data
- [ ] Code review (self-review or CodeRabbit) completed
- [ ] Roadmap updated: P1-41 marked Fully Implemented
