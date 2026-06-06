# Shadcn Factory Web Reimplementation Design

## Summary

Reimplement `apps/web` as a behavior-preserving UI rewrite built around factory-default shadcn components. The rewrite keeps Cognify's existing product routes, API behavior, permissions, generated-client usage, MSW fixtures, and workflow states intact while replacing the visual and interaction layer with shadcn primitives generated from preset `b5deKXWz4`.

This supersedes the current lighter shadcn-first posture for this effort. The new target is stricter: `packages/ui` should remain a shadcn-managed primitive package that can survive future shadcn rewrites or preset refreshes, while `apps/web` owns route composition and only a small audited set of custom composite exceptions.

## Goals

- Apply shadcn preset `b5deKXWz4` and use the default shadcn theme.
- Preserve all user-visible product behavior and backend/API contracts.
- Rebuild all `apps/web` routes and app shells with factory-default shadcn primitives.
- Remove custom reusable UI components unless shadcn has no practical equivalent.
- Keep `packages/ui` as factory-default shadcn primitive exports only.
- Use `apps/web/components/ui` only for audited app composites or missing shadcn-equivalent components.
- Add a visible app-shell dark mode toggle.
- Add a global plain `d` keyboard shortcut to toggle dark mode, ignored while typing.
- Use `ui-ux-pro-max` criteria as a design critique checklist during implementation and review.

## Non-Goals

- No backend rewrite.
- No API contract changes unless a test fixture requires a strictly UI-related correction.
- No product workflow redesign.
- No procurement copy or terminology rewrite.
- No new Cognify visual brand palette.
- No custom styling system layered over shadcn defaults.
- No persistence of the `d` shortcut into server-side user profile settings in this slice.

## Package Boundaries

### `packages/ui`

`packages/ui` is the factory shadcn primitive package.

- It should contain generated shadcn components, shadcn hooks, and shadcn utility files only.
- It should be safe to refresh with the active preset without losing Cognify business logic.
- It must not contain Cognify-specific copy, workflow state, procurement variants, route behavior, or app shell decisions.
- Compatibility fixes should prefer dependency patches or documented generated-component exceptions over product-specific edits.
- The public API should expose shadcn primitives for `apps/web` consumption.

The expected CLI direction is:

```bash
pnpm dlx shadcn@latest apply --preset b5deKXWz4 -c apps/web
```

The implementation plan must verify whether the preset updates `apps/web/components.json`, `packages/ui/components.json`, package dependencies, and `apps/web/app/globals.css`. If shadcn requires separate app and package applications, document the exact commands before running them.

### `apps/web`

`apps/web` owns the application shell, routes, feature workflows, data hooks, and user-facing procurement composition.

- Route files and feature workflow files may compose shadcn primitives directly.
- Feature-level code can keep non-visual behavior such as hooks, schemas, API wrappers, mappers, tests, and fixtures.
- Existing custom reusable UI components should be deleted or inlined into routes unless they qualify as audited exceptions.
- Custom composites must live under `apps/web/components/ui` and be grouped by responsibility.

Suggested groups:

- `apps/web/components/ui/headers`: page headers, decorated titles, route mastheads.
- `apps/web/components/ui/graph`: graph, chart, network, and relationship visual composites not covered by shadcn `Chart`.
- `apps/web/components/ui/scorecard`: procurement score matrices and evaluation layouts that exceed shadcn `Table` or `Chart`.
- `apps/web/components/ui/procurement-table`: dense procurement table compositions where TanStack state plus shadcn `Table` needs shared glue.
- `apps/web/components/ui/workflow-state`: workflow state summaries that have no direct shadcn primitive equivalent.

Each custom composite must include a short documentation entry that states:

- why shadcn primitives alone are insufficient;
- which shadcn primitives it composes;
- which routes consume it;
- what accessibility behavior it must preserve.

## Behavior Freeze

The rewrite is UI-only.

Must preserve:

- all existing app routes;
- authentication and session gate behavior;
- tenant selection and tenant persistence semantics;
- generated `@cognify/api-client` usage;
- TanStack Query hook behavior;
- permissions and conditional action visibility;
- MSW fixture behavior;
- existing loading, empty, forbidden, not-found, conflict, and validation states;
- workflow state transitions for requisitions, projects, approvals, sourcing/RFQs, quotations, calendar, system readiness, and vendor portal.

Tests may be updated for DOM structure and accessible names when the shadcn rewrite changes markup, but not to weaken product behavior assertions.

## Theme And Dark Mode

Use the default shadcn theme generated by preset `b5deKXWz4`.

Dark mode requirements:

- Use `next-themes` or the shadcn-compatible theme mechanism chosen by the preset.
- Add a visible theme toggle in the authenticated app shell.
- The toggle must be reachable by keyboard and have an accessible name.
- Add a global plain `d` shortcut to toggle light/dark mode.
- Ignore `d` when focus is inside `input`, `textarea`, `select`, or contenteditable elements.
- Ignore `d` when modifier keys are pressed.
- Persist the selected theme client-side for instant UX.
- Do not write the shortcut-driven change to the account profile `theme` field in this slice.
- Ensure the vendor portal and auth pages render correctly in both modes.

## Shadcn Component Strategy

Prefer official shadcn primitives and blocks from the configured `@shadcn` registry.

Primary primitives expected for the rewrite:

- Shell and navigation: `Sidebar`, `Breadcrumb`, `Command`, `DropdownMenu`, `Sheet`, `Button`, `Kbd`, `Separator`.
- Forms: `Form`, `Field`, `Label`, `Input`, `Textarea`, `Select`, `Checkbox`, `RadioGroup`, `Switch`.
- Feedback: `Alert`, `AlertDialog`, `Sonner`, `Skeleton`, `Spinner`, `Empty`, `Progress`.
- Data display: `Table`, `Card`, `Badge`, `Tabs`, `Accordion`, `Tooltip`, `Popover`, `ScrollArea`, `Chart`.
- Menus and dense actions: `DropdownMenu`, `ContextMenu`, `ButtonGroup`, `ToggleGroup`.

If a shadcn block is useful as source material, adapt it at the route level rather than turning it into a Cognify design-system abstraction. Blocks may inform the shell, login, dashboard, and chart/table layouts, but the final code must keep product behavior local to `apps/web`.

## Route Rewrite Scope

The full rewrite covers the current `apps/web` route inventory:

- root and app layout;
- auth layout and login;
- dashboard layout and dashboard route;
- workspace layout and account;
- requisitions list/create/detail/edit;
- projects list/create/detail/edit;
- approvals queue/task detail/policies;
- sourcing intake list/detail and RFQ draft workspace;
- quotations normalization queue/detail, comparison, scoring, scoring templates, award recommendation;
- procurement calendar;
- system readiness;
- vendor RFQ invitation portal.

The implementation plan should convert routes in slices and verify each slice before moving on.

Recommended order:

1. Primitive package and theme reset.
2. Providers, theme toggle, and app shell.
3. Auth/account/dashboard.
4. Requisitions and projects.
5. Approvals and approval policies.
6. Sourcing intake, RFQ workspace, and vendor portal.
7. Quotations normalization, comparison, scoring, awards, and PO handoff surfaces.
8. Procurement calendar and system readiness.
9. Final custom-composite audit and documentation.

## UI/UX Critique Checklist

Use `ui-ux-pro-max` as the review lens for every converted route.

Required checks:

- Accessibility: visible focus rings, semantic labels, correct dialog/sheet focus management, keyboard-reachable controls.
- Touch targets: important actions at least 44px high with adequate spacing.
- Layout: no mobile horizontal overflow, predictable responsive breakpoints, stable dimensions for dense workflow controls.
- Forms: visible labels, field-level errors, required indicators, disabled states, submit feedback.
- Navigation: predictable back links, deep links preserved, command/menu keyboard navigation retained.
- Color: default shadcn tokens only, no raw route-level color palette except token classes.
- Dark mode: readable contrast in all shells, tables, forms, alerts, cards, and popovers.
- Data density: operational procurement screens should stay scannable and work-focused, not marketing-like.
- Motion: use shadcn/Radix behavior; avoid decorative custom animation.

## Testing And Verification

Each implementation slice should run focused checks first, then broader web checks at integration gates.

Expected verification categories:

- shadcn audit script updated for the stricter component policy;
- `pnpm --filter @cognify/ui typecheck`;
- `pnpm --filter @cognify/web typecheck`;
- focused Vitest suites for converted routes;
- Playwright smoke checks for app shell, dark mode toggle, `d` shortcut, and representative dense workflows;
- screenshot review in light and dark mode for dashboard, RFQ workspace, quotation comparison, approval task, and vendor portal.

The final gate should prove:

- `packages/ui` has no Cognify-specific custom primitives;
- custom UI exceptions under `apps/web/components/ui` are grouped and documented;
- all routes render under the default shadcn theme;
- dark mode toggles through visible control and `d`;
- behavior assertions remain intact.

## Risks

- Full route rewrite has broad regression risk despite behavior freeze.
- Factory defaults may reduce bespoke information density in procurement workflows unless dense screens are carefully composed.
- The preset may introduce dependency/type drift that requires patches.
- Removing app-level composites can increase repeated route markup.
- Some custom components may be legitimate exceptions, especially scorecards, graphs, dense tables, and route-specific headers.

## Open Implementation Decisions

The implementation plan must resolve these before code changes:

- whether `b5deKXWz4` should be applied once through `apps/web/components.json` or separately to `packages/ui`;
- exact generated component list to install or refresh;
- exact exception documentation format;
- whether to retain or replace the existing shadcn audit script;
- which Playwright screenshots become the required visual baseline for this rewrite.
