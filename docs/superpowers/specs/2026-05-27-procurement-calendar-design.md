# Procurement Calendar Design

## Status

- Status: Draft for review
- Date: 2026-05-27
- Release scope: P1 Epic 8, slice 2 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-35`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-17-approval-orchestration-design.md`
  - `docs/superpowers/specs/2026-05-19-rfq-draft-creation-design.md`
  - `docs/superpowers/specs/2026-05-19-vendor-invitation-to-rfq-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-versioning-design.md`
  - `docs/superpowers/specs/2026-05-22-quotation-comparison-table-design.md`
  - `docs/superpowers/specs/2026-05-26-award-approval-design.md`
  - `docs/superpowers/specs/2026-05-26-purchase-order-request-handoff-design.md`

## Purpose

P1-35 gives buyers, managers, approvers, and admins one authenticated calendar surface for date-sensitive procurement work. It aggregates existing workflow dates such as RFQ response deadlines, approval due dates, requisition needed-by dates, purchase-order handoff requested dates, and quotation validity dates.

This slice is a read-only operational visibility layer. It does not create manual reminders, persist independent calendar events, sync external calendars, or mutate source workflows.

## Problem

The P1 procurement lifecycle now has multiple date-bearing workflows. RFQs have response deadlines, approval tasks have due dates, requisitions carry needed-by dates, quotations can expire, and approved awards can move into PO handoff. Without a calendar, buyers and managers must inspect several queues and workspaces to understand what is late, what is due soon, and which source record needs attention.

The roadmap calls for a procurement calendar as the final P1 feature. The right P1 scope is a tenant-safe, generated-contract read model over existing source-of-truth records, not a separate planning system.

## Goals

- Add one authenticated `/calendar` workspace for procurement dates.
- Aggregate visible events from existing source records through a backend-owned read model.
- Support month, week, and agenda-style views over the same API contract.
- Let users filter by source type, event status, date range, and search text.
- Show summary counts for overdue, due soon, scheduled, completed, and informational items.
- Link every event back to the owning workspace when a source record exists.
- Preserve tenant isolation, permission filtering, and generated API contracts.
- Represent missing future domains, such as vendor document expiry or contract renewals, as disabled or empty sources rather than fake data.

## Non-Goals

- Manual reminders or ad hoc user-created calendar items.
- Persisted `procurement_calendar_events` records.
- Calendar editing, drag-and-drop rescheduling, or workflow mutations from the calendar.
- External calendar sync, ICS export, Outlook integration, Google Calendar integration, or notifications from calendar events.
- Business-hours calendars, holiday rules, or SLA recalculation.
- Contract lifecycle management or real contract renewal tracking.
- Vendor document expiry implementation unless existing vendor/document fields already provide real dates.
- New approval, RFQ, quotation, purchase-order, or vendor workflow states.

## Design Decision

### Use A Query-Backed Calendar Read Model

P1-35 should expose `GET /api/procurement-calendar/events`, backed by a server-side query that reads current tenant data from existing domains and normalizes date-bearing records into a common event shape.

The calendar should not persist event rows. Source records remain the source of truth, and event status is derived at request time from the source date and source workflow state. This avoids event sync bugs, backfills, and a second lifecycle that could drift from RFQs, approvals, quotations, requisitions, or PO handoffs.

### Keep Calendar Behavior Read-Only

The calendar is an operational visibility surface. It can route the user to the source workspace, but it does not complete approvals, update RFQ deadlines, edit quotation validity, change requisition needed-by dates, or modify PO handoffs.

Mutations stay in the owning workflow screens where validation, audit, policy, lock-version handling, and user intent are already explicit.

### Backend Owns Visibility Rules

The web app should not call several source endpoints and merge dates client-side. Calendar visibility must be tenant-scoped and permission-filtered on the backend so the API contract expresses exactly which events the current actor may see.

The frontend consumes generated `@cognify/api-client` types through a feature wrapper in `apps/web/features/procurement-calendar`.

### Use Reporting As The Read-Model Owner

Backend ownership should live in `apps/api/Domains/Reporting` unless implementation review shows a stronger local convention for cross-workflow read models. The calendar spans approvals, RFQs, requisitions, quotations, purchase-order handoffs, and future vendor document dates, so it is a reporting/read concern rather than a new workflow domain.

Source-specific mappers can depend on existing domain models, but durable business behavior remains in the source domains.

## Approaches Considered

1. Query-backed aggregate endpoint.

   This is the selected approach. It is simple, tenant-safe, and always reflects current source records. The main implementation risk is query performance, which can be controlled with date-range caps, indexed source fields, and per-source limits.

2. Persisted generated events.

   A dedicated event table would make reads faster and could support history later, but it introduces synchronization, backfill, invalidation, and lifecycle complexity. That is too much for P1-35.

3. Frontend-only aggregation.

   The web app could call RFQ, approval, requisition, quotation, and PO endpoints separately, then merge events in the browser. This duplicates backend visibility rules, creates more network chatter, and weakens the OpenAPI boundary.

## Workflow

### Actors

- Buyer: views RFQ deadlines, requisition needed-by dates, quotation validity dates, PO handoff dates, and other sourcing events they can access.
- Manager: views operational deadlines for procurement work visible to their role.
- Approver: views assigned approval due dates and any related records their permissions allow.
- Admin: views tenant-wide calendar events across supported sources.
- Requester: may view their own requisition needed-by dates when their permissions allow calendar access.
- Vendor portal visitor: no access to the authenticated procurement calendar.
- System: derives events from current source records and returns normalized calendar data.

### Views

- `month`: default view for broad workload scanning.
- `week`: denser short-range view for immediate deadlines.
- `agenda`: chronological list for keyboard-friendly review and testing.

All views use the same API event shape. The `view` query parameter may help the API tune default limits or grouping, but it must not change event semantics.

### Main Flow

1. User opens `/calendar` inside the authenticated workspace shell.
2. `SessionGate` verifies the session and tenant context.
3. Web hook calls `GET /api/procurement-calendar/events` with `from`, `to`, optional `view`, optional `sourceTypes[]`, optional `statuses[]`, and optional `q`.
4. Laravel applies `auth:sanctum` and `ResolveCurrentTenant`.
5. Calendar query loads visible source records for the current tenant and actor.
6. Source mappers normalize records into calendar events.
7. API returns range metadata, source availability metadata, summary counts, and event rows.
8. UI renders the selected view with filters, compact event cards, empty states, and source-record links.
9. User opens an event detail panel or follows the source link to the owning workspace.

### Event Sources

Initial supported sources:

- RFQ response deadlines from `Rfq.response_due_at`.
- RFQ invitation-specific response deadlines from `RfqInvitation.response_due_at` when those dates differ from the RFQ-level deadline.
- Approval task due dates from active `ApprovalTask.due_at` records.
- Requisition needed-by dates from `Requisition.needed_by_date`.
- PO handoff requested PO dates from `PurchaseOrderRequestHandoff.requested_po_date` when present.
- Quotation validity dates from current quotation or current quotation version `valid_until`.

Placeholder or future sources:

- Vendor document expiry dates: included in `availableSources` as unavailable unless a real source table/field exists.
- Contract renewal dates: omitted or marked unavailable until a real contract lifecycle model exists.
- Expected delivery dates: for P1, use requisition needed-by and PO handoff requested PO dates only. Do not invent delivery tracking without a source field.

### Derived Status

Calendar event status is derived, not stored:

- `overdue`: event date is before now and the source is still open or actionable.
- `due_soon`: event date is within the next 7 days and the source is still open or actionable.
- `scheduled`: event date is in the future outside the due-soon window.
- `completed`: source record has a clear completed or resolved state.
- `informational`: date is useful context but not directly actionable, such as quotation validity.

Source mappers must define what open, actionable, or completed means for each source type. If the source state is ambiguous, prefer `informational` over implying actionability.

## API Contract

### Endpoint

```txt
GET /api/procurement-calendar/events
```

Query parameters:

- `from`: required ISO date.
- `to`: required ISO date.
- `view`: optional enum `month`, `week`, `agenda`.
- `sourceTypes[]`: optional event source enum filter.
- `statuses[]`: optional event status enum filter.
- `q`: optional search string.
- `limit`: optional item cap for agenda-heavy ranges.

Validation rules:

- `from` and `to` are required dates.
- `to` must be on or after `from`.
- Date range is capped, recommended at 120 days.
- `sourceTypes[]` and `statuses[]` must use known enum values.
- `q` is trimmed and capped.
- `limit` is bounded.

### Response Shape

```json
{
  "data": {
    "range": {
      "from": "2026-05-01",
      "to": "2026-05-31",
      "timezone": "UTC"
    },
    "summary": {
      "total": 14,
      "byStatus": {
        "overdue": 2,
        "dueSoon": 4,
        "scheduled": 6,
        "completed": 1,
        "informational": 1
      },
      "bySourceType": {
        "rfqDeadline": 3,
        "approvalDue": 4,
        "requisitionNeededBy": 2,
        "poHandoff": 1,
        "quotationValidity": 4,
        "vendorDocumentExpiry": 0
      }
    },
    "availableSources": [
      {
        "sourceType": "rfqDeadline",
        "label": "RFQ deadlines",
        "available": true
      },
      {
        "sourceType": "vendorDocumentExpiry",
        "label": "Vendor documents",
        "available": false,
        "reason": "Vendor document expiry dates are not captured yet."
      }
    ],
    "events": []
  }
}
```

### Event Shape

Each event contains:

- `id`: stable composite id, such as `rfq:<uuid>:response_due`.
- `sourceType`: enum.
- `sourceId`: source record id.
- `sourceLabel`: human-readable source group.
- `title`: compact display title.
- `description`: optional supporting text.
- `startsAt`: ISO date-time.
- `endsAt`: nullable ISO date-time.
- `allDay`: boolean.
- `status`: derived event status.
- `priority`: enum such as `low`, `normal`, `high`, `critical`.
- `record`: `{ type, id, label, href }`.
- `context`: compact source-specific metadata only.

Do not return full source resources inside `context`. The calendar event is a summary row with a navigation target.

### Source Type Enum

Initial enum values:

- `rfqDeadline`
- `approvalDue`
- `requisitionNeededBy`
- `poHandoff`
- `quotationValidity`
- `vendorDocumentExpiry`
- `contractRenewal`

`vendorDocumentExpiry` and `contractRenewal` can be listed as unavailable until real source records exist.

## Backend Design

### Query Object

Add a calendar query service under `apps/api/Domains/Reporting`, for example:

```txt
apps/api/Domains/Reporting/
  Http/Controllers/ProcurementCalendarEventController.php
  Http/Requests/ListProcurementCalendarEventsRequest.php
  Http/Resources/ProcurementCalendarEventResource.php
  Http/Resources/ProcurementCalendarEventCollectionResource.php
  Queries/ListProcurementCalendarEvents.php
  Support/ProcurementCalendarEvent.php
  Support/ProcurementCalendarSourceMapper.php
```

Use only the files the implementation actually needs.

### Source Mappers

Each mapper should:

- accept current tenant, actor, date range, filters, and search text;
- query only records the actor may view;
- apply source-specific date filtering in SQL where possible;
- normalize rows into `ProcurementCalendarEvent` value objects;
- avoid source mutations and side effects.

Potential mapper classes:

- `RfqDeadlineCalendarSource`
- `ApprovalDueCalendarSource`
- `RequisitionNeededByCalendarSource`
- `PurchaseOrderHandoffCalendarSource`
- `QuotationValidityCalendarSource`
- `UnavailableCalendarSource` for vendor document and contract placeholders

### Tenant And Permission Rules

Every source query must prove:

- actor is authenticated;
- current tenant is resolved;
- source record belongs to current tenant;
- related records belong to the same tenant;
- actor has permission to view the source record or source category;
- cross-tenant records do not appear even when ids or dates match.

Use existing policies or permission resolver behavior where practical. Do not rely on frontend filtering.

### Performance Rules

- Require a bounded date range.
- Filter by date in database queries before mapping.
- Select only fields needed for event summaries.
- Eager-load only compact relationships needed for labels and links.
- Apply a bounded `limit` for agenda-heavy ranges if needed.
- Sort events by `startsAt`, then source priority, then title.

## Frontend Design

### Ownership

Frontend feature code belongs under:

```txt
apps/web/features/procurement-calendar/
  api/
  components/
  hooks/
  mocks/
  tests/
  types/
  utils/
  workflows/
```

Route:

```txt
apps/web/app/(workspace)/calendar/page.tsx
```

Shared UI primitives can come from `packages/ui`, but calendar-specific components stay in `apps/web`.

### Workspace Layout

The `/calendar` page should include:

- header with title, date range controls, today button, and view switcher;
- summary strip for status and source counts;
- filter controls for source type, status, and search text;
- month grid as the default view;
- week view for near-term work;
- agenda list for dense and accessible review;
- event detail panel or inline disclosure with source metadata and source-record link;
- empty, filtered-empty, loading, error, and permission-denied states.

Use URL query state for shareable filters and date ranges where consistent with existing table/filter patterns.

### Event Cards

Event cards should remain compact:

- source badge;
- title;
- date or time;
- status indicator;
- source record label, such as RFQ number, approval task label, requisition number, vendor, or quotation number.

The event card should not become a miniature workspace. Users follow the link to act on the source record.

### Navigation

Add `/calendar` to shell navigation and command palette visibility for users with calendar permission. If no explicit permission exists yet, derive initial visibility from existing buyer/admin/manager style permissions without exposing unsupported source records.

Breadcrumb:

```txt
Calendar
```

## Error Handling

- `422`: invalid dates, unsupported filters, range over cap, or invalid limit.
- `403`: authenticated actor lacks calendar visibility entirely.
- `404`: should not normally be used for aggregate listing, but source links may produce 404 if a record becomes inaccessible after the event list loads.
- Valid empty range: return empty `events` and summary counts.
- Unavailable source category: return metadata in `availableSources`; do not fail the request.
- Partial source implementation: omit unsupported source rows rather than returning fake events.

UI error states:

- loading skeleton while events load;
- empty state for no events in range;
- filtered-empty state when filters hide all events;
- permission-denied state when API returns `403`;
- retryable error state for transient API failures.

## OpenAPI And Client

OpenAPI remains the contract source of truth:

- update `apps/api/storage/openapi/openapi.json`;
- regenerate `packages/api-client/src/generated/*`;
- consume generated types through `apps/web/features/procurement-calendar/api`;
- keep MSW handlers OpenAPI-shaped.

Do not hand-write duplicate response types in `apps/web`.

## Audit, Notifications, And Search

This slice is read-only and does not need new audit events or notification records. Source workflows continue to own audit and notifications for their own mutations.

Global search integration is optional for P1-35. If included, add only the `/calendar` command/navigation entry. Do not index derived calendar events as separate searchable records.

## Testing

### Backend

Add API feature tests for:

- buyer/admin can list calendar events in a date range;
- RFQ response deadlines appear with source links;
- active approval task due dates appear and completed tasks derive completed or are excluded according to mapper rules;
- requisition needed-by dates appear only for visible records;
- PO handoff requested dates appear when present;
- quotation validity dates appear as informational events;
- `sourceTypes[]`, `statuses[]`, `from`, `to`, and `q` filters work;
- invalid range returns `422`;
- over-cap range returns `422`;
- cross-tenant records never appear;
- unauthorized roles receive `403` or source-filtered empty responses according to policy;
- unavailable vendor-document and contract sources appear in metadata without fake events.

### Frontend

Add web tests for:

- calendar page renders loading and populated states;
- month view groups events by date;
- agenda view sorts chronologically;
- source and status filters update results;
- search empty state appears;
- event detail exposes source metadata and source link;
- permission-denied and API error states render;
- shell navigation and command palette include calendar only when permitted.

### Browser Smoke

Add or plan a soft-gate Playwright smoke for:

1. Log in with seeded demo credentials.
2. Open `/calendar`.
3. Filter to RFQ deadlines.
4. Open one event detail.
5. Follow the source link to the RFQ or quotation workspace.

If local Playwright host dependencies block execution, keep the spec and validate with lint/typecheck/unit tests while documenting the environment blocker.

### Verification Commands

Backend:

```bash
cd apps/api
php artisan test --filter=ProcurementCalendar
php artisan route:list --path=api
```

Contract:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm typecheck
```

Frontend:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

Final shared check when implementation touches broad shell/navigation or generated client surfaces:

```bash
pnpm lint
pnpm test
pnpm build
```

## Completion Criteria

P1-35 is complete when authenticated users with calendar visibility can open `/calendar`, scan date-sensitive procurement work across supported source records, filter the view, inspect event details, and navigate back to source workflows through generated-client-backed data.

The roadmap should be updated after implementation with this design spec path and the implementation plan path. Keep manual reminders, persisted event lifecycle, external calendar sync, contract renewals, and real vendor-document expiry tracking out of P1-35 unless a later approved spec expands scope.
