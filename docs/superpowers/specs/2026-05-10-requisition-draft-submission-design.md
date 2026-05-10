# Cognify Requisition Draft and Submission Design Spec

## Changelog

- 2026-05-10: Initial workflow-first design spec for the first Cognify feature slice.

## Status

Proposed for implementation.

## Feature

Requisition Draft and Submission.

This is the first product feature slice for Cognify. It establishes the minimum procurement workflow spine:

```txt
Tenant/Auth baseline
  -> Requisition draft
  -> Submit requisition
  -> Requisition detail workspace
  -> Basic approval readiness
```

This spec follows `docs/05-runbooks/feature-development.md` and the Cognify monorepo boundaries in `AGENTS.md`.

## Product Goal

Give a requester a reliable way to create, save, review, and submit a procurement requisition inside a tenant-scoped workspace. The submitted requisition becomes the root object for approval, quotation collection, comparison, evidence, AI scoring, and reporting.

## Non-Goals

- Full approval routing.
- Quotation upload.
- Vendor comparison.
- OCR or AI scoring.
- Budget integrations.
- Multi-currency pricing logic beyond capturing a currency code.
- Sophisticated tenant administration UI.

## Users and Actors

| Actor | Description | Feature Permissions |
| --- | --- | --- |
| Requester | Creates procurement requests for goods or services. | Create, edit own drafts, submit own drafts, view own requisitions. |
| Buyer | Reviews submitted requisitions for sourcing readiness. | View submitted requisitions. Future: manage quotations. |
| Approver | Reviews business justification and spend request. | View submitted requisitions. Future: approve or reject. |
| Admin | Manages tenant-level access and can inspect all requisitions. | View all tenant requisitions. |
| System | Records audit events and enforces state transitions. | Internal only. |

## Workflow

```txt
Draft created
  -> Draft updated
  -> Draft submitted
  -> Pending approval
```

### States

| State | Meaning | Allowed Actions |
| --- | --- | --- |
| `draft` | Requester is still composing the requisition. | Update, add/remove line items, submit, discard later. |
| `submitted` | Requester has submitted the requisition for review. | View only for requester. Buyer/approver visibility begins. |
| `pending_approval` | Alias or next approval state after submission if approval routing is enabled. | Reserved for approval feature. |

Implementation note: the first slice may persist only `draft` and `submitted`. It should name the transition so `submitted -> pending_approval` can be added without rewriting the API.

### Transitions

| Transition | Trigger | Guardrails | Audit Event |
| --- | --- | --- | --- |
| Create draft | Requester starts a requisition. | Authenticated tenant member. | `requisition.created` |
| Update draft | Requester saves form changes. | Owner or admin, state is `draft`. | `requisition.updated` |
| Submit draft | Requester clicks submit. | Required fields valid, at least one line item, state is `draft`. | `requisition.submitted` |

### Validation Rules

Minimum fields required for submit:

- Title.
- Business justification.
- Needed-by date.
- At least one line item.
- Each line item has name, quantity, unit of measure, estimated unit price, and currency.

Fields allowed to remain optional in v1:

- Project.
- Department.
- Cost center.
- Preferred vendors.
- Attachments.
- Delivery location.

## UX Strategy

The first UX should feel like an operational SaaS workspace, not a marketing page or decorative dashboard. The design should be quiet, dense enough for repeated use, and optimized for scanning, editing, validation, and workflow confidence.

### UI/UX Pro Max Iteration Notes

The UX direction applies these high-priority checks:

- Accessibility: visible labels, keyboard navigation, focus states, semantic buttons, non-color-only status indicators.
- Touch and interaction: 44px minimum primary controls, loading feedback for save/submit, no hover-only actions.
- Layout and responsive: no horizontal scroll on mobile, stable table/list dimensions, detail-first mobile flow.
- Forms and feedback: inline validation, error summary, save state, unsaved-change confirmation, clear recovery paths.
- Navigation: deep links for list, create, and detail; predictable back behavior; active nav states.
- Data presentation: status badges include text and icon, prices use tabular figures, empty states include one clear action.

## Information Architecture

### Routes

| Route | Purpose |
| --- | --- |
| `/dashboard` | Shows high-level procurement work queue summary. |
| `/requisitions` | Requisition list workspace. |
| `/requisitions/new` | Create requisition draft. |
| `/requisitions/[requisitionId]` | Requisition detail workspace. |
| `/requisitions/[requisitionId]/edit` | Edit draft requisition. Optional if edit can happen inline on detail. |

Implementation can place routes under the existing App Router groups as appropriate:

- `apps/web/app/(dashboard)`
- `apps/web/app/(workspace)`

The URL should be clean and product-oriented. Avoid exposing internal state-machine names in route paths.

### Navigation Touchpoints

Primary navigation:

- Dashboard.
- Requisitions.
- Approvals.
- Vendors.
- Reporting.

Requisition local navigation:

- Overview.
- Line items.
- Activity.
- Approval readiness.

For v1, local navigation can be section anchors or tabs on the detail page. Do not create empty pages.

## Screen Specs

### Dashboard Additions

Purpose: show that requisitions are the starting work queue.

Visual touchpoints:

- Work queue summary row:
  - Drafts.
  - Submitted.
  - Needs attention.
  - Average submission age later.
- Primary action: `New requisition`.
- Secondary link: `View requisitions`.
- Small activity list for recent requisition events when data exists.

States:

- Empty: "No requisitions yet" with `New requisition`.
- Loading: skeleton for summary cards and list.
- Error: inline recoverable panel with retry.

### Requisition List

Purpose: operational table for finding and resuming requisitions.

Desktop layout:

- Page title: `Requisitions`.
- Primary action button: `New requisition`.
- Filter bar:
  - Search.
  - Status.
  - Owner.
  - Needed-by date range.
- Table columns:
  - Requisition number.
  - Title.
  - Status.
  - Requester.
  - Needed by.
  - Estimated total.
  - Updated.
  - Row actions.

Mobile layout:

- Compact filter button opens a sheet.
- Requisitions render as list rows with title, status, needed-by date, and amount.
- Primary action remains visible near top and in a sticky bottom action only if it does not conflict with navigation.

States:

- Empty no data: friendly first-run state with `New requisition`.
- Empty filtered: "No requisitions match these filters" with `Clear filters`.
- Loading: table skeleton with fixed row height.
- Error: retry panel.
- Permission-limited: hide restricted records rather than rendering disabled rows.

### New Requisition Form

Purpose: create a draft with enough structure to submit.

Form layout:

- Header:
  - Title: `New requisition`.
  - Save state: `Unsaved`, `Saving`, `Saved`, or `Save failed`.
  - Actions: `Save draft`, `Submit`.
- Main form sections:
  - Request summary.
  - Business justification.
  - Line items.
  - Optional context.
- Right rail on desktop:
  - Submission checklist.
  - Estimated total.
  - Draft activity.
- Mobile:
  - Single-column form.
  - Submission checklist collapses under header.
  - Actions use sticky bottom bar after user scrolls past header.

Fields:

| Field | Type | Required for Draft | Required for Submit |
| --- | --- | --- | --- |
| Title | Text input | Yes | Yes |
| Business justification | Textarea | No | Yes |
| Needed-by date | Date | No | Yes |
| Department | Select | No | No |
| Project | Select/search | No | No |
| Cost center | Text/select | No | No |
| Delivery location | Textarea | No | No |
| Currency | Select | No | Yes if line items exist |

Line item fields:

| Field | Type | Required for Submit |
| --- | --- | --- |
| Item name | Text input | Yes |
| Description | Textarea or expandable field | No |
| Quantity | Number input | Yes |
| Unit | Select | Yes |
| Estimated unit price | Currency input | Yes |
| Currency | Select | Yes |

Interaction rules:

- Save draft can run with minimal title.
- Submit validates all required submit fields.
- Submit button shows loading state and is disabled while submitting.
- On validation failure, show an error summary above the form and inline errors near fields.
- Focus moves to the first invalid field after submit failure.
- Warn before leaving with unsaved changes.
- Add/remove line item controls must be keyboard-accessible.
- Destructive remove action should require confirmation only when the line item has meaningful entered data.

### Requisition Detail Workspace

Purpose: durable workspace page after draft creation or submission.

Header:

- Requisition number.
- Title.
- Status badge.
- Estimated total.
- Needed-by date.
- Primary action based on state:
  - Draft: `Edit draft` or inline edit.
  - Draft: `Submit`.
  - Submitted: no requester primary action.

Main sections:

- Overview.
- Line items.
- Business justification.
- Submission checklist.
- Activity timeline.
- Approval readiness placeholder.

Right panel:

- Status summary.
- Key dates.
- Ownership.
- Permission-aware actions.

Activity timeline:

- Created.
- Draft updated.
- Submitted.
- Future approval events.

Status badge rules:

- Must include text.
- May include icon.
- Must not rely on color alone.
- Use restrained semantic colors.

### Submit Confirmation

Purpose: avoid accidental submission while keeping the flow efficient.

Use a dialog or sheet:

- Title: `Submit requisition?`
- Body: explain that submitted requisitions are locked for requester edits in v1.
- Checklist:
  - Required fields complete.
  - Line items complete.
  - Estimated total visible.
- Actions:
  - Primary: `Submit requisition`.
  - Secondary: `Keep editing`.

On success:

- Navigate to detail page.
- Show non-blocking success toast or inline confirmation.
- Activity timeline includes submission event.

### Command Palette Touchpoints

Add commands when command palette is implemented:

- New requisition.
- Open requisitions.
- Search requisitions.
- Open current requisition activity.

Do not block the first feature on full command palette functionality.

## Visual Direction

### Style

Operational SaaS, restrained, information-first.

Use:

- High-contrast neutral surfaces.
- One primary accent for actions.
- Semantic status colors only for state.
- 8px or smaller radius for cards/panels unless existing primitives require otherwise.
- Lucide icons for actions and status accents.

Avoid:

- Marketing hero composition inside the app.
- Decorative gradient backgrounds.
- Oversized cards for dense operational data.
- Color-only state communication.
- Nested cards.

### Layout

Desktop:

- Persistent left navigation.
- Sticky page header where useful.
- Content max width for forms.
- Tables use full available width with stable column sizing.
- Detail pages may use main content plus right rail.

Mobile:

- Single-column content.
- Filters in a sheet.
- Avoid horizontally scrolling tables; use list rows.
- Sticky action bar only for active form workflows.

### Typography

- Body text minimum 16px on mobile.
- Use medium weight for labels and section headings.
- Use tabular figures for totals, quantities, and prices.
- Keep headings compact inside app panels.
- Avoid viewport-scaled font sizes.

### Motion

- Motion should explain state changes only.
- Use 150-300ms transitions for dialogs, sheets, validation reveal, and row insertion.
- Respect reduced motion.
- Avoid animating width, height, top, or left.

## API Contract

OpenAPI source:

- `apps/api/storage/openapi/openapi.json`

Generated client:

- `packages/api-client/src/generated/*`

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/requisitions` | List tenant-visible requisitions. |
| `POST` | `/api/requisitions` | Create draft requisition. |
| `GET` | `/api/requisitions/{requisitionId}` | Read requisition detail. |
| `PATCH` | `/api/requisitions/{requisitionId}` | Update draft requisition. |
| `POST` | `/api/requisitions/{requisitionId}/submit` | Submit draft requisition. |
| `GET` | `/api/requisitions/{requisitionId}/activity` | Read activity timeline. Can be folded into detail response for v1. |

### Resource Shape

`Requisition`:

- `id`
- `number`
- `tenantId`
- `title`
- `status`
- `businessJustification`
- `neededByDate`
- `department`
- `projectId`
- `costCenter`
- `deliveryLocation`
- `currency`
- `estimatedTotal`
- `requester`
- `lineItems`
- `createdAt`
- `updatedAt`
- `submittedAt`
- `permissions`

`RequisitionLineItem`:

- `id`
- `name`
- `description`
- `quantity`
- `unit`
- `estimatedUnitPrice`
- `currency`
- `estimatedLineTotal`

`RequisitionPermissions`:

- `canUpdate`
- `canSubmit`
- `canViewActivity`

`ActivityEvent`:

- `id`
- `type`
- `message`
- `actor`
- `occurredAt`
- `metadata`

### Error Shape

Use a consistent API error shape:

- `message`
- `code`
- `errors` keyed by field for validation failures.

Validation errors should map cleanly to frontend form fields.

## Backend Design

Primary domain:

- `apps/api/Domains/Requisition`

Supporting cross-cutting areas:

- `apps/api/app/Tenancy`
- `apps/api/app/Auth`
- `apps/api/app/Audit`

Recommended domain files for this slice:

```txt
apps/api/Domains/Requisition/
  Actions/
    CreateRequisitionDraft.php
    UpdateRequisitionDraft.php
    SubmitRequisition.php
  Data/
    RequisitionData.php
    RequisitionLineItemData.php
  Events/
    RequisitionCreated.php
    RequisitionUpdated.php
    RequisitionSubmitted.php
  Models/
    Requisition.php
    RequisitionLineItem.php
  Policies/
    RequisitionPolicy.php
  States/
    RequisitionStatus.php
  Support/
    RequisitionNumberGenerator.php
```

Only create directories when adding real files.

### Persistence

Tables:

- `tenants`
- `tenant_user` or equivalent membership table.
- `requisitions`
- `requisition_line_items`
- `audit_events` or use the selected activity log package consistently.

Minimum `requisitions` columns:

- `id`
- `tenant_id`
- `requester_id`
- `number`
- `title`
- `status`
- `business_justification`
- `needed_by_date`
- `department`
- `project_id`
- `cost_center`
- `delivery_location`
- `currency`
- `estimated_total`
- `submitted_at`
- timestamps

### Policy Rules

- Tenant member can create requisitions for their current tenant.
- Requester can update own requisition only while `draft`.
- Admin can view tenant requisitions.
- Buyer and approver can view submitted requisitions.
- Submit requires owner or admin and `draft` state.

### Audit Rules

Audit events:

- `requisition.created`
- `requisition.updated`
- `requisition.submitted`

Audit payload should include:

- Tenant.
- Requisition ID and number.
- Actor.
- Previous status and next status for transitions.
- Changed fields for update if practical.

## Frontend Design

Primary feature path:

- `apps/web/features/requisitions`

Recommended files:

```txt
apps/web/features/requisitions/
  api/
    requisitions-api.ts
  components/
    requisition-status-badge.tsx
    requisition-summary-panel.tsx
    requisition-activity-timeline.tsx
    submit-requisition-dialog.tsx
  forms/
    requisition-form.tsx
    requisition-line-items-editor.tsx
  hooks/
    use-requisition.ts
    use-requisitions.ts
    use-save-requisition-draft.ts
    use-submit-requisition.ts
  mocks/
    requisitions-handlers.ts
    requisitions-fixtures.ts
  schemas/
    requisition-form-schema.ts
  tables/
    requisitions-table.tsx
  tests/
    requisition-form.test.tsx
    requisitions-workflow.test.tsx
  types/
    requisition-view-model.ts
  utils/
    requisition-totals.ts
  workflows/
    requisition-workflow.ts
```

Use generated API types where available. Local view models are allowed when they adapt generated types for UI rendering.

### Frontend State Rules

- React Query owns server state.
- Form state stays local to the form.
- Zustand is only justified if cross-route workspace state appears.
- Avoid global state for draft form data unless autosave requires it.

### MSW Rules

MSW should cover:

- Empty requisition list.
- Draft list with one or more records.
- Create draft success.
- Update draft success.
- Submit success.
- Validation error on submit.
- Permission denied.
- Network/server failure.

Handlers must return OpenAPI-shaped responses.

## Accessibility Requirements

- Every input has a visible label.
- Error summary links to invalid fields.
- First invalid field receives focus after failed submit.
- Dialog focus is trapped and returns to trigger on close.
- All icon-only controls have `aria-label`.
- Status is readable without color.
- Activity timeline is a semantic list.
- Submit loading state is announced with accessible text.
- Keyboard users can add, edit, and remove line items.

## Testing Strategy

### Frontend

- Form validation tests.
- Add/remove line item tests.
- Save draft mutation test with MSW.
- Submit success path test.
- Submit validation error test.
- Requisition list empty and populated states.

### Backend

- Create draft feature test.
- Update draft feature test.
- Submit draft feature test.
- Cannot update submitted requisition.
- Cannot access another tenant's requisition.
- Audit events emitted for create/update/submit.
- Policy tests for requester, buyer, approver, admin.

### Contract

- OpenAPI updated for all endpoints and schemas.
- Orval regenerated.
- Frontend hooks compile against generated types.

## Implementation Plan

1. Confirm baseline toolchain works locally.
2. Add tenant/auth minimum backend model and policy scaffolding required for requisitions.
3. Define OpenAPI contract for requisition list/create/detail/update/submit.
4. Generate API client.
5. Build MSW fixtures and frontend hooks from the contract.
6. Build requisition list, new form, detail workspace, status badge, submit dialog, and activity timeline.
7. Implement Laravel Requisition domain models, actions, policies, routes, resources, and tests.
8. Replace broad MSW development paths with real API integration.
9. Run verification commands and document any blockers.

## Verification Commands

Frontend:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

Contract:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm typecheck
```

Backend:

```bash
cd apps/api
php artisan test
php artisan route:list --path=api
```

Full shared check before PR:

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```

## Open Questions

- Should submitted requisitions lock all requester edits in v1, or allow withdrawal/edit before buyer review?
- Should requisition numbers be tenant-scoped sequential numbers or globally unique prefixed IDs?
- Is department/cost center free text for v1 or selected from tenant-managed reference data?
- Should activity timeline be backed by Spatie activitylog immediately or a small first-party audit table?
- Should `/requisitions/[id]/edit` exist, or should draft editing happen inline on the detail page?

## Acceptance Criteria

- A tenant member can create a requisition draft.
- A requester can save draft changes and line items.
- A requester can submit a valid draft.
- Submitted requisitions are visible in list and detail screens.
- Required validation errors are visible, field-specific, and accessible.
- Requisition activity shows create/update/submit events.
- API contract documents the feature endpoints.
- Generated API client includes requisition operations and schemas.
- Backend tests cover state, policy, tenant, and audit behavior.
- Frontend tests cover form, list, detail, and submit interaction states.
