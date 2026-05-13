# Workflow UI Primitives Design

## Changelog

- 2026-05-13: Initial delegated design spec for the next P0 slice after Tenant/Auth/Access, Audit/API Contract, and App Shell Foundation.

## Status

Draft for user review. The user delegated design choices for this slice and asked that this document and the implementation plan remain uncommitted.

## Context

The completed P0 foundations give Cognify tenant-aware identity, audit/API contract conventions, an authenticated app shell, a record workspace layout, and a first requisition workflow. The next roadmap item is **Workflow UI Primitives**, covering:

- Global Right Panel System.
- Data Table Foundation.
- Form and Validation Foundation.
- Status Badge and Workflow State System.
- Activity Timeline Primitive.

The current requisition feature already contains local versions of most of these patterns:

- `apps/web/features/requisitions/tables/requisitions-table.tsx`
- `apps/web/features/requisitions/forms/requisition-form.tsx`
- `apps/web/features/requisitions/components/requisition-status-badge.tsx`
- `apps/web/features/requisitions/components/requisition-activity-timeline.tsx`
- inert shell host at `apps/web/components/shell/right-panel-host.tsx`

This slice should formalize those patterns into app-level Cognify workflow primitives without promoting procurement-specific behavior into `packages/ui`.

## Product Goal

Give every future Cognify workflow a consistent, accessible, tenant-safe way to render dense work queues, state badges, validation-heavy forms, audit-visible timelines, and contextual right-side panels.

## Delegated Design Decision

The user will not be available for approval gates and explicitly delegated the design choices. This spec therefore records the alternatives and makes the product and technical decisions directly. The recommended direction is **Option A: app-level workflow primitives proven through requisitions**.

## Scope

This slice delivers frontend workflow foundations in `apps/web`:

- A usable global right panel provider and panel surface connected to the app shell host.
- A generic but app-owned data table component for operational procurement work queues.
- A form field and validation summary layer that maps Zod/client errors and API validation fields into accessible UI.
- A workflow status badge system with explicit labels, tones, icons, and descriptions.
- A reusable activity timeline primitive that renders audit-shaped events without hard-coding requisition labels.
- Requisition workflow migration onto the new primitives as the proving implementation.
- Focused unit/component tests for the primitives and requisition regression tests.

This slice does not deliver:

- Command palette behavior.
- Global search.
- Notification center.
- Saved table views.
- Column resizing or virtualization.
- Bulk row actions.
- Backend endpoint changes.
- OpenAPI changes.
- New shared packages.
- A generic design system in `packages/ui`.

## Recommended Approach

### Option A: App-Level Workflow Primitives Proven Through Requisitions

Create primitives under `apps/web/components` and migrate requisition list, form, status badge, activity timeline, and detail quick-view behavior to them.

This is the recommended approach. It gives upcoming P1 requisition, approval, RFQ, vendor, quotation, and award workflows a stable UI foundation while respecting the boundary that Cognify-specific workflow composition belongs in `apps/web`.

### Option B: Promote Workflow Primitives Into `packages/ui`

Move tables, forms, status badges, and timeline components into the shared UI package.

This is premature. These components carry Cognify vocabulary, procurement workflow states, audit semantics, and operational layout behavior. `packages/ui` should stay limited to reusable shadcn/Radix primitives and framework-neutral helpers.

### Option C: Leave Feature-Local Components In Each Domain

Keep requisition-specific components and let future features copy or invent their own patterns.

This has the lowest immediate cost, but it would create inconsistent table, form, badge, panel, and timeline behavior across approvals, quotations, vendors, and awards. P0 is the right time to establish these primitives before more workflows arrive.

## Architecture

All new code stays in `apps/web`.

Planned structure:

```txt
apps/web/components/data-table/
  data-table.tsx
  data-table-empty-state.tsx
  data-table-types.ts
  use-data-table-state.ts
  data-table.test.tsx

apps/web/components/forms/
  form-error-summary.tsx
  form-field.tsx
  validation-errors.ts
  forms.test.tsx

apps/web/components/right-panel/
  right-panel-provider.tsx
  right-panel-root.tsx
  right-panel-trigger.tsx
  right-panel-types.ts
  right-panel.test.tsx

apps/web/components/workflow/
  activity-timeline.tsx
  status-badge.tsx
  workflow-state.ts
  workflow-primitives.test.tsx
```

Existing shell integration point:

```txt
apps/web/components/shell/right-panel-host.tsx
apps/web/components/shell/app-shell.tsx
```

Requisition proof points:

```txt
apps/web/features/requisitions/tables/requisitions-table.tsx
apps/web/features/requisitions/forms/requisition-form.tsx
apps/web/features/requisitions/components/requisition-status-badge.tsx
apps/web/features/requisitions/components/requisition-activity-timeline.tsx
apps/web/features/requisitions/workflows/requisition-detail-page.tsx
apps/web/features/requisitions/workflows/requisition-list-page.tsx
apps/web/features/requisitions/tests/requisitions-workflow.test.tsx
```

## Workflow Map

### Actors

| Actor | Relationship to This Slice |
| --- | --- |
| Requester | Uses forms, tables, status badges, timelines, and quick panels in requisition workflows. |
| Buyer | Later consumes the same primitives for intake, sourcing, RFQ, and quotation work queues. |
| Approver | Later consumes the same primitives for approval queues, decision forms, and activity trails. |
| Auditor | Relies on consistent timeline rendering and status language. |
| System | Supplies API validation fields and audit event records. |

### UI Flow

```txt
Authenticated route
  -> AppShell
  -> feature workflow page
  -> app-owned workflow primitives
  -> typed hooks backed by generated client or MSW
```

### Right Panel Flow

```txt
Feature component
  -> useRightPanel().openPanel(panel)
  -> RightPanelProvider stores active panel
  -> RightPanelHost renders RightPanelRoot
  -> user closes via button, Escape, overlay, or route change
```

The right panel is contextual UI chrome, not a persistence boundary. Feature hooks still own data loading, mutations, permissions, and API errors.

### Data Table Flow

```txt
Feature list hook
  -> DataTable rows/columns/state
  -> desktop table or mobile list fallback
  -> feature-owned row actions and filters
```

The table primitive renders state and interaction patterns. It does not fetch data, own business filters, or know tenant rules.

### Form Flow

```txt
Feature form state
  -> Zod/client validation or API validation response
  -> normalizeValidationErrors()
  -> FormErrorSummary + FormField
  -> focus first invalid field
```

The form primitive improves consistency. It does not choose business validation rules.

### Timeline Flow

```txt
Feature audit/activity hook
  -> audit-shaped events
  -> optional action presentation map
  -> ActivityTimeline
```

The timeline uses action keys and metadata for display, but the source of truth remains the backend audit trail.

## Primitive Specifications

### Global Right Panel System

The right panel system provides one shared contextual panel surface for feature workflows.

It must support:

- open, replace, and close operations;
- title, description, icon, content, footer, and size;
- close button with an accessible name;
- Escape-to-close;
- overlay click close;
- body scroll locking while open;
- route-change close behavior;
- `aria-modal`, `role="dialog"`, `aria-labelledby`, and `aria-describedby`;
- stable host integration through `RightPanelHost`;
- no panel content rendered when closed.

Panel sizes:

| Size | Width |
| --- | --- |
| `sm` | `24rem` |
| `md` | `32rem` |
| `lg` | `40rem` |

First requisition use:

- Add an `Open details panel` action to requisition list rows.
- Panel shows requisition number, status, requester, needed-by date, estimated total, and two links: `Open workspace` and `Edit draft` when allowed.

Non-goals:

- nested panels;
- global panel history;
- server-driven panel content;
- persisted panel state after reload.

### Data Table Foundation

The data table primitive is an operational list renderer, not a data-fetching abstraction.

It must support:

- typed columns;
- optional column alignment;
- optional column width classes;
- optional `hideOnMobile`;
- row key function;
- row action slot;
- empty state;
- loading skeleton rows;
- error state with retry action;
- desktop table layout;
- mobile list fallback;
- accessible table caption;
- `aria-sort` for sorted columns;
- stable row heights and no horizontal mobile overflow.

Sorting:

- The primitive exposes sort buttons and state.
- Feature hooks decide whether sorting is local or remote.
- Initial requisition migration can keep search/status filtering in `RequisitionListPage` and use table sort state only for UI readiness.

Pagination:

- The primitive accepts optional pagination metadata and callbacks.
- Initial requisition migration may show pagination summary from existing API meta without implementing page navigation if no page-changing endpoint behavior exists in the current hook.

### Form and Validation Foundation

The form foundation standardizes labels, descriptions, inline errors, error summaries, and API validation mapping.

It must support:

- `FormField` with `label`, `htmlFor`, optional description, optional error, and required marker;
- `FormErrorSummary` with an alert role and links to invalid fields when field IDs are known;
- `normalizeValidationErrors(error)` for API-client and local validation shapes;
- `flattenZodFieldErrors(fieldErrors)` for current Zod usage;
- focus helper for the first invalid field;
- consistent red/error styling that works in light and dark themes;
- no hidden labels for user-facing fields.

Initial requisition migration:

- Replace local `Field` helper in `requisition-form.tsx` with `FormField`.
- Replace ad hoc error alert with `FormErrorSummary`.
- Preserve existing save and submit behavior.

### Status Badge and Workflow State System

The status primitive maps workflow states to labels, descriptions, tones, and icons. It must never rely on color alone.

Core type:

```ts
export type WorkflowTone =
  | "neutral"
  | "draft"
  | "info"
  | "success"
  | "warning"
  | "danger"
  | "locked";
```

Status config shape:

```ts
export type WorkflowStateConfig<TStatus extends string> = Record<
  TStatus,
  {
    label: string;
    description: string;
    tone: WorkflowTone;
    icon: LucideIcon;
  }
>;
```

Initial requisition status map:

| Status | Label | Tone | Description |
| --- | --- | --- | --- |
| `draft` | Draft | `draft` | The requester can still edit and submit this requisition. |
| `submitted` | Submitted | `success` | The requisition has been submitted for procurement review. |
| `pending_approval` | Pending approval | `info` | The requisition is waiting for an approval decision. |

The primitive supports:

- compact and default sizes;
- optional description for assistive text;
- icon and label always visible;
- strong contrast tokens;
- exhaustive mapping for each feature status union.

### Activity Timeline Primitive

The timeline renders audit-visible events consistently across workflows.

It must support:

- empty state;
- action-specific icon mapping;
- actor name fallback;
- timestamp formatting;
- optional metadata rows;
- optional event target display;
- stable list semantics via `<ol>`;
- compact item layout;
- clear message text from the API event message.

Initial requisition migration:

- Replace `RequisitionActivityTimeline` internals with `ActivityTimeline`.
- Keep a tiny requisition wrapper only to pass requisition-specific action icons if needed.

## UX Direction

The primitives should feel like quiet enterprise SaaS tooling:

- compact spacing;
- strong borders and readable contrast;
- no hero sections;
- no decorative gradients;
- no nested cards;
- text labels on state badges;
- icon-only buttons only when the icon is familiar and has a tooltip or accessible name;
- stable dimensions for rows, buttons, badges, and loading states;
- mobile fallbacks designed for scanning, not squeezed desktop tables.

The right panel should feel like a focused utility surface. It should not become a large modal substitute for primary workflows.

## Accessibility Rules

- `main`, shell landmarks, and record layout behavior remain owned by App Shell Foundation.
- Tables must have captions, header scopes, and row actions with record-specific labels.
- Mobile list fallback must preserve the primary record name and action labels.
- Form fields must have visible labels and error text connected by `aria-describedby`.
- Error summary must use `role="alert"` and link to invalid fields where possible.
- Right panel must be keyboard closable.
- Status badges include visible labels and hidden descriptions.
- Timeline items use list semantics and expose actor/time context as text.
- Controls maintain at least 44px hit targets where they are interactive.

## Data, Tenant, and Permission Rules

This is a frontend composition slice. Tenant isolation, authorization, and validation remain enforced by backend APIs and generated client contracts.

Frontend responsibilities:

- hide feature actions when existing permission fields say the action is unavailable;
- preserve API validation field names when rendering errors;
- avoid importing MSW fixtures into production components;
- avoid writing tenant identifiers into browser storage from these primitives;
- render authorization and validation failures as recoverable UI where the feature hook exposes them.

Backend responsibilities remain unchanged:

- tenant-scoped queries;
- route middleware;
- policies;
- audit events;
- API error contract.

## Testing Strategy

New primitive tests:

- right panel opens, replaces content, closes by button, closes by Escape, and renders correct dialog attributes;
- data table renders loading, error, empty, desktop table, mobile fallback, row actions, and sort state;
- form helpers render labels, descriptions, inline errors, error summaries, and normalized validation fields;
- status badge renders all requisition statuses with labels, icons, descriptions, and tone classes;
- activity timeline renders empty state and audit-shaped events.

Requisition regression tests:

- requisition list still renders MSW-backed rows;
- quick panel opens from a requisition row and links to the workspace;
- requisition form still blocks invalid submit with error summary and inline field errors;
- requisition detail still renders activity events through the new timeline primitive;
- submitted or permission-locked requisitions do not show unavailable actions.

Verification commands:

```bash
pnpm --filter @cognify/web test
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

No API contract regeneration is expected. If implementation unexpectedly changes OpenAPI, stop and update this spec before proceeding.

## Rollout Order

1. Add right panel provider/root and connect it to the shell host.
2. Add workflow status and activity primitives.
3. Add form validation primitives and migrate requisition form.
4. Add data table primitive and migrate requisition list.
5. Add requisition row quick panel as the first real right-panel use.
6. Run focused web verification.

This order gives visible integration early and keeps each primitive proven by the current requisition workflow.

## Open Decisions Closed By This Spec

- Workflow primitives live in `apps/web/components`, not `packages/ui`.
- This is a frontend-only slice.
- Requisitions are the proving workflow.
- Right panel behavior is included because the app shell already has only an inert host.
- Saved table views, virtualization, bulk actions, and notification integration are deferred to later slices.
- Status state definitions stay feature-owned and are passed into generic primitives instead of creating one global enum for all domains.

