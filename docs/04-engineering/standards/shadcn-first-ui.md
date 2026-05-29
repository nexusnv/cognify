# Shadcn-First UI Standard

## Rule

Cognify web screens use official shadcn components first, Cognify-specific composites of shadcn components second, and custom markup only when no official primitive exists.

## Package Boundary

- `packages/ui` exports business-neutral shadcn/Radix primitives only.
- `apps/web/components` may export Cognify app-shell, layout, table, state, and workflow composites built from `@cognify/ui`.
- `apps/web/features/*` owns procurement-specific UI language and workflow composition.

## Required Replacements

- Use `Button` or `buttonVariants` instead of raw styled `button` elements.
- Use `Input`, `Textarea`, `Select`, `Checkbox`, `RadioGroup`, `Switch`, `Field`, and `Form` instead of raw styled form controls.
- Use `Card` for bordered panels with header/body/footer structure.
- Use `Alert` and `AlertDialog` for warning, error, and destructive confirmation surfaces.
- Use `Dialog`, `Sheet`, `Popover`, `DropdownMenu`, `Command`, `Tabs`, `Tooltip`, and `Calendar` instead of hand-rolled interactive overlays.
- Use `Table` primitives for tabular markup while preserving TanStack Table state where present.
- Use `Skeleton`, `Spinner`, `Empty`, and app-level state composites for loading, empty, and error states.

## Allowed Custom Markup

- Page layout grids, content bands, and semantic regions may use raw `main`, `section`, `header`, `nav`, `aside`, `div`, `dl`, `ol`, and `ul`.
- Feature composites may add procurement meaning when they are assembled from shadcn primitives.
- Custom calendar month/week layout is allowed for procurement calendar density, but its controls, filters, details, popovers, dialogs, tabs, badges, and cards must use shadcn primitives.

## Accessibility Requirements

- Icon-only buttons must have `aria-label`.
- Dialogs and sheets must use official shadcn/Radix focus management.
- Menus, comboboxes, command palettes, tabs, and popovers must be keyboard navigable.
- Form errors must be associated with controls through labels, descriptions, and error messages.
- Touch targets must be at least 44px high for workflow actions.
