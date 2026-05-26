# Purchase Order Request Handoff Design

## Status

- Status: Draft for review
- Date: 2026-05-26
- Release scope: P1 Epic 8, slice 1 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-34`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-25-recommendation-award-decision-design.md`
  - `docs/superpowers/specs/2026-05-26-award-approval-design.md`

## Purpose

P1-34 turns an approved RFQ award recommendation into a structured purchase-order request handoff package for finance or ERP users. The handoff makes the next operational step visible inside Cognify and gives buyers/admins a downloadable CSV or JSON artifact that can be uploaded or re-keyed into an external system.

This slice does not integrate with an ERP, issue an official purchase order number, notify vendors, or sync downstream PO status back into Cognify. It creates an auditable internal handoff record and export artifact only.

## Problem

P1-32 and P1-33 make vendor selection auditable: buyers can choose the recommended vendor, preserve rationale/evidence/scores, and route the recommendation through approval. After approval, the current workflow stops at an approved recommendation. A buyer still has to infer what finance needs, gather requisition/RFQ/vendor/quotation/approval details manually, and move the process forward outside Cognify.

The roadmap calls for a structured ERP or finance handoff before direct integration. The right P1 scope is therefore a durable, tenant-scoped PO request package with clear readiness, review, and export states. It should be generated from the approved recommendation facts so the exported payload reflects what was actually approved.

## Goals

- Automatically create a draft PO request handoff when an RFQ award recommendation reaches `approved`.
- Snapshot the approved recommendation context into the handoff: requisition, project, RFQ, selected vendor, selected quotation/version, pricing, line items, commercial terms, approval summary, and supporting evidence references.
- Show the PO handoff status and actions in the award recommendation workspace.
- Allow buyer/admin users to review the package and mark it ready for export.
- Provide CSV and JSON export endpoints for finance or ERP upload.
- Record audit events for draft creation, ready marking, export generation, and cancellation.
- Preserve tenant isolation, generated API contracts, and backend-owned workflow transitions.
- Keep this as the first post-award handoff slice without broad ERP integration.

## Non-Goals

- Direct ERP, accounting, or finance-system integration.
- Real PO creation inside Cognify.
- ERP-assigned PO number, PO status sync, receipt, invoice, payment, goods-received, or three-way-match workflows.
- Vendor award/regret notifications.
- Split awards, partial awards, line-level award distribution across multiple vendors, or multiple PO requests from one award recommendation.
- Contract lifecycle management handoff.
- Calendar views, renewal dates, delivery tracking, or procurement calendar behavior.
- Configurable export mapping templates or tenant-specific ERP adapters.
- A finance approval workflow separate from the already completed award approval.
- Editing the approved award recommendation after the handoff exists.

## Design Decision

### Create A Dedicated PurchaseOrder Domain

P1-34 should introduce `apps/api/Domains/PurchaseOrder` for post-award operational handoff behavior. The existing `RfqAwardRecommendation` remains in `apps/api/Domains/Quotation` because it owns evaluation and approval facts. The new domain owns handoff packaging, status, export format, and future PO status sync extension points.

This boundary is preferable to adding PO behavior directly to `Quotation` because the handoff is no longer an evaluation concern. It is also preferable to using the current lightweight `Domains\Award\Models\Award` demo scaffold as the primary surface, because P1-34 is specifically about a PO request handoff record and export package rather than a complete award lifecycle domain.

The first implementation can keep the existing `Award` model untouched unless demo/search code requires compatibility. A future award lifecycle slice can decide whether to formalize an operational Award aggregate.

### Auto-Create Draft Handoff On Approval

When `RfqAwardRecommendation` transitions to `approved`, the award approval callback should create or reveal exactly one `PurchaseOrderRequestHandoff` record for that recommendation.

The handoff starts in `draft` so it is immediately visible, but nothing is exported or submitted automatically. Buyer/admin users can review the generated package, resolve missing operational fields, mark it `ready`, and export CSV or JSON on demand.

This keeps the next step clear while preserving a human gate before finance/ERP use.

### Snapshot Approved Facts

The handoff should snapshot the approved recommendation facts at creation time instead of rebuilding from mutable RFQ/quotation/vendor data every time the user opens the screen. The source IDs remain linked for navigation and traceability, but the package exports the reviewed snapshot.

Snapshotting protects audit integrity if vendor records, quotation metadata, or RFQ details change after approval. If a later workflow needs a corrected package, it should create a new handoff revision or cancel/regenerate through an explicit action. That is out of scope for this first slice except for a simple cancellation state.

### Export Is A Generated Artifact, Not Integration

CSV and JSON exports should be generated from the handoff snapshot and recorded as audit events. They should not be stored as permanent files in the Evidence Vault unless a later evidence/export retention slice requires it.

JSON should preserve structured nested sections. CSV should be a pragmatic finance-friendly flattened line export with repeated header fields per line item.

## Approaches Considered

1. Internal handoff package only.

   This is the smallest version and would avoid export complexity, but it underserves the roadmap wording because finance/ERP users still need a portable artifact.

2. Internal handoff package plus CSV/JSON export.

   This is the selected approach. It creates a durable internal workflow record and gives finance/ERP users a practical handoff artifact without pretending Cognify has direct ERP integration.

3. Internal handoff package plus submitted-to-finance workflow.

   This adds useful operational tracking, but it starts to model a downstream finance queue and status semantics before we have actual finance users or integrations. The first slice should avoid that extra lifecycle.

## Workflow

### Actors

- Buyer: reviews the auto-created handoff, fills optional operational fields, marks it ready, exports CSV/JSON, and cancels the handoff if it was created in error.
- Admin: same abilities as buyer for tenant workflows.
- Approver: no new PO handoff action in this slice; approvers only drive handoff creation indirectly by approving the award recommendation.
- Requester: no direct PO handoff access in this slice.
- Vendor portal visitor: no access.
- System: creates or reveals the handoff when award approval completes, snapshots source data, records audit events, and produces export responses.

### States

`PurchaseOrderRequestHandoffStatus`:

- `draft`: generated from an approved award recommendation and available for buyer/admin review.
- `ready`: buyer/admin has confirmed the package is ready to hand to finance or ERP.
- `exported`: at least one CSV or JSON export has been generated from the ready package.
- `cancelled`: buyer/admin cancelled the handoff before or after export because it should not be used.

State rules:

- Only an approved award recommendation can create a handoff.
- Creation is idempotent by recommendation id.
- `draft` can be reviewed and updated for optional handoff fields.
- `draft` can move to `ready` when required package fields exist.
- Only `ready` or `exported` handoffs can be exported.
- Exporting a `ready` handoff moves it to `exported`.
- Exporting an already `exported` handoff creates a new audit/export event but does not create a new handoff.
- `draft`, `ready`, and `exported` can be cancelled by buyer/admin with a reason.
- `cancelled` is terminal for this slice.
- Changes to the source recommendation after approval are not allowed by prior slice rules; the handoff remains read-only from the recommendation side.

### Main Flow

1. Approver approves the final award approval task.
2. `MarkRfqAwardRecommendationApproved` records the recommendation outcome.
3. A PurchaseOrder domain action creates or reveals a `PurchaseOrderRequestHandoff`.
4. The handoff snapshots:
   - tenant id
   - recommendation id
   - approval instance id
   - RFQ id, number, title, scope summary, response due date
   - requisition id, number, title, requester, department, cost center, delivery location, needed-by date when available
   - project id, number, name when linked
   - vendor id, name, registration/tax/contact fields available in current vendor records
   - quotation id, quotation number, selected quotation version id, version number
   - currency, subtotal, tax, freight, discount, total amount
   - payment, delivery, warranty, lead-time, and compliance terms
   - quotation line item snapshot
   - award rationale, tradeoff, risk, exception summary
   - scorecard id and scoring summary when available
   - approval summary and approver decision metadata
   - evidence reference labels and ids
5. The award recommendation workspace shows a PO handoff section once status is `approved`.
6. Buyer/admin reviews the package and marks it ready.
7. Buyer/admin downloads CSV or JSON export.
8. Cognify records audit events for readiness and export.

### Failure Paths

- No approved recommendation: return `409` for create/reveal and hide handoff actions in the UI.
- Missing recommendation, RFQ, vendor, quotation, or selected version: return `409` with a specific readiness error; do not create an incomplete ready/exportable package.
- Optional operational fields missing: create `draft` with readiness warnings rather than blocking creation.
- Cross-tenant access: return `404` or `403` consistently with adjacent quotation/approval routes.
- Duplicate creation attempt: return the existing active handoff.
- Export before ready: return `409`.
- Export cancelled handoff: return `409`.
- Stale update: require `lockVersion` on review/update, ready, and cancel actions.

## Data Model

### PurchaseOrderRequestHandoff

Create `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderRequestHandoff.php` backed by `purchase_order_request_handoffs`.

Suggested columns:

- `id` UUID primary key
- `tenant_id`
- `rfq_award_recommendation_id` unique per tenant
- `approval_instance_id` nullable link for traceability
- `rfq_id`
- `requisition_id` nullable
- `project_id` nullable
- `vendor_id`
- `quotation_id`
- `quotation_version_id`
- `number` generated handoff number, for example `POH-2026-000001`
- `status`
- `currency`
- `subtotal_amount`
- `tax_amount`
- `freight_amount`
- `discount_amount`
- `total_amount`
- `requested_by_user_id`
- `ready_by_user_id` nullable
- `ready_at` nullable
- `cancelled_by_user_id` nullable
- `cancelled_at` nullable
- `cancelled_reason` nullable
- `last_exported_by_user_id` nullable
- `last_exported_at` nullable
- `last_export_format` nullable, `csv` or `json`
- `source_snapshot` JSON
- `line_snapshot` JSON
- `approval_snapshot` JSON
- `evidence_snapshot` JSON
- `readiness_warnings` JSON nullable
- `lock_version`
- timestamps

Indexes:

- unique `tenant_id`, `rfq_award_recommendation_id`
- index `tenant_id`, `status`, `updated_at`
- index `tenant_id`, `rfq_id`
- index `tenant_id`, `vendor_id`

### Export Event

Do not create a separate export-history table in this slice. Repeated export history is captured through:

- `last_exported_*` fields on the handoff
- audit event payload for every export

Do not store raw export file contents in the database for this slice.

## Snapshot Shape

`source_snapshot` should be shaped for JSON export and UI rendering:

```json
{
  "handoff": {
    "number": "POH-2026-000001",
    "status": "draft",
    "createdAt": "2026-05-26T00:00:00Z"
  },
  "requisition": {
    "id": "1",
    "number": "REQ-2026-0001",
    "title": "Warehouse racking",
    "requesterName": "Aisha Rahman",
    "department": "Operations",
    "costCenter": "OPS-MY",
    "deliveryLocation": "Kuala Lumpur DC",
    "neededByDate": "2026-07-15"
  },
  "rfq": {
    "id": "10",
    "number": "RFQ-2026-0007",
    "title": "Warehouse racking RFQ"
  },
  "project": {
    "id": "5",
    "number": "PRJ-2026-0003",
    "name": "Warehouse Expansion"
  },
  "vendor": {
    "id": "101",
    "name": "Northwind Traders",
    "contactName": "Maya Lim",
    "contactEmail": "maya@example.test"
  },
  "quotation": {
    "id": "201",
    "number": "QT-2026-0034",
    "versionId": "301",
    "versionNumber": 2,
    "currency": "MYR",
    "subtotalAmount": "120000.00",
    "taxAmount": "9600.00",
    "freightAmount": "1500.00",
    "discountAmount": "0.00",
    "totalAmount": "131100.00",
    "paymentTerms": "Net 30",
    "deliveryTerms": "Delivered to site",
    "warrantyTerms": "24 months",
    "leadTimeDays": 30
  },
  "awardRecommendation": {
    "id": "rec-uuid",
    "rationale": "Best weighted score and compliant lead time.",
    "tradeoffSummary": "Higher freight cost offset by shorter lead time.",
    "riskSummary": "No critical risks.",
    "exceptionSummary": null
  }
}
```

`line_snapshot` should be an array with one object per selected quotation version line item:

```json
[
  {
    "lineNumber": 1,
    "itemCode": "RACK-001",
    "description": "Pallet rack bay",
    "quantity": "20.000",
    "unitOfMeasure": "EA",
    "unitPrice": "5000.00",
    "taxAmount": "8000.00",
    "freightAmount": "1000.00",
    "discountAmount": "0.00",
    "lineTotal": "109000.00",
    "currency": "MYR",
    "notes": "Includes installation."
  }
]
```

`approval_snapshot` should include the approval instance id, completed-at timestamp, final decision, final approver summaries, and decision reason when present.

`evidence_snapshot` should include evidence type, id, label, and source links where available. It should not duplicate attachment file contents.

## API Design

Use tenant-scoped routes under the authenticated API. Suggested endpoints:

```txt
GET    /api/rfqs/{rfq}/award-recommendation/po-handoff
POST   /api/rfqs/{rfq}/award-recommendation/po-handoff
PATCH  /api/po-handoffs/{handoff}
POST   /api/po-handoffs/{handoff}/ready
POST   /api/po-handoffs/{handoff}/cancel
GET    /api/po-handoffs/{handoff}/export.json
GET    /api/po-handoffs/{handoff}/export.csv
```

Route responsibilities:

- `GET /rfqs/{rfq}/award-recommendation/po-handoff`: returns the current handoff for the RFQ award recommendation, or `null` if not created/approved.
- `POST /rfqs/{rfq}/award-recommendation/po-handoff`: creates or reveals the handoff. This is mostly for repair/idempotency because normal creation happens automatically on approval.
- `PATCH /po-handoffs/{handoff}`: updates optional operational fields held in the handoff snapshot, such as delivery attention, internal finance note, requested PO date, and export memo. It must not alter approved recommendation/vendor/amount facts.
- `POST /po-handoffs/{handoff}/ready`: validates required fields and moves `draft` to `ready`.
- `POST /po-handoffs/{handoff}/cancel`: terminal cancellation with reason.
- `GET /po-handoffs/{handoff}/export.json`: returns downloadable JSON export and moves `ready` to `exported` if needed.
- `GET /po-handoffs/{handoff}/export.csv`: returns downloadable CSV export and moves `ready` to `exported` if needed.

Expected operation IDs:

- `showRfqAwardRecommendationPoHandoff`
- `createRfqAwardRecommendationPoHandoff`
- `updatePurchaseOrderRequestHandoff`
- `markPurchaseOrderRequestHandoffReady`
- `cancelPurchaseOrderRequestHandoff`
- `exportPurchaseOrderRequestHandoffJson`
- `exportPurchaseOrderRequestHandoffCsv`

Expected schemas:

- `PurchaseOrderRequestHandoffResponse`
- `PurchaseOrderRequestHandoff`
- `PurchaseOrderRequestHandoffStatus`
- `PurchaseOrderRequestHandoffSnapshot`
- `PurchaseOrderRequestHandoffLine`
- `PurchaseOrderRequestHandoffApprovalSummary`
- `PurchaseOrderRequestHandoffEvidenceReference`
- `UpdatePurchaseOrderRequestHandoffRequest`
- `MarkPurchaseOrderRequestHandoffReadyRequest`
- `CancelPurchaseOrderRequestHandoffRequest`

The JSON export endpoint returns the same structured handoff payload with an export envelope:

```json
{
  "exportedAt": "2026-05-26T00:00:00Z",
  "format": "json",
  "handoff": {}
}
```

The generated client should expose the JSON endpoint. CSV download should use a small typed web helper that preserves tenant/auth conventions and returns a `Blob`, because generated JSON helpers are not the right fit for `text/csv` downloads.

## Authorization

Use a `PurchaseOrderRequestHandoffPolicy`.

Rules:

- Buyer/admin can view, create/reveal, update optional fields, mark ready, export, and cancel tenant handoffs.
- Approver can view only if they are also buyer/admin in the tenant; no approver-only handoff visibility in this slice.
- Requester has no handoff access in this slice.
- Vendor portal users have no access.
- Every action must verify:
  - authenticated session
  - current tenant
  - handoff tenant id
  - linked RFQ/recommendation/vendor/quotation tenant ids

The auto-create path from award approval should run inside backend domain logic after the approval decision is authorized. It must still validate tenant ownership before writing.

## Web UX

### Placement

Extend the existing award recommendation workspace:

- `apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx`
- Add a section after approval once recommendation status is `approved`.
- Use a feature component such as `RfqAwardPoHandoffPanel`.
- Use shadcn/Radix primitives from `@cognify/ui`; keep Cognify-specific composition in `apps/web`.

Do not add a broad finance module, standalone PO list, or procurement calendar in this slice.

### Panel Behavior

For non-approved recommendations:

- Do not show PO handoff actions.

For approved recommendations without a handoff:

- Show a small recovery action for buyer/admin: `Generate PO handoff`.
- Normal users should rarely see this because approval should auto-create the draft.

For `draft` handoff:

- Show handoff number, status, vendor, total, currency, line count, approval timestamp, and readiness warnings.
- Show editable optional fields:
  - requested PO date
  - delivery attention
  - finance note
  - export memo
- Show `Mark ready` action.

For `ready` handoff:

- Show read-only summary and export actions:
  - `Download JSON`
  - `Download CSV`
- Allow cancellation with reason.

For `exported` handoff:

- Show last exported timestamp, format, and actor.
- Continue allowing repeat downloads.
- Allow cancellation only with a clear reason.

For `cancelled` handoff:

- Show cancellation reason and actor.
- No export or ready actions.

### Export UX

Exports should behave like downloads, not navigation. The UI should use standard buttons with clear labels and preserve error states.

The panel should show:

- loading state for handoff query
- empty state for no approved recommendation
- error state for forbidden/not found/conflict
- disabled state while mutations run
- stale state message when `lockVersion` conflicts

## Export Format

### JSON

JSON should be the canonical structured export. It should include:

- export metadata
- handoff identifiers
- source snapshot
- line snapshot
- approval snapshot
- evidence snapshot
- readiness warnings

### CSV

CSV should be line-oriented. Each row is one line item. Repeat header-level values on every row so finance users can import it into basic tools.

Recommended CSV headers:

```txt
handoff_number
handoff_status
rfq_number
requisition_number
project_number
vendor_name
vendor_id
quotation_number
quotation_version
currency
po_total_amount
line_number
item_code
description
quantity
unit_of_measure
unit_price
tax_amount
freight_amount
discount_amount
line_total
payment_terms
delivery_terms
warranty_terms
lead_time_days
approval_instance_id
approved_at
approved_by
award_rationale
finance_note
export_memo
```

Marking ready must block when `line_snapshot` is empty. CSV export therefore always has at least one line-item row in this slice.

## Backend Actions

Create actions under `apps/api/Domains/PurchaseOrder/Actions`:

- `CreateOrRevealPurchaseOrderRequestHandoff`
- `BuildPurchaseOrderRequestHandoffSnapshot`
- `UpdatePurchaseOrderRequestHandoff`
- `MarkPurchaseOrderRequestHandoffReady`
- `CancelPurchaseOrderRequestHandoff`
- `ExportPurchaseOrderRequestHandoff`

Integration point:

- Modify `MarkRfqAwardRecommendationApproved` so it calls `CreateOrRevealPurchaseOrderRequestHandoff` after the recommendation is marked approved.
- Keep this call idempotent and inside the same transaction as the approved recommendation transition. Snapshot creation is synchronous in this slice because all source data is local.

Readiness validation:

- approved recommendation exists
- selected vendor, quotation, and quotation version exist
- selected quotation belongs to RFQ/vendor/tenant
- handoff has currency and total
- line snapshot is non-empty
- approval snapshot confirms approved outcome

Readiness warnings:

- missing requester metadata
- missing cost center
- missing delivery location
- missing vendor contact email
- missing tax/freight/discount breakdown
- missing warranty/payment/delivery terms

Warnings do not block draft creation. Blocking errors prevent `ready`.

## Audit And Notifications

Audit events:

- `purchase_order_handoff.created`
- `purchase_order_handoff.updated`
- `purchase_order_handoff.ready`
- `purchase_order_handoff.exported`
- `purchase_order_handoff.cancelled`

Audit metadata should include:

- handoff id and number
- recommendation id
- rfq id
- vendor id
- status before/after where relevant
- export format where relevant
- cancellation reason where relevant

Notifications:

- No new notification channel or notification event is required for P1-34. The award workspace is the primary discovery surface for the created handoff.

## OpenAPI And Generated Client

OpenAPI is the source of truth. Add schemas/endpoints to:

- `apps/api/storage/openapi/openapi.json`

Then regenerate:

```bash
pnpm generate:api
pnpm check:api-contract
```

Web code must consume generated schemas/endpoints from `@cognify/api-client`. Do not duplicate contract response types in app code.

## Testing

### API Tests

Create focused tests such as `apps/api/tests/Feature/PurchaseOrderRequestHandoffApiTest.php`:

- approving an award recommendation auto-creates one draft PO handoff
- approval callback is idempotent and does not create duplicate handoffs
- buyer/admin can create or reveal handoff for an already approved recommendation
- non-approved recommendations cannot create handoffs
- handoff snapshot includes requisition, RFQ, vendor, quotation version, line, approval, and evidence details
- buyer/admin can update optional handoff fields with `lockVersion`
- ready action validates blocking fields and records ready actor/timestamp
- JSON export returns structured payload and marks ready handoff exported
- CSV export returns text/csv with expected headers and line rows
- repeat export records audit without creating a new handoff
- cancelled handoff cannot be exported
- cross-tenant view/update/ready/export/cancel attempts fail
- requester/vendor cannot access handoff endpoints
- real Sanctum/session route stack succeeds before logout and returns `401` after logout

### Web Tests

Extend quotation workspace tests:

- approved recommendation shows PO handoff panel
- draft handoff shows warnings and mark-ready action
- ready handoff shows CSV/JSON export actions
- exported handoff shows last export metadata and repeat download actions
- cancelled handoff hides export actions
- mutation errors and stale lock conflicts are visible
- non-approved recommendation does not show handoff actions

Add API wrapper/hook tests for JSON and CSV export behavior.

### Verification Commands

Expected implementation verification:

```bash
php artisan test --filter=PurchaseOrderRequestHandoffApiTest
php artisan test --filter=RfqAwardApprovalApiTest
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web test -- rfq-award-recommendation-workspace
pnpm lint
pnpm typecheck
git diff --check
```

Adjust commands during implementation only if repo scripts or test names require it.

## Scope Boundaries For Implementation Plan

The implementation plan should include:

- new PurchaseOrder backend domain files
- migration and model/policy/action/resource/controller/routes
- OpenAPI contract and generated client updates
- web API wrappers/hooks/MSW fixtures
- `RfqAwardPoHandoffPanel` in the existing award workspace
- focused API and web tests
- roadmap update only after implementation and verification pass

The implementation plan should not include:

- standalone purchase order list page
- finance queue
- ERP adapter abstraction
- configurable export templates
- PO status sync
- procurement calendar
- vendor communications
- split award support

## Completion Definition

This slice is complete when an approved award recommendation automatically yields a tenant-scoped PO request handoff draft, buyer/admin users can review and mark that package ready, and Cognify can generate CSV and JSON handoff exports from the approved snapshot with audit coverage. The workflow remains intentionally incomplete for real procurement operations: Cognify does not create an official PO, send it to an ERP, notify vendors, or track downstream PO status in this slice.
