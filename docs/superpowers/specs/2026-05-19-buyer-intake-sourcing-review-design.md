# Buyer Intake Sourcing Review Design

## Status

- Status: Draft for review
- Date: 2026-05-19
- Release scope: P1 Epic 5, slice 1 only
- Related roadmap: `docs/02-release-management/2026-05-15-P1-Epics.md`, `docs/01-product/feature-roadmap.md`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`

## Purpose

This slice gives buyers a durable intake workflow for submitted or approved requisitions that need sourcing review. Buyers can triage the requisition, classify the sourcing need, check completeness, assign ownership, and record a sourcing path decision.

The slice intentionally stops before RFQ creation and vendor invitations. It must preserve explicit handoff boundaries for the next Epic 5 slices:

1. RFQ creation from a ready intake review.
2. Vendor invitations from the RFQ workspace.

## Problem

Approved or intake-ready requisitions need buyer review before they become sourcing work. Without a dedicated intake workflow, buyers either overload requisition status, use comments as decisions, or jump straight into RFQ records before the sourcing path is clear. That weakens auditability and makes later RFQ, quotation, and award work harder to reason about.

## Goals

- Provide a buyer intake queue for requisitions that need sourcing review.
- Record buyer ownership, category/classification, urgency, complexity, target decision date, checklist state, and decision notes.
- Preserve requisition content as the source of truth while keeping sourcing decisions in a dedicated workflow record.
- Record auditable state transitions for intake creation, claim/reassignment, review updates, and decisions.
- Provide a `ready_for_rfq` handoff state for the next slice without creating RFQs in this slice.
- Keep API contracts generated through OpenAPI and consumed by `@cognify/api-client`.

## Non-Goals

- RFQ creation forms or RFQ workspace behavior.
- Vendor invitation workflow.
- Vendor portal access.
- Quotation upload, manual entry, or versioning.
- Quotation comparison, scoring, award decisions, award approvals, or PO handoff.
- AI sourcing recommendations.
- Full vendor master management.
- Policy enforcement beyond recorded checklist and sourcing decision fields.

## Actors

- Buyer: reviews intake work, claims or receives assignments, records sourcing decisions.
- Admin: can manage any tenant intake review and reassign ownership.
- Requester: receives clarification requests through the existing requisition correction flow, but does not manage intake reviews.
- Approver: can see relevant requisition outcomes through existing approval/requisition surfaces, but does not manage sourcing intake.
- System: records audit events and in-app notifications for workflow side effects.

## Workflow

The intake workflow starts when a tenant buyer/admin explicitly creates or reveals an intake review for an eligible requisition. Automatic intake creation on approval is deferred until a later operational automation slice.

Eligible requisitions are:

- tenant-scoped to the active tenant;
- `submitted` or `approved`;
- not withdrawn or cancelled;
- not already linked to another active sourcing intake review.

The intake state machine is:

```txt
open
  -> in_review
in_review
  -> clarification_requested | ready_for_rfq | direct_award_recorded | closed
clarification_requested
  -> in_review | closed
ready_for_rfq
  -> closed
direct_award_recorded
  -> closed
```

Allowed sourcing path decisions:

- `needs_rfq`: the review is complete and ready for the RFQ creation slice.
- `needs_clarification`: the buyer needs requester correction or missing information.
- `direct_award`: the buyer records that the sourcing path is direct award, but no award workflow is built here.
- `no_sourcing_required`: the buyer records a reasoned closeout where sourcing is not needed. This path is included in the first implementation so buyers are not forced to choose RFQ or direct award for administrative closeouts.

Clarification should use or align with the existing requisition change-request behavior so requesters see one consistent correction flow. `ready_for_rfq` must create a durable handoff state, not a draft RFQ.

## Backend Design

Backend ownership belongs in `apps/api/Domains/Quotation` because the roadmap maps buyer intake and sourcing to the quotation domain.

Add a durable sourcing intake review model named `SourcingIntakeReview`, linked to:

- tenant;
- requisition;
- optional procurement project;
- assigned buyer;
- actor metadata for claimed, decided, and closed events.

Core fields:

- `tenant_id`
- `requisition_id`
- `project_id`
- `assigned_buyer_id`
- `status`
- `sourcing_path`
- `category`
- `subcategory`
- `urgency`
- `complexity`
- `target_decision_date`
- `checklist`
- `internal_notes`
- `decision_reason`
- `clarification_message`
- `claimed_at`
- `decided_at`
- `closed_at`
- timestamps

The checklist should be structured JSON for the first slice, with named items such as specification completeness, budget clarity, line item completeness, needed-by feasibility, project linkage, and evidence sufficiency. It can be normalized later if reporting or policy rules require item-level tables.

Domain actions should own behavior:

- create or reveal intake review for a requisition;
- claim intake review;
- reassign intake review;
- update classification/checklist/notes;
- record sourcing decision;
- close review from `in_review`, `clarification_requested`, `ready_for_rfq`, or `direct_award_recorded` when the request includes a required closeout reason.

Controllers should only adapt HTTP requests to domain actions and resources.

## API Contract

OpenAPI should define every request and response before backend hardening, then regenerate `packages/api-client`.

Proposed endpoints:

- `GET /api/sourcing/intake-reviews`
  List buyer intake reviews with pagination, filters, sorting, presets, and status counts.
- `GET /api/sourcing/intake-reviews/{review}`
  Show intake review detail with requisition summary, checklist, activity summary, and permissions.
- `POST /api/requisitions/{requisition}/sourcing-intake`
  Create or reveal the intake review for an eligible requisition. This is idempotent per requisition.
- `POST /api/sourcing/intake-reviews/{review}/claim`
  Assign the current buyer.
- `POST /api/sourcing/intake-reviews/{review}/reassign`
  Reassign to another buyer. Buyer/admin permissions apply.
- `PATCH /api/sourcing/intake-reviews/{review}`
  Save classification, checklist, notes, urgency, complexity, and target decision date.
- `POST /api/sourcing/intake-reviews/{review}/decision`
  Record the sourcing path decision with reason and optional requester-facing clarification message.

Responses should include:

- review identity and status;
- requisition summary;
- project summary when available;
- assigned buyer summary;
- checklist state;
- timestamps;
- allowed permissions/actions;
- normalized validation and conflict errors.

## Permissions And Tenancy

Only buyers and admins can create, claim, update, reassign, or decide intake reviews.

Every query and mutation must prove:

- authenticated user;
- active tenant membership;
- source requisition belongs to the active tenant;
- optional project belongs to the active tenant;
- assigned buyer belongs to the active tenant;
- cross-tenant records cannot be inferred through error differences.

Frontend permissions may hide actions, but backend policies and domain actions remain authoritative.

## Audit And Notifications

Audit events should be emitted for:

- `sourcing_intake.created`
- `sourcing_intake.claimed`
- `sourcing_intake.reassigned`
- `sourcing_intake.updated`
- `sourcing_intake.clarification_requested`
- `sourcing_intake.ready_for_rfq`
- `sourcing_intake.direct_award_recorded`
- `sourcing_intake.closed`

In-app notifications should be recorded for these events:

- assigned buyer receives intake assignment;
- requester receives clarification request;
- assigned buyer receives the RFQ-ready handoff when the review is marked `ready_for_rfq`.

Notification behavior must remain in-app only and respect existing notification infrastructure.

## Frontend Design

Frontend ownership should be `apps/web/features/sourcing`.

Suggested structure:

```txt
apps/web/features/sourcing/
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

- `/sourcing/intake`: buyer intake queue.
- `/sourcing/intake/{review}`: intake detail workspace.

Navigation should be permission-aware and visible to buyers/admins.

The queue should use existing table patterns with loading, empty, populated, error, permission-denied, and mobile fallback states. Columns should include:

- requisition number/title;
- requester;
- department;
- project;
- estimated total;
- needed-by date;
- requisition status;
- intake status;
- assigned buyer;
- target decision date;
- updated date.

Presets:

- unassigned;
- mine;
- needs clarification;
- ready for RFQ;
- closed or decided.

The detail workspace should use `RecordWorkspaceLayout`.

Workspace sections:

- header with requisition number/title, intake status, assigned buyer, and sourcing path;
- requisition summary, line items, justification, requester metadata, and project context;
- buyer review panel with category, urgency, complexity, target decision date, checklist, and internal notes;
- decision form with required reason handling;
- activity timeline combining intake events and relevant requisition activity;
- state-aware actions for claim, reassign, save review, request clarification, mark ready for RFQ, record direct-award path, and close as no sourcing required.

`ready_for_rfq` may show a disabled or future-state handoff affordance. It must not create an RFQ.

## Mocked Frontend Workflow

MSW handlers should live under `apps/web/features/sourcing/mocks` or shared test setup and return OpenAPI-shaped data. Production components must not import fixtures directly.

Mocks should cover:

- empty queue;
- unassigned queue item;
- claimed review;
- clarification requested;
- ready for RFQ;
- direct award recorded;
- permission denied;
- stale/conflict response when another buyer decides first.

## Error Handling

The API should return normalized error envelopes for:

- validation errors;
- permission denial;
- missing tenant context;
- cross-tenant or missing records;
- invalid state transitions;
- stale review updates;
- duplicate active review attempts when idempotency cannot safely resolve.

The web UI should present validation summaries, disabled actions for impossible transitions, conflict recovery that refetches the review, and a clear permission-denied state.

## Testing

API tests:

- buyers/admins can create, list, show, claim, reassign, update, and decide intake reviews;
- requesters and approvers cannot manage sourcing intake;
- tenant isolation is enforced for requisitions, projects, assigned buyers, and reviews;
- review creation is idempotent per requisition;
- invalid transitions return conflict errors;
- clarification requests align with the existing requisition correction flow;
- audit events are written for create, assignment, update, and decision actions;
- OpenAPI response permissions and validation shapes are covered.

Web tests:

- queue renders loading, empty, populated, error, and permission-denied states;
- filters and presets update the query state;
- detail workspace renders generated-client-shaped MSW data;
- claim, save, and decision flows invalidate relevant TanStack Query keys;
- stale-state conflicts refetch and preserve user context;
- `ready_for_rfq` renders handoff state without RFQ creation behavior.

Contract and verification commands:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
pnpm build
```

Narrower test commands can be used during development, but final verification should include contract generation and the relevant API/web checks for touched areas.
