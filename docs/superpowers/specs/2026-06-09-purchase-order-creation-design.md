# Purchase Order Creation Design

## Status

- Status: Draft for review
- Date: 2026-06-09
- Release scope: P1 core procure-to-pay lifecycle, slice P1-36 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-36`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-25-recommendation-award-decision-design.md`
  - `docs/superpowers/specs/2026-05-26-award-approval-design.md`
  - `docs/superpowers/specs/2026-05-26-purchase-order-request-handoff-design.md`
  - `docs/superpowers/specs/2026-05-27-procurement-calendar-design.md`

## Roadmap Analysis

The roadmap update on 2026-06-08 changes Cognify's P1 target from request-to-award governance into a complete procure-to-pay path. P1-36 through P1-54 add durable purchase orders, receiving, supplier invoices, matching, payment readiness, payment status, vendor master readiness, tax/currency/payment-term baselines, record graph visibility, and operational queues.

The next new P1 feature should be `P1-36 Purchase Order Creation`.

Rationale:

- P1-34 already creates a buyer/admin-reviewed `PurchaseOrderRequestHandoff` in `apps/api/Domains/PurchaseOrder`, but the handoff explicitly stops before issuing an official PO number or creating a durable PO.
- P1-37 through P1-50 depend on a real purchase order aggregate. PO review, issue-to-supplier, change orders, receiving, invoice matching, and budget commitment all need stable PO identifiers and line-level commitments.
- Starting with receiving or invoices would force a fake PO model or embed PO data into later domains, creating rework.
- P1-36 is a coherent vertical slice with one primary API domain (`PurchaseOrder`) and one new primary web feature group (`apps/web/features/purchase-orders`).

## Problem

After award approval, Cognify can generate and export a PO handoff package. That makes the next operational step visible, but the product still has no durable internal purchase order record. Users cannot open a PO workspace, see a stable PO number, track line-level ordered amounts, or provide the core record that receiving and invoice matching will use.

The current handoff is a reviewed package. P1-36 promotes that package into the first internal PO aggregate while preserving the audit boundary between award facts, handoff review, and purchase order operations.

## Goals

- Convert one ready or exported PO request handoff into one tenant-scoped purchase order.
- Generate a Cognify PO number, for example `PO-2026-000001`, at PO creation time.
- Persist PO header, vendor, billing, shipping, commercial terms, delivery terms, totals, source links, and line items.
- Keep awarded vendor, selected quotation, source RFQ, and award-approved price, quantity, currency, and line identity facts traceable and locked.
- Allow buyer/admin users to complete missing operational PO fields before sending the PO into a later review/approval slice.
- Show a PO creation action from the existing award recommendation PO handoff panel.
- Add a purchase order workspace and a lightweight purchase order list so created POs are discoverable before P1-54 work queues exist.
- Record audit events for creation and draft updates.
- Expose OpenAPI endpoints and consume them through `@cognify/api-client`.
- Preserve tenant isolation, lock-version conflict handling, and backend-owned state transitions.

## Non-Goals

- PO finance/procurement approval workflow. That belongs to P1-37.
- Supplier issue, supplier acknowledgement, email delivery, or vendor portal PO exposure. That belongs to P1-38.
- Change orders after review or issue. That belongs to P1-39.
- Receiving, goods receipt, delivery fulfillment tracking, invoice capture, matching, invoice approval, payment readiness, or payment status. These belong to P1-40 through P1-48.
- ERP/accounting integration or external PO-number sync.
- Split awards or generating multiple purchase orders from one award recommendation.
- Budget encumbrance or commitment accounting.
- Vendor master enrichment beyond storing the vendor details already available from the handoff snapshot.
- Configurable PO numbering schemes or tenant-specific print/PDF templates.

## Approaches Considered

### 1. Add More Fields To The Existing PO Handoff

This would be the smallest change because the handoff already has source snapshots, status, exports, and policies. It is rejected because it keeps Cognify stuck at "handoff package" semantics. Receiving, invoice matching, and payment readiness need a durable purchase order with line-level state and future lifecycle transitions.

### 2. Create A Durable PO Directly From Approved Award Recommendation

This bypasses the handoff and creates a PO immediately after award approval. It is rejected for this slice because P1-34 intentionally inserted buyer/admin review before downstream use. Creating a PO directly from the award would skip the operational review fields already captured on the handoff and would make the handoff redundant.

### 3. Convert A Ready Or Exported PO Handoff Into A Draft Purchase Order

This is the selected approach. It uses the existing handoff as the reviewed source package, creates one durable PO from that package, and leaves approval/issue/receiving/invoice behavior for later slices. It is small enough to implement end to end while giving future P2P workflows a real aggregate.

## Workflow

### Actors

- Buyer: creates a PO from a ready/exported handoff, reviews the draft PO, fills missing operational fields, saves updates, and opens the PO workspace.
- Admin: has the same abilities as buyer for tenant workflows.
- Finance user: no new write permissions in P1-36 unless their existing tenant role also grants buyer/admin behavior. Finance review is deferred to P1-37.
- Requester: can view linked requisition/project context through existing routes, but does not create or edit POs in this slice.
- Vendor portal visitor: no PO access in this slice.
- System: validates the source handoff, creates the PO and lines transactionally, records audit events, and enforces idempotency.

### Source Eligibility

A `PurchaseOrderRequestHandoff` can create a PO when:

- It belongs to the current tenant.
- Its status is `ready` or `exported`.
- It is not `cancelled`.
- It has no existing active `PurchaseOrder`.
- It includes line snapshots, currency, total amount, approved award decision metadata, vendor id, RFQ id, recommendation id, and quotation/version references when available.

`draft` handoffs should not create POs. The user must finish the handoff review and mark it ready first. This keeps the existing P1-34 review gate meaningful.

### Purchase Order States

`PurchaseOrderStatus`:

- `draft`: created from a ready/exported handoff and editable for allowed operational fields.
- `ready_for_review`: buyer/admin has completed required operational fields and made the PO available for the future P1-37 review/approval workflow.
- `cancelled`: buyer/admin cancels the draft before review because the PO should not proceed.

State rules:

- Creation always starts in `draft`.
- `draft` can be updated for allowed fields.
- `draft` can move to `ready_for_review` when required fields are present.
- `draft` can be cancelled with a reason.
- `ready_for_review` is terminal for P1-36. P1-37 will introduce the review/approval transitions from this state.
- `cancelled` is terminal for this slice.

### Main Flow

1. Buyer/admin opens an approved RFQ award recommendation.
2. The existing PO handoff panel shows a ready or exported handoff and a `Create purchase order` action.
3. Buyer/admin starts PO creation.
4. The API validates the handoff, locks it, and checks that no PO already exists for it.
5. The API creates a `PurchaseOrder` header with a generated PO number and source links to the handoff, recommendation, approval instance, RFQ, requisition, project, vendor, quotation, and quotation version.
6. The API creates `PurchaseOrderLine` rows from the handoff line snapshot.
7. The API copies handoff review fields into PO draft fields:
   - requested PO date
   - delivery attention
   - finance note
   - export memo as internal note context
8. The API copies source, approval, and evidence snapshots for traceability, while keeping source ids as explicit columns.
9. The API records `purchase_order.created`.
10. The web redirects to `/purchase-orders/{purchaseOrderId}`.
11. Buyer/admin completes any missing draft PO fields and saves.
12. Buyer/admin marks the PO `ready_for_review`, which records `purchase_order.ready_for_review`.

### Failure Paths

- Handoff not ready/exported: return `409` with a message telling the user to mark the handoff ready first.
- Handoff cancelled: return `409`.
- Existing PO for handoff: return the existing PO instead of creating a duplicate.
- Missing required source facts: return `409` with specific missing fields.
- Cross-tenant source or route access: return `403` or `404` consistently with existing tenant-scoped routes.
- Stale update or ready-for-review action: require `lockVersion` and return `409` on mismatch.
- PO already ready for review: block update/cancel actions in P1-36 and return `409`.

## Data Model

### `purchase_orders`

Create `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php` backed by `purchase_orders`.

Suggested columns:

- `id` UUID primary key
- `tenant_id`
- `purchase_order_request_handoff_id` unique per tenant
- `rfq_award_recommendation_id`
- `approval_instance_id` nullable
- `rfq_id`
- `requisition_id` nullable
- `project_id` nullable
- `vendor_id`
- `quotation_id` nullable
- `quotation_version_id` nullable
- `number`
- `status`
- `currency`
- `subtotal_amount`
- `tax_amount`
- `freight_amount`
- `discount_amount`
- `total_amount`
- `requested_po_date` nullable
- `expected_delivery_date` nullable
- `billing_name` nullable
- `billing_address` JSON nullable
- `shipping_name` nullable
- `shipping_address` JSON nullable
- `delivery_attention` nullable
- `payment_terms` nullable
- `delivery_terms` nullable
- `buyer_note` nullable
- `finance_note` nullable
- `source_snapshot` JSON
- `approval_snapshot` JSON
- `evidence_snapshot` JSON
- `created_by_user_id`
- `ready_for_review_by_user_id` nullable
- `ready_for_review_at` nullable
- `cancelled_by_user_id` nullable
- `cancelled_at` nullable
- `cancelled_reason` nullable
- `lock_version`
- timestamps

Indexes:

- unique `tenant_id`, `purchase_order_request_handoff_id`
- unique `tenant_id`, `number`
- index `tenant_id`, `status`, `updated_at`
- index `tenant_id`, `vendor_id`
- index `tenant_id`, `rfq_id`
- index `tenant_id`, `requisition_id`

### `purchase_order_lines`

Create `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php` backed by `purchase_order_lines`.

Suggested columns:

- `id` UUID primary key
- `tenant_id`
- `purchase_order_id`
- `source_line_id` nullable
- `line_number`
- `description`
- `category` nullable
- `sku` nullable
- `unit`
- `quantity`
- `unit_price`
- `subtotal_amount`
- `tax_amount` nullable
- `freight_amount` nullable
- `discount_amount` nullable
- `total_amount`
- `currency`
- `needed_by_date` nullable
- `expected_delivery_date` nullable
- `delivery_location` nullable
- `notes` nullable
- `source_snapshot` JSON nullable
- timestamps

Indexes:

- index `tenant_id`, `purchase_order_id`
- unique `purchase_order_id`, `line_number`

Line values created from the approved handoff are locked in P1-36 except for draft-only delivery metadata and notes. Price, quantity, vendor, currency, and source quotation facts should not be edited in this first slice. If those facts are wrong, the user should cancel the draft PO and correct the upstream process; P1-39 will handle controlled change orders after a PO exists.

## API Contract

Add OpenAPI paths under the existing tenant-scoped authenticated route group:

```txt
GET    /api/purchase-orders
GET    /api/purchase-orders/{purchaseOrder}
POST   /api/po-handoffs/{handoff}/purchase-order
PATCH  /api/purchase-orders/{purchaseOrder}
POST   /api/purchase-orders/{purchaseOrder}/ready-for-review
POST   /api/purchase-orders/{purchaseOrder}/cancel
```

Endpoint behavior:

- `GET /api/purchase-orders`: returns paginated tenant purchase orders with filters for status, vendor, requester/requisition/project where available, search, and updated date.
- `GET /api/purchase-orders/{purchaseOrder}`: returns one PO with lines, source links, snapshots, permissions, and lock version.
- `POST /api/po-handoffs/{handoff}/purchase-order`: creates or reveals the PO for a ready/exported handoff.
- `PATCH /api/purchase-orders/{purchaseOrder}`: updates allowed draft operational fields with `lockVersion`.
- `POST /api/purchase-orders/{purchaseOrder}/ready-for-review`: validates required fields and moves `draft` to `ready_for_review`.
- `POST /api/purchase-orders/{purchaseOrder}/cancel`: cancels a draft with reason and `lockVersion`.

Generated client outputs should include:

- `PurchaseOrder`
- `PurchaseOrderLine`
- `PurchaseOrderStatus`
- `PurchaseOrderListResponse`
- `PurchaseOrderResponse`
- `CreatePurchaseOrderFromHandoffResponse`
- `UpdatePurchaseOrderRequest`
- `MarkPurchaseOrderReadyForReviewRequest`
- `CancelPurchaseOrderRequest`

## Authorization And Tenant Rules

Use a new `PurchaseOrderPolicy`.

P1-36 permissions:

- Buyer/admin can list, view, create from handoff, update draft operational fields, mark ready for review, and cancel draft POs.
- Requester cannot create or edit POs in this slice.
- Vendor portal users cannot access PO endpoints.
- Future finance-specific permissions are deferred to P1-37 unless they already map to buyer/admin.

Every query must filter by `tenant_id`. Every source relation must belong to the current tenant:

- source handoff
- recommendation
- approval instance
- RFQ
- requisition
- project
- vendor
- quotation
- quotation version

## Backend Design

Extend `apps/api/Domains/PurchaseOrder`.

New files:

- `Actions/CreatePurchaseOrderFromHandoff.php`
- `Actions/UpdatePurchaseOrder.php`
- `Actions/MarkPurchaseOrderReadyForReview.php`
- `Actions/CancelPurchaseOrder.php`
- `Http/Controllers/PurchaseOrderController.php`
- `Http/Requests/UpdatePurchaseOrderRequest.php`
- `Http/Requests/MarkPurchaseOrderReadyForReviewRequest.php`
- `Http/Requests/CancelPurchaseOrderRequest.php`
- `Http/Resources/PurchaseOrderResource.php`
- `Http/Resources/PurchaseOrderLineResource.php`
- `Models/PurchaseOrder.php`
- `Models/PurchaseOrderLine.php`
- `Policies/PurchaseOrderPolicy.php`
- `States/PurchaseOrderStatus.php`
- `Support/PurchaseOrderNumber.php`

Action responsibilities:

- `CreatePurchaseOrderFromHandoff` owns idempotent conversion from handoff to PO and line rows inside one database transaction.
- `UpdatePurchaseOrder` owns lock-version checks and allowed draft field updates.
- `MarkPurchaseOrderReadyForReview` owns required-field validation and status transition.
- `CancelPurchaseOrder` owns cancellation reason capture and terminal state.

Controllers stay thin and delegate business behavior to actions.

## Web Design

Create `apps/web/features/purchase-orders`.

Suggested structure:

```txt
apps/web/features/purchase-orders/
  api/
  components/
  hooks/
  mocks/
  schemas/
  tables/
  tests/
  types/
  workflows/
```

Routes:

```txt
apps/web/app/(workspace)/purchase-orders/page.tsx
apps/web/app/(workspace)/purchase-orders/[purchaseOrderId]/page.tsx
```

Primary screens:

- Purchase order list: dense table with PO number, status, vendor, total, currency, source requisition/RFQ, updated date, and action to open the workspace.
- Purchase order workspace: record-focused view with header status, source trail, vendor summary, commercial terms, billing/shipping details, line table, evidence links, activity summary, and actions.
- PO draft editor: inline or form section for allowed draft operational fields.
- Source handoff action: existing `rfq-award-po-handoff-panel.tsx` shows `Create purchase order` when the handoff is ready/exported and no PO exists.

Navigation:

- Add "Purchase orders" under the Procurement module once the list route exists.
- Add global search result support for `purchase_order` after the API exposes purchase order search/list metadata.
- Do not add deep P2P queue navigation yet; P1-54 owns daily operational queues.

UX states:

- Loading, empty list, populated list, permission denied, create conflict, stale update, draft missing required fields, ready-for-review success, and cancelled state.
- Disable source-locked line and award fields with explanatory field labels, not editable controls.
- Keep the workspace quiet and operational. This is a work surface, not a marketing page.

## Data Flow

```txt
Ready/exported PO handoff
  -> POST /api/po-handoffs/{handoff}/purchase-order
  -> PurchaseOrder + PurchaseOrderLine rows
  -> generated client response
  -> web redirects to /purchase-orders/{id}
  -> buyer/admin edits draft operational fields
  -> PATCH /api/purchase-orders/{id}
  -> POST /api/purchase-orders/{id}/ready-for-review
  -> future P1-37 review workflow starts from ready_for_review
```

## Audit And Notifications

Audit events:

- `purchase_order.created`
- `purchase_order.updated`
- `purchase_order.ready_for_review`
- `purchase_order.cancelled`

Audit payloads should include:

- PO id and number
- source handoff id and number
- source recommendation id
- vendor id
- total amount and currency
- before/after status for transitions
- changed draft fields for updates

No new notification event is required in P1-36. P1-37 can add review assignment notifications when PO approval tasks exist.

## Calendar And Record Graph

P1-36 should not expand the procurement calendar beyond current behavior. Expected delivery dates can be stored on the PO and PO lines for future use, but adding PO dates as a calendar source should wait for a follow-up calendar enhancement or the broader P2P visibility work. Full P2P record graph behavior belongs to P1-53.

## Testing And Verification

Backend focused tests:

- `apps/api/tests/Feature/PurchaseOrderCreationApiTest.php`
  - buyer/admin can create a draft PO from a ready handoff
  - exported handoff can create a PO
  - draft/cancelled handoff cannot create a PO
  - duplicate creation reveals existing PO
  - PO creation persists line rows from handoff snapshot
  - cross-tenant handoff access is denied
  - requester/vendor cannot create or update PO
  - draft operational fields update with `lockVersion`
  - stale update returns `409`
  - ready-for-review validates required fields and changes status
  - cancelled PO cannot be updated or marked ready

Web focused tests:

- `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`
  - list renders created POs
  - workspace renders source trail, vendor, totals, line table, and status actions
  - draft field update calls generated-client-backed API wrapper
  - ready-for-review action handles success and validation conflict
  - cancelled state disables editing
- Existing quotation award recommendation workspace test:
  - ready/exported PO handoff shows `Create purchase order`
  - created PO redirects to the PO workspace

Contract and verification commands:

```bash
pnpm generate:api
pnpm check:api-contract
php artisan test --filter PurchaseOrderCreationApiTest
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
pnpm --dir apps/web exec vitest run features/quotations/tests/rfq-award-recommendation-workspace.test.tsx
pnpm --filter @cognify/web typecheck
```

Run the API command from `apps/api` if not using the repo-level script wrapper.

## Open Questions Resolved For This Spec

- PO creation source: ready/exported handoff, not award recommendation directly.
- PO number: generated internally by Cognify at creation time.
- Supplier issue: deferred to P1-38.
- Review/approval: deferred to P1-37, with P1-36 ending at `ready_for_review`.
- Line edits: source-locked in P1-36 except delivery metadata and notes.
- PO discoverability: lightweight list and detail route now; broad operational queues later.

## Exit Criteria

P1-36 is complete when:

- A buyer/admin can convert a ready/exported PO handoff into exactly one durable purchase order.
- The purchase order has a stable PO number, source links, header fields, line rows, status, lock version, and audit trail.
- The purchase order can be opened from a dedicated workspace route.
- Allowed draft operational fields can be updated with conflict handling.
- The PO can be marked `ready_for_review` for P1-37.
- Generated API client types are used by the web feature.
- Focused API, web, contract, and typecheck validations pass.
