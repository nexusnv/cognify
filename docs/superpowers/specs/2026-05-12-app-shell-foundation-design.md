# App Shell Foundation Design

## Context

The P0 release plan places App Shell Foundation after Tenant, Auth, Access Foundation and Audit/API Contract Foundation in delivery order. The roadmap capabilities covered here are:

- Workspace Shell.
- Workspace Detail Layout.

The current web app already has a useful first scaffold:

- `apps/web/components/shell/workspace-shell.tsx`
- `apps/web/components/shell/dashboard-shell.tsx`
- `apps/web/components/shell/command-palette-host.tsx`
- `apps/web/components/shell/right-panel-host.tsx`
- `apps/web/app/(workspace)/layout.tsx`
- `apps/web/app/(dashboard)/layout.tsx`
- identity context from `apps/web/features/identity/hooks/use-current-user.ts`
- requisition list/detail workflows under `apps/web/features/requisitions`

This epic should harden those pieces into a consistent operational shell rather than starting over. It is intentionally frontend-only unless implementation uncovers a contract gap. It consumes the identity contract already delivered by the previous P0 slice and leaves command palette behavior, global search, notifications, and the full right-panel system to their own P0 epics.

## Delegated Design Decision

The user delegated design approval for this slice. This spec therefore makes the product and technical choices directly instead of stopping for approval gates. The chosen direction is the lean app-shell foundation described in Option A below.

## Scope

This epic delivers:

- One authenticated operational shell used by dashboard, account, and procurement workspace routes.
- Tenant-aware header with active tenant, current user, role, and account entry point.
- Permission-aware primary navigation sourced from a typed shell navigation registry.
- Responsive shell behavior for desktop and mobile.
- Skip link and landmark structure for keyboard and screen reader navigation.
- Breadcrumbs derived from route metadata.
- A footer status strip for local shell context only, not real system health monitoring.
- Stable extension hosts for command palette, notifications, and right panel without implementing their future workflows.
- A reusable record workspace layout for requisitions and future projects, vendors, quotations, approvals, and awards.
- A first migration of requisition detail UI onto the record workspace layout.
- Focused shell tests that prove route chrome, tenant context, navigation state, accessibility landmarks, mobile menu behavior, and requisition detail layout integration.

This epic does not deliver:

- A functional command palette.
- Global search.
- Notification center, unread counts, preferences, or realtime delivery.
- Full right-panel drawer behavior.
- Data table, form, status badge, or activity timeline primitives beyond using existing requisition components.
- Backend API changes.
- New shared packages.
- New `packages/ui` components. Cognify app shell composition stays in `apps/web`.

## Workflow

1. Authenticated user enters an operational route.
2. `SessionGate` validates identity and active tenant context through the existing identity hook.
3. The operational app layout renders one `AppShell`.
4. `AppShell` reads the current path, identity context, and navigation registry.
5. The shell shows tenant context, user context, active navigation, route breadcrumbs, and extension hosts.
6. Record routes can wrap their feature content in `RecordWorkspaceLayout`.
7. Requisition detail uses `RecordWorkspaceLayout` for title, status, metadata, actions, local sections, main content, and sidebar content.
8. Future features add routes and detail pages by extending shell route metadata and composing record layout slots.

Failure paths:

- Identity loading shows the existing session loading state before shell chrome appears.
- Authentication or tenant failures are still handled by `SessionGate`.
- Navigation items with missing permission are hidden.
- Navigation destinations that are not implemented yet are rendered as disabled text, not broken links.
- Shell extension hosts remain inert and must not pretend future features are complete.

## Recommended Approach

### Option A: Lean Operational Shell And Record Layout

Consolidate `DashboardShell` and `WorkspaceShell` into a single `AppShell` with small supporting files: route metadata, navigation registry, desktop/mobile navigation, header, breadcrumbs, footer, and inert extension hosts. Add a reusable `RecordWorkspaceLayout` under `apps/web/components/workspace` and migrate requisition detail to it.

This is the recommended approach. It gives every P0 and P1 workflow a stable place to live while respecting the repo boundary that Cognify-specific shells stay in `apps/web`.

### Option B: Promote Shell Pieces Into `packages/ui`

Create reusable shell primitives in `packages/ui` and compose them from the app.

This is too early. The shell encodes Cognify navigation, tenant context, procurement routes, and workflow conventions. Promoting it now would put product meaning in a shared primitive package and violate the repo boundary.

### Option C: Keep Route-Specific Shells

Continue with separate dashboard and workspace shells and patch each as needed.

This avoids a refactor, but it will make command palette, notifications, breadcrumbs, and detail layouts drift as more procurement modules arrive. P0 is the right time to establish a single shell contract.

## Frontend Architecture

All implementation stays in `apps/web`.

Planned structure:

```txt
apps/web/components/shell/
  app-shell.tsx
  app-shell.test.tsx
  breadcrumbs.tsx
  command-palette-host.tsx
  mobile-shell-nav.tsx
  notification-host.tsx
  right-panel-host.tsx
  shell-footer.tsx
  shell-header.tsx
  shell-nav.tsx
  shell-route-config.ts
  shell-types.ts
  shell-utils.ts

apps/web/components/workspace/
  record-workspace-layout.tsx
  record-workspace-layout.test.tsx

apps/web/app/(dashboard)/layout.tsx
apps/web/app/(workspace)/layout.tsx

apps/web/features/requisitions/workflows/requisition-detail-page.tsx
```

`DashboardShell` and the current `WorkspaceShell` should be removed or reduced to compatibility wrappers only if implementation needs a short transition. The desired final state is one operational shell.

## Shell Navigation

The shell navigation registry is the source of truth for top-level operational navigation. It is app-specific and should live in `apps/web/components/shell/shell-route-config.ts`.

Initial groups:

| Group | Items |
| --- | --- |
| Work | Dashboard, Requisitions, Approvals |
| Sourcing | Vendors, Quotations, Comparison |
| Governance | Evidence, Audit |
| Manage | Account |

Implemented routes:

- `/dashboard`
- `/requisitions`
- `/account`

Disabled future destinations:

- `/approvals`
- `/vendors`
- `/quotations`
- `/comparison`
- `/evidence`
- `/audit`

Permission behavior:

- Dashboard is visible to all authenticated users.
- Requisitions is visible to users with any requisition permission already exposed by the identity contract.
- Account is visible to all authenticated users.
- Audit is visible only when `permissions.canAccessAdmin` is true, but remains disabled until a UI exists.
- Future items can add permission predicates without changing shell rendering.

## Breadcrumbs

Breadcrumbs are derived from route metadata and current path patterns. They are not fetched from the backend in this epic.

Initial behavior:

- `/dashboard`: Dashboard.
- `/requisitions`: Requisitions.
- `/requisitions/new`: Requisitions / New.
- `/requisitions/[id]`: Requisitions / Requisition workspace.
- `/requisitions/[id]/edit`: Requisitions / Requisition workspace / Edit.
- `/account`: Account.

Dynamic record titles should remain inside the record workspace header. The breadcrumb label for a requisition detail route stays generic until a future breadcrumb API or feature hook is justified.

## Record Workspace Layout

`RecordWorkspaceLayout` is a Cognify-specific layout component for durable record pages. It should be generic enough for future procurement records but not promoted to `packages/ui`.

It provides:

- Back link area.
- Eyebrow/meta row.
- Title.
- Status slot.
- Primary and secondary action slots.
- Metadata definition grid.
- Local section navigation.
- Main content slot.
- Sidebar slot.
- Loading/error states remain owned by feature workflows.

The requisition detail page should use it for:

- Back to requisitions link.
- Requisition number, status, title, estimated total, needed-by date, requester.
- Edit and review/submit actions.
- Local sections: Overview, Line items, Activity, Readiness.
- Main content: overview, line items, activity timeline.
- Sidebar content: submission checklist and approval readiness section.

## Extension Hosts

The shell should include these stable hosts:

- `CommandPaletteHost`: button and keyboard target only. It may expose a clear `aria-label`, but opening commands belongs to the Command Palette epic.
- `NotificationHost`: icon button with disabled/empty state. Notification center behavior belongs to the Notification Foundation epic.
- `RightPanelHost`: inert host with a named region or null host element. Drawer state and contextual panels belong to the Global Right Panel System epic.

These hosts create placement and API boundaries without implying feature completion.

## Responsive And Accessibility Rules

- Desktop shell uses a persistent left navigation and sticky header.
- Mobile shell uses a menu button and overlay navigation.
- Mobile overlay must close when a link is selected or when Escape is pressed.
- Main content has `id="main-content"` and the skip link targets it.
- Header, navigation, main, and footer landmarks must be present.
- Icon-only buttons need labels.
- Navigation active state must not rely on color alone.
- Text must fit in shell controls at mobile and desktop widths.
- The shell should remain quiet, dense, and operational, not marketing-oriented.

## Styling Direction

Use the existing Tailwind token approach in `apps/web/app/globals.css` and avoid adding a design system package in this slice.

The visual direction should be:

- utilitarian SaaS workspace;
- high contrast borders and text;
- restrained surface colors;
- compact spacing;
- no decorative hero sections, gradient backgrounds, or marketing composition.

If additional CSS variables are needed, they should be neutral app tokens such as sidebar, accent, and subdued surface colors.

## Testing Strategy

Frontend tests should be written before implementation:

- Shell renders tenant name, user name, role, command host, notification host, navigation, breadcrumbs, and footer.
- Shell hides admin/audit navigation for requester identities.
- Shell marks the active route with accessible state.
- Mobile menu opens, exposes navigation, and closes on Escape.
- Dashboard layout is protected by `SessionGate`, matching workspace routes.
- Record workspace layout renders title, status, metadata, local sections, main content, sidebar content, and actions.
- Requisition detail uses record workspace layout semantics and still renders existing requisition data and activity.

Narrow verification:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx components/workspace/record-workspace-layout.test.tsx features/requisitions/tests/requisitions-workflow.test.tsx
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

No API tests or contract generation are expected for this epic.

## Implementation Slices

1. **Operational App Shell**
   Add route config, shared `AppShell`, shell header/nav/footer/breadcrumb pieces, notification host, responsive mobile nav, and protect dashboard routes with `SessionGate`.

2. **Record Workspace Layout**
   Add `RecordWorkspaceLayout`, migrate requisition detail onto it, and verify the existing requisition workflow still behaves through MSW.

These slices belong in one implementation plan because the record workspace layout depends on the shell chrome conventions and shares the same frontend verification path.

## Planning Decisions

The implementation plan should use these decisions unless code constraints require a narrower adjustment:

- Keep shell behavior entirely client-side because it depends on current identity query state and current pathname.
- Use `lucide-react` icons already installed in `apps/web`.
- Do not add Radix dialog/sheet primitives for the mobile menu unless existing dependencies already make it trivial; a small accessible local overlay is enough for P0.
- Do not add global Zustand shell state in this slice.
- Do not add generated API types or OpenAPI changes.
- Keep disabled future nav items visible only where they help users understand the product map; avoid broken links.
- Prefer route metadata over scattered page-level breadcrumb strings.
- Keep record layout slot-based so feature workflows own data loading, actions, permissions, and domain language.
