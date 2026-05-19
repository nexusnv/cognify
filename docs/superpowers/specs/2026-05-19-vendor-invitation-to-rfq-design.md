# Vendor Invitation To RFQ Design

## Status

- Status: Draft for review
- Date: 2026-05-19
- Release scope: P1 Epic 5, slice 3 only
- Related roadmap: `docs/02-release-management/2026-05-15-P1-Epics.md`, `docs/01-product/feature-roadmap.md`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Previous slices:
  - `docs/superpowers/specs/2026-05-19-buyer-intake-sourcing-review-design.md`
  - `docs/superpowers/specs/2026-05-19-rfq-draft-creation-design.md`

## Purpose

This slice lets buyers invite existing tenant vendors to a draft RFQ and track the invitation lifecycle inside the RFQ workspace. It converts the RFQ draft from an internal buyer-authored package into a buyer-managed vendor outreach record without introducing external vendor portal access, quotation capture, comparison, scoring, award decisions, or purchase order handoff.

The design deliberately uses the existing lightweight `Vendor` model as a picker source. It does not build a standalone vendor-management module. Full vendor profile management belongs in a later slice when vendor lifecycle requirements are clearer.

## Problem

The RFQ draft slice creates a durable sourcing package, but buyers still need a controlled way to select vendors, record outreach, track invitation status, and preserve the audit trail. If this behavior is left to comments or external spreadsheets, Cognify loses the source-of-truth chain between requisition, intake decision, RFQ package, vendor response, quotation evaluation, and award governance.

At the same time, building vendor portal access or full vendor management now would couple this slice to Epic 6 before invitation contracts stabilize. This slice should establish the internal invitation record and buyer workflow first.

## Goals

- Let buyers and admins select existing active tenant vendors for an RFQ.
- Create RFQ invitation records with vendor, contact, message, response due date, and status.
- Track invitation status inside the RFQ workspace.
- Support resend, cancel, and internal status updates with audit events.
- Prevent duplicate active invitations for the same RFQ and vendor.
- Keep API contracts generated through OpenAPI and consumed through `@cognify/api-client`.
- Preserve a clean handoff for Epic 6 vendor portal and quotation capture.

## Non-Goals

- Standalone vendor directory or vendor profile management.
- Creating vendors from the RFQ invitation workflow.
- External vendor authentication or vendor portal access.
- Invitation-token generation, secure public RFQ links, or vendor-facing sessions.
- Actual email delivery or external notification delivery.
- Quotation upload, manual quotation entry, quotation versioning, comparison, scoring, awards, award approval, or PO handoff.
- AI vendor recommendations or sourcing optimization.

## Actors

- Buyer: selects vendors, creates invitations, resends invitations, cancels invitations, and tracks status from the RFQ workspace.
- Admin: can manage tenant RFQ invitations and recover or cancel work.
- Requester: can view relevant requisition/RFQ context only where existing permissions allow, but does not manage RFQ invitations.
- Vendor: represented by an internal vendor record in this slice; external vendor login and responses are out of scope.
- System: records audit events and exposes invitation state for later vendor portal and quotation workflows.

## Workflow

The workflow starts from an RFQ in `draft` status.

```txt
draft RFQ
  -> select existing tenant vendors
  -> create invitations
  -> record sent state
  -> resend, cancel, or update status
```

Invitation states are:

```txt
pending
  -> sent
  -> cancelled
sent
  -> acknowledged
  -> declined
  -> expired
  -> cancelled
```

For this slice, `sent` means the buyer has recorded the invitation as sent inside Cognify. It does not imply email delivery or vendor portal access. Epic 6 can later attach token delivery, vendor portal access, and quotation submission to these invitation records.

Workflow rules:

- Only `draft` RFQs can receive invitations.
- Only buyers and admins can create, resend, cancel, or update invitation status.
- Only active vendors in the current tenant can be invited.
- One vendor can have at most one active invitation per RFQ.
- Cancelled invitations are terminal and require a reason.
- Resending updates `sent_at` and writes an audit event; it does not create a duplicate invitation.
- Invitation records remain linked to the RFQ, vendor, tenant, and source procurement context.

## Backend Design

Backend ownership belongs in `apps/api/Domains/Quotation` because invitations are part of the RFQ workflow. The existing `apps/api/Domains/Vendor/Models/Vendor.php` model is a supporting data source.

Add a durable RFQ invitation model named `RfqInvitation`, linked to:

- tenant;
- RFQ;
- vendor;
- actor metadata for send, resend, cancel, and status changes when useful for audit context.

Core fields:

- `tenant_id`
- `rfq_id`
- `vendor_id`
- `status`
- `contact_name`
- `contact_email`
- `message`
- `response_due_at`
- `sent_at`
- `acknowledged_at`
- `declined_at`
- `expired_at`
- `cancelled_at`
- `cancel_reason`
- `metadata`
- timestamps

Domain actions should own behavior:

- create invitations for one or more selected vendors;
- resend an invitation;
- cancel an invitation with a required reason;
- update internal invitation status for acknowledged, declined, or expired states;
- enforce duplicate active invitation prevention.

Controllers should only adapt HTTP requests to domain actions and resources. Durable workflow rules must not live in controllers.

## API Contract

OpenAPI should define the contract first, then regenerate `packages/api-client`.

Proposed endpoints:

- `GET /api/rfqs/{rfq}/invitations`
  List invitations for an RFQ with vendor summary, contact fields, status, timestamps, and permissions.
- `POST /api/rfqs/{rfq}/invitations`
  Create invitations for selected existing vendors. The request accepts vendor IDs, optional contact overrides, message, and response due date.
- `POST /api/rfq-invitations/{invitation}/resend`
  Mark an invitation as resent, update `sent_at`, and write audit.
- `POST /api/rfq-invitations/{invitation}/cancel`
  Cancel an invitation with a required reason.
- `PATCH /api/rfq-invitations/{invitation}/status`
  Update internal status to `acknowledged`, `declined`, or `expired` where allowed.
- `GET /api/vendors?status=active&category=...`
  Minimal tenant-scoped vendor picker endpoint. This is not a vendor directory page.

Responses should include:

- invitation identity, status, contact fields, message, due date, and timestamps;
- RFQ summary;
- vendor summary;
- permissions for resend, cancel, and status update;
- normalized validation, authorization, tenant, not-found, and conflict errors.

The vendor picker response should include only:

- vendor ID;
- name;
- category;
- status;
- risk rating;
- default contact metadata when present.

Frontend code should consume generated endpoints through `@cognify/api-client`. App-specific view models may derive from generated schemas, but app code must not duplicate response contracts by hand.

## Permissions And Tenancy

Only buyers and admins can manage RFQ invitations.

Every query and mutation must prove:

- the actor is authenticated;
- the actor belongs to the active tenant;
- the RFQ belongs to the active tenant;
- the vendor belongs to the active tenant;
- the RFQ source intake, requisition, and optional project remain tenant-consistent;
- cross-tenant records cannot be inferred through error differences.

Frontend permissions may hide actions, but backend policies and domain actions remain authoritative.

## Audit And Notifications

Audit events should be emitted for:

- `rfq_invitation.created`
- `rfq_invitation.sent`
- `rfq_invitation.resent`
- `rfq_invitation.cancelled`
- `rfq_invitation.acknowledged`
- `rfq_invitation.declined`
- `rfq_invitation.expired`

No vendor-facing email, token, or external notification should be emitted in this slice. In-app buyer/admin notifications can be deferred unless the implementation plan identifies a narrow existing notification path that is directly useful and low risk.

## Frontend Design

Frontend ownership stays in `apps/web/features/sourcing` because vendor invitations are part of the RFQ sourcing workflow.

Suggested structure:

```txt
apps/web/features/sourcing/api/vendor-api.ts
apps/web/features/sourcing/api/rfq-invitation-api.ts
apps/web/features/sourcing/components/rfq-invitation-panel.tsx
apps/web/features/sourcing/components/rfq-invitation-dialog.tsx
apps/web/features/sourcing/components/rfq-invitation-status-badge.tsx
apps/web/features/sourcing/components/vendor-picker.tsx
apps/web/features/sourcing/hooks/use-rfq-invitations.ts
apps/web/features/sourcing/hooks/use-rfq-invitation-actions.ts
apps/web/features/sourcing/hooks/use-vendor-picker.ts
apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts
apps/web/features/sourcing/mocks/vendor-fixtures.ts
apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx
```

The RFQ workspace should replace the current vendor invitation placeholder with a real invitation panel.

The panel should include:

- invitation count and status summary;
- create invitation action when the RFQ is draft and permissions allow;
- invitation table or mobile cards with vendor, contact, status, response due date, last sent time, and actions;
- empty state when no vendors have been invited;
- empty vendor-picker state when the tenant has no active vendors;
- clear future-state copy that vendor portal access arrives in a later slice.

Use shadcn/Radix primitives and existing Cognify sourcing patterns before adding custom controls. Keep Cognify-specific composition inside `apps/web/features/sourcing`; do not move business UI into `packages/ui`.

## Mocked Frontend Workflow

MSW handlers should return OpenAPI-shaped data and cover:

- vendor picker list;
- RFQ invitation list;
- create invitation success;
- duplicate active invitation conflict;
- resend success;
- cancel success;
- permission denied;
- non-draft RFQ read-only state;
- empty vendor picker state.

Production components must not import mock fixtures directly.

## Error Handling

The API should return normalized error envelopes for:

- validation errors;
- missing tenant context;
- permission denial;
- missing or cross-tenant records;
- attempting to invite inactive or cross-tenant vendors;
- attempting to invite vendors to a non-draft RFQ;
- duplicate active invitation conflicts;
- attempts to resend or cancel terminal invitations.

The web UI should present validation summaries, disabled actions for impossible states, conflict recovery that refetches invitation data, and a clear permission-denied or read-only state.

## Testing

API tests:

- buyers/admins can list active vendors for the picker;
- requesters and approvers cannot manage RFQ invitations;
- buyers/admins can create invitations for active tenant vendors on draft RFQs;
- duplicate active invitation for the same RFQ/vendor returns `409 Conflict`;
- cross-tenant RFQ/vendor/invitation access is blocked;
- invitations cannot be created for cancelled or non-draft RFQs;
- resend updates `sent_at` and writes audit without duplicating records;
- cancel requires a reason and moves the invitation to `cancelled`;
- internal status updates support acknowledged, declined, and expired;
- audit events are written for create, sent, resend, cancel, and status transitions;
- OpenAPI response permissions and validation shapes are covered.

Web tests:

- RFQ workspace renders invitation panel from generated-client-shaped MSW data;
- buyer can search/select vendors and create invitations;
- duplicate conflict error is visible and recoverable;
- cancel flow requires reason and refreshes invitation list;
- non-draft RFQ shows read-only invitation state;
- empty vendor picker state is clear and does not imply vendor management exists;
- production components do not import mock fixtures.

Contract and verification commands for the implementation plan:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=RfqInvitation
cd apps/api && php artisan test --filter=Vendor
pnpm --filter @cognify/web test -- sourcing
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
pnpm build
git diff --check
```

## Exit Criteria

- Buyers can create and manage RFQ invitations from the RFQ workspace using existing tenant vendors.
- Invitation status, due date, vendor, and contact context are visible without leaving the RFQ.
- Invitation lifecycle changes are tenant-safe, permission-checked, and auditable.
- The workflow leaves a clean handoff for Epic 6 vendor portal and quotation capture.
- No standalone vendor directory, vendor portal, quotation capture, or email delivery is introduced.
