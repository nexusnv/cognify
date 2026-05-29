# Shadcn-First UI Standard

## Rule

Cognify web screens use official shadcn components first, Cognify-specific composites of shadcn components second, and custom markup only when no official primitive exists.

## Package Boundary

- `packages/ui` exports business-neutral shadcn/Radix primitives only.
- `packages/ui/src/components`, `packages/ui/src/hooks`, `packages/ui/src/lib`, and `apps/web/app/globals.css` are shadcn-managed defaults refreshed by `pnpm dlx shadcn@latest apply --preset b2CipdfvO -c apps/web`.
- `apps/web/components` may export Cognify app-shell, layout, table, state, and workflow composites built from `@cognify/ui`.
- `apps/web/features/*` owns procurement-specific UI language and workflow composition.

## CLI Contract

- Run shadcn from the project root with `pnpm dlx shadcn@latest apply --preset b2CipdfvO -c apps/web`.
- Keep `apps/web/components.json` as the app entrypoint and route shared `ui`, `utils`, `lib`, and `hooks` aliases to `@cognify/ui`.
- Keep `packages/ui/components.json` in sync with the active preset and backed by package-local `#components`, `#lib`, and `#hooks` imports.
- Do not add Cognify-specific behavior, product copy, workflow variants, or app-only styling directly to shadcn-managed defaults.
- Add custom UI in app composites or feature components. Compose shadcn primitives there instead of editing the generated primitive.
- If a generated primitive needs a temporary compatibility patch for TypeScript or dependency drift, prefer a `pnpm` dependency patch over editing the generated primitive. Document the patch under "Approved Generated Compatibility Patches" with the upstream reason and verification command.

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

## Approved Generated Compatibility Patches

- `patches/react-day-picker@10.0.1.patch`: the active `radix-mira` preset emits a `classNames.table` key that is not present in `react-day-picker@10.0.1`'s `ClassNames` type. The dependency type patch allows the generated calendar primitive to remain untouched while `pnpm --filter @cognify/ui typecheck` passes.
- `patches/@hugeicons__react@1.1.6.patch`: the active `radix-mira` preset spreads `React.ComponentProps<"svg">` into `HugeiconsIcon`, whose `strokeWidth` prop is typed as `number`. The dependency type patch widens `strokeWidth` to match SVG props so the generated spinner primitive remains untouched while `pnpm --filter @cognify/ui typecheck` passes.
