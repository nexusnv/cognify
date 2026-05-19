# RFQ Draft Creation Design

## Status

- Status: Draft for review
- Date: 2026-05-19
- Release scope: P1 Epic 5, slice 2 only
- Related roadmap: `docs/02-release-management/2026-05-15-P1-Epics.md`, `docs/01-product/feature-roadmap.md`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Previous slice: `docs/superpowers/specs/2026-05-19-buyer-intake-sourcing-review-design.md`

## Purpose

This slice lets buyers create and maintain a draft RFQ from a sourcing intake review that has already been marked `ready_for_rfq`. It turns the buyer intake handoff into a durable RFQ workspace without introducing vendor invitations, external vendor access, quotation capture, evaluation, awards, or purchase order handoff.

The slice is deliberately narrow: Cognify should be able to prove the internal RFQ drafting workflow before it opens the RFQ to vendors.

## Problem

The buyer intake slice records that a requisition is ready for RFQ, but it intentionally does not create RFQs. Without this follow-up slice, buyers have an auditable sourcing decision but no workspace for shaping the RFQ package. Jumping directly to vendor invitations would mix two different decisions: what the RFQ contains and which vendors should receive it.

## Goals

- Create or reveal one active draft RFQ from a `ready_for_rfq` sourcing intake review.
- Keep the RFQ linked to the source intake review, requisition, and optional procurement project.
- Let buyers edit draft RFQ scope, response due date, required documents, response instructions, line-item sourcing details, evaluation notes, and internal notes.
- Preserve the requisition as the source of truth for the original business need while allowing RFQ-specific sourcing adjustments.
- Record auditable RFQ draft creation, update, and cancellation events.
- Add a workspace route for draft RFQ review and editing.
- Keep API contracts generated through OpenAPI and consumed by `@cognify/api-client`.
- Include `pnpm build` in final verification because every PR now runs an automated build workflow.

## Non-Goals

- Vendor invitations or invitation status tracking.
- RFQ publishing to vendors.
- Vendor portal access.
- Quotation upload, manual entry, or versioning.
- Quotation comparison, scoring, recommendations, award decisions, award approvals, or PO handoff.
- AI sourcing recommendations.
- Full vendor master management.
- Multi-round RFQ amendment or sealed-bid behavior.

## Actors

- Buyer: creates, edits, and cancels draft RFQs for sourcing work they can manage.
- Admin: can manage tenant RFQ drafts and recover/cancel draft work.
- Requester: can view the underlying requisition through existing requisition surfaces, but does not edit RFQ drafts.
- Vendor: out of scope for this slice.
- System: records audit events and exposes generated API contracts.

## Workflow

The workflow starts from a sourcing intake review in `ready_for_rfq`.

```txt
sourcing intake ready_for_rfq
  -> create/reveal draft RFQ
draft RFQ
  -> edit draft
  -> cancel draft
```

RFQ creation must be idempotent. Repeated create attempts for the same eligible intake review should return the existing active draft instead of creating duplicates.

Allowed RFQ states for this slice:

- `draft`: internal buyer-authored RFQ package, editable by buyer/admin.
- `cancelled`: terminal draft cancellation before vendor invitation or publishing exists.

Future states such as `published`, `inviting`, `closed`, or `awarded` are reserved for later slices and should not be implemented as active workflow behavior here.

## Backend Design

Backend ownership belongs in `apps/api/Domains/Quotation`. The existing `Domains\Quotation\Models\Rfq` model should be hardened from a demo/search placeholder into the durable RFQ workflow record for this slice.

The RFQ record should be linked to:

- tenant;
- sourcing intake review;
- requisition;
- optional procurement project;
- creating buyer or system actor metadata when useful for audit context.

Core draft fields:

- `tenant_id`
- `sourcing_intake_review_id`
- `requisition_id`
- `project_id`
- `number`
- `title`
- `status`
- `scope_summary`
- `response_due_at`
- `response_instructions`
- `required_documents`
- `line_items`
- `evaluation_notes`
- `internal_notes`
- `cancel_reason`
- `cancelled_at`
- timestamps

`required_documents` and `line_items` can be JSON for this slice. `line_items` should be copied from requisition lines at draft creation so buyers can shape the RFQ package without mutating the original requisition. Normalize these later only when invitation, quotation, or evaluation requirements prove item-level reporting or versioning needs.

Domain actions should own behavior:

- `CreateOrRevealRfqDraftFromIntake`: validates tenant, buyer/admin authorization, `ready_for_rfq` intake state, and duplicate active draft handling.
- `UpdateRfqDraft`: updates draft-only fields and rejects cancelled RFQs.
- `CancelRfqDraft`: records cancellation reason and terminal state.

Controllers should adapt HTTP requests to domain actions and resources. Durable workflow rules should not live in controllers.

## API Contract

OpenAPI should define the contract first, then regenerate `packages/api-client`.

Proposed endpoints:

- `POST /api/sourcing/intake-reviews/{review}/rfq`
  Create or reveal the draft RFQ for a `ready_for_rfq` intake review.
- `GET /api/rfqs/{rfq}`
  Show RFQ workspace data with requisition summary, intake summary, project summary, draft fields, permissions, and audit summary.
- `PATCH /api/rfqs/{rfq}`
  Update draft RFQ fields.
- `POST /api/rfqs/{rfq}/cancel`
  Cancel a draft RFQ with a required reason.

Responses should include:

- RFQ identity, number, status, and timestamps;
- source sourcing intake review summary;
- source requisition summary and line-item context;
- optional project summary;
- draft scope, due date, required documents, instructions, RFQ line items, evaluation notes, and internal notes;
- permissions for edit/cancel/future invitation affordances;
- normalized validation, authorization, tenant, and conflict errors.

## Permissions And Tenancy

Only buyers and admins can create, edit, or cancel RFQ drafts.

Every query and mutation must prove:

- the actor is authenticated;
- the actor belongs to the active tenant;
- the RFQ, intake review, requisition, and optional project belong to the active tenant;
- the source intake review is `ready_for_rfq` before draft creation;
- assigned or acting buyers are tenant members when buyer-specific metadata is stored;
- cross-tenant records cannot be inferred through error differences.

Frontend permissions may hide actions, but backend policies and domain actions remain authoritative.

## Audit And Notifications

Audit events should be emitted for:

- `rfq.draft_created`
- `rfq.draft_updated`
- `rfq.draft_cancelled`

No vendor-facing notifications should be emitted in this slice because vendor invitations are out of scope. In-app notifications can be deferred unless the implementation plan identifies an existing buyer-only notification pattern that is low-risk and directly useful.

## Frontend Design

Frontend ownership should remain in `apps/web/features/sourcing` because this is still Epic 5 sourcing work and builds directly on the intake feature.

Suggested structure:

```txt
apps/web/app/(workspace)/sourcing/rfqs/[rfqId]/page.tsx
apps/web/features/sourcing/api/rfq-api.ts
apps/web/features/sourcing/components/rfq-draft-form.tsx
apps/web/features/sourcing/components/rfq-line-items-table.tsx
apps/web/features/sourcing/components/rfq-required-documents-editor.tsx
apps/web/features/sourcing/hooks/use-rfq-draft.ts
apps/web/features/sourcing/hooks/use-rfq-draft-actions.ts
apps/web/features/sourcing/mocks/rfq-handlers.ts
apps/web/features/sourcing/schemas/rfq-draft-schema.ts
apps/web/features/sourcing/tests/rfq-draft-workflow.test.tsx
apps/web/features/sourcing/types/rfq-view-model.ts
apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx
```

Use shadcn/Radix primitives through existing local patterns before adding custom controls. Use TanStack Query for server state, React Hook Form and Zod for draft editing, generated-client wrappers for API calls, and MSW fixtures for focused tests. Production components must not import mock fixtures directly.

The RFQ workspace should use existing shell/workspace patterns and include:

- header with RFQ number, title, status, source requisition, and source intake link;
- source requisition and intake summary;
- editable draft RFQ form;
- RFQ line-item table copied from requisition lines;
- required documents editor;
- response instructions and evaluation notes;
- internal buyer notes;
- future vendor invitation section rendered as a non-actionable placeholder;
- audit or activity summary;
- state-aware actions for save and cancel.

The buyer intake detail page should expose a create/reveal RFQ action when the review is `ready_for_rfq`. After creation it should route to the RFQ workspace.

## Mocked Frontend Workflow

MSW handlers should return OpenAPI-shaped RFQ data and cover:

- create RFQ from a ready intake review;
- reveal existing active draft on repeated create;
- draft workspace loading and populated states;
- validation errors;
- permission denied;
- conflict when the source intake is no longer eligible;
- cancelled RFQ state.

## Error Handling

The API should return normalized error envelopes for:

- validation errors;
- missing tenant context;
- permission denial;
- missing or cross-tenant records;
- intake review not `ready_for_rfq`;
- duplicate active draft edge cases that cannot safely resolve;
- attempts to edit or cancel a cancelled RFQ.

The web UI should present validation summaries, disabled actions for impossible states, conflict recovery that refetches intake/RFQ data, and a clear permission-denied state.

## Testing

API tests:

- buyers/admins can create or reveal RFQ drafts from `ready_for_rfq` intake reviews;
- requesters and approvers cannot create, edit, or cancel RFQ drafts;
- tenant isolation is enforced for RFQs, intake reviews, requisitions, and projects;
- RFQ creation is idempotent per active intake review;
- draft updates persist allowed fields and reject cancelled RFQs;
- cancellation requires a reason and creates a terminal state;
- invalid transitions return conflict errors;
- audit events are written for draft create, update, and cancel actions;
- OpenAPI response permissions and validation shapes are covered.

Web tests:

- intake detail shows create/reveal RFQ action only when allowed;
- create-from-intake routes to the RFQ workspace;
- RFQ workspace renders generated-client-shaped MSW data;
- draft save flow validates, mutates, and invalidates relevant TanStack Query keys;
- validation and conflict errors are visible and recoverable;
- cancelled RFQ renders read-only state.

Contract and verification commands:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=Rfq
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test -- sourcing
pnpm build
```

Final implementation plans may add broader checks when shared surfaces change, but `pnpm build` should remain part of final PR readiness verification.
