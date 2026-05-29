# Shadcn First Screen Conversion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert every existing Cognify web screen and reusable app-level UI surface to prefer official shadcn components first, Cognify-specific composites of shadcn components second, and custom markup only when no official primitive exists.

**Architecture:** Keep official reusable primitives in `packages/ui`, keep Cognify procurement meaning in `apps/web`, and preserve existing generated-client/MSW workflow boundaries. This is a frontend-only UI standardization program: it must not change API contracts, business workflow semantics, permissions, tenant behavior, or generated `@cognify/api-client` types.

**Tech Stack:** Next.js App Router, React 19, Tailwind CSS v4, lucide-react, Radix-backed shadcn components from the `@shadcn` registry, `@cognify/ui` shared primitive package, TanStack Query, TanStack Table, MSW, Vitest, Playwright.

---

## Source Documents And Registry Evidence

- `AGENTS.md`
- `ARCHITECTURE.md`
- `docs/05-runbooks/feature-development.md`
- `apps/web/components.json`
- `apps/web/package.json`
- `packages/ui/package.json`
- `packages/ui/src/index.ts`
- shadcn MCP registry: configured registry is `@shadcn`.
- shadcn MCP registry inventory confirmed these official UI items exist: `accordion`, `alert`, `alert-dialog`, `aspect-ratio`, `avatar`, `badge`, `breadcrumb`, `button`, `button-group`, `calendar`, `card`, `carousel`, `chart`, `checkbox`, `collapsible`, `combobox`, `command`, `context-menu`, `dialog`, `drawer`, `dropdown-menu`, `empty`, `field`, `form`, `hover-card`, `input`, `input-group`, `input-otp`, `item`, `label`, `menubar`, `navigation-menu`, `pagination`, `popover`, `progress`, `radio-group`, `resizable`, `scroll-area`, `select`, `separator`, `sheet`, `sidebar`, `skeleton`, `slider`, `sonner`, `spinner`, `switch`, `table`, `tabs`, `textarea`, `toggle`, `toggle-group`, `tooltip`, `kbd`, `native-select`.
- shadcn MCP component detail lookup confirmed these dependencies:
  - `button`, `dialog`, `sheet`, `dropdown-menu`, `tabs`, `select`, `checkbox`, `badge`, `tooltip`, `popover`, `breadcrumb`, `sidebar`, and `alert-dialog` use Radix dependencies.
  - `form` uses Radix, `@hookform/resolvers`, `zod`, and `react-hook-form`.
  - `command` uses `cmdk`.
  - `calendar` uses `react-day-picker` and `date-fns`.
  - `sonner` uses `sonner` and `next-themes`.
  - `table`, `card`, `field`, `input`, `textarea`, `alert`, `skeleton`, `pagination`, and `empty` are local component files without extra runtime component dependencies.
- shadcn MCP add command for the expansion set:

```bash
pnpm dlx shadcn@latest add @shadcn/dialog @shadcn/sheet @shadcn/dropdown-menu @shadcn/table @shadcn/tabs @shadcn/form @shadcn/field @shadcn/select @shadcn/skeleton @shadcn/sonner @shadcn/tooltip @shadcn/command @shadcn/popover @shadcn/calendar @shadcn/breadcrumb @shadcn/pagination @shadcn/empty @shadcn/sidebar @shadcn/button-group @shadcn/input-group @shadcn/progress @shadcn/radio-group @shadcn/switch @shadcn/alert-dialog @shadcn/scroll-area
```

Run the command only after Task 1 records the baseline. Because this repository exports shared primitives from `packages/ui`, generated files may need to be moved or copied from the app alias target into `packages/ui/src/components` and exported from `packages/ui/src/index.ts`.

## Scope Boundaries

In scope:

- All current `apps/web/app/**/page.tsx` and `layout.tsx` surfaces.
- All current `apps/web/components/**/*.tsx` shared app composition.
- All current `apps/web/features/**/{components,forms,tables,workflows}/**/*.tsx`.
- All custom buttons, cards, tables, dialogs, sheets, alerts, empty states, skeletons, nav, breadcrumbs, tabs, menus, popovers, combobox-like controls, forms, labels, inputs, selects, textareas, checkboxes, progress indicators, toasts, tooltips, pagination, calendar controls, sidebars, and data-display sections.
- Custom composite components where Cognify business meaning exists, built from official shadcn primitives.

Out of scope:

- Backend API, OpenAPI, generated API client, migrations, Laravel domain actions, tenant middleware, business state names, route contracts, and MSW response shapes.
- Product copy rewrites except small wording needed to preserve accessible labels after converting controls.
- New workflows, new data fields, visual rebranding, charts that are not currently present, and replacing TanStack Table behavior with a different data engine.
- Moving Cognify-specific shell or procurement workflow components into `packages/ui`.

## Conversion Rules

1. Prefer official shadcn primitives exported from `@cognify/ui`.
2. If a control has no exact primitive, build an app-level composite from shadcn primitives in `apps/web/components` or the owning feature folder.
3. Keep `packages/ui` business-neutral. It may export `Button`, `Card`, `Dialog`, `Sheet`, `Table`, `Form`, `Field`, `Select`, `Badge`, `Alert`, `Skeleton`, and similar primitives, but it must not export `ProjectStatusBadge`, `RequisitionCard`, `ProcurementCalendarWeekView`, or Cognify workflow language.
4. Use lucide icons for icon buttons and menus. Icon-only buttons require `aria-label`.
5. Preserve touch target minimums: interactive controls must stay at least `h-10` desktop and `min-h-11` where already used for touch-heavy workflow actions.
6. Preserve keyboard access when replacing hand-rolled overlays with `Dialog`, `Sheet`, `Command`, `DropdownMenu`, `Popover`, `Tabs`, `Select`, and `Calendar`.
7. Use shadcn semantic variants before custom color classes:
   - Buttons: `default`, `secondary`, `outline`, `ghost`, `destructive`, `link` after the package supports it.
   - Badges: `default`, `secondary`, `outline`, `destructive` after the package supports it.
   - Alerts: `default`, `destructive` after the package supports it.
8. Raw `div` and `section` remain acceptable for page layout grids and unframed bands. Framed panels with borders, headers, descriptions, and action footers should become `Card` or a Cognify composite built on `Card`.
9. Raw `button`, `input`, `select`, `textarea`, `table`, and hand-rolled `role="dialog"` are not acceptable after this conversion unless there is a documented exception in `docs/04-engineering/standards/shadcn-first-ui.md`.
10. Do not import mock fixtures directly into production UI while touching files.

## Screen Inventory To Convert

Routes:

- `apps/web/app/page.tsx`
- `apps/web/app/(auth)/login/page.tsx`
- `apps/web/app/(dashboard)/dashboard/page.tsx`
- `apps/web/app/(workspace)/account/page.tsx`
- `apps/web/app/(workspace)/approval-policies/page.tsx`
- `apps/web/app/(workspace)/approval-policies/new/page.tsx`
- `apps/web/app/(workspace)/approval-policies/[policyId]/page.tsx`
- `apps/web/app/(workspace)/approvals/page.tsx`
- `apps/web/app/(workspace)/approvals/tasks/[taskId]/page.tsx`
- `apps/web/app/(workspace)/calendar/page.tsx`
- `apps/web/app/(workspace)/projects/page.tsx`
- `apps/web/app/(workspace)/projects/new/page.tsx`
- `apps/web/app/(workspace)/projects/[projectId]/page.tsx`
- `apps/web/app/(workspace)/projects/[projectId]/edit/page.tsx`
- `apps/web/app/(workspace)/quotations/awards/[rfqId]/page.tsx`
- `apps/web/app/(workspace)/quotations/comparisons/[rfqId]/page.tsx`
- `apps/web/app/(workspace)/quotations/normalizations/page.tsx`
- `apps/web/app/(workspace)/quotations/normalizations/[normalizationId]/page.tsx`
- `apps/web/app/(workspace)/quotations/scoring/[rfqId]/page.tsx`
- `apps/web/app/(workspace)/quotations/scoring/templates/page.tsx`
- `apps/web/app/(workspace)/quotations/scoring/templates/[templateId]/page.tsx`
- `apps/web/app/(workspace)/requisitions/page.tsx`
- `apps/web/app/(workspace)/requisitions/new/page.tsx`
- `apps/web/app/(workspace)/requisitions/[requisitionId]/page.tsx`
- `apps/web/app/(workspace)/requisitions/[requisitionId]/edit/page.tsx`
- `apps/web/app/(workspace)/sourcing/intake/page.tsx`
- `apps/web/app/(workspace)/sourcing/intake/[reviewId]/page.tsx`
- `apps/web/app/(workspace)/sourcing/rfqs/[rfqId]/page.tsx`
- `apps/web/app/(workspace)/system/page.tsx`
- `apps/web/app/vendor/rfq-invitations/[token]/page.tsx`

Shared app surfaces:

- `apps/web/components/data-table/*.tsx`
- `apps/web/components/forms/*.tsx`
- `apps/web/components/right-panel/*.tsx`
- `apps/web/components/shell/*.tsx`
- `apps/web/components/workflow/*.tsx`
- `apps/web/components/workspace/*.tsx`

Feature surfaces:

- `apps/web/features/approvals/{components,forms,tables,workflows}/**/*.tsx`
- `apps/web/features/attachments/components/**/*.tsx`
- `apps/web/features/identity/{components,forms,workflows}/**/*.tsx`
- `apps/web/features/notifications/components/**/*.tsx`
- `apps/web/features/procurement-calendar/{components,workflows}/**/*.tsx`
- `apps/web/features/projects/{components,forms,tables,workflows}/**/*.tsx`
- `apps/web/features/quotations/{components,tables,workflows}/**/*.tsx`
- `apps/web/features/requisitions/{components,forms,tables,workflows}/**/*.tsx`
- `apps/web/features/search/components/**/*.tsx`
- `apps/web/features/sourcing/{components,tables,workflows}/**/*.tsx`
- `apps/web/features/system-readiness/{components,workflows}/**/*.tsx`
- `apps/web/features/vendor-portal/{components,workflows}/**/*.tsx`

## File Map

Shared primitive package modifications:

- Modify: `packages/ui/package.json`
- Modify: `packages/ui/src/index.ts`
- Keep and align: `packages/ui/src/components/alert.tsx`
- Keep and align: `packages/ui/src/components/badge.tsx`
- Keep and align: `packages/ui/src/components/button.tsx`
- Keep and align: `packages/ui/src/components/card.tsx`
- Keep and align: `packages/ui/src/components/checkbox.tsx`
- Keep and align: `packages/ui/src/components/input.tsx`
- Keep and align: `packages/ui/src/components/label.tsx`
- Keep and align: `packages/ui/src/components/native-select.tsx`
- Keep and align: `packages/ui/src/components/separator.tsx`
- Keep and align: `packages/ui/src/components/textarea.tsx`
- Create: `packages/ui/src/components/alert-dialog.tsx`
- Create: `packages/ui/src/components/breadcrumb.tsx`
- Create: `packages/ui/src/components/button-group.tsx`
- Create: `packages/ui/src/components/calendar.tsx`
- Create: `packages/ui/src/components/command.tsx`
- Create: `packages/ui/src/components/dialog.tsx`
- Create: `packages/ui/src/components/dropdown-menu.tsx`
- Create: `packages/ui/src/components/empty.tsx`
- Create: `packages/ui/src/components/field.tsx`
- Create: `packages/ui/src/components/form.tsx`
- Create: `packages/ui/src/components/input-group.tsx`
- Create: `packages/ui/src/components/pagination.tsx`
- Create: `packages/ui/src/components/popover.tsx`
- Create: `packages/ui/src/components/progress.tsx`
- Create: `packages/ui/src/components/radio-group.tsx`
- Create: `packages/ui/src/components/scroll-area.tsx`
- Create: `packages/ui/src/components/select.tsx`
- Create: `packages/ui/src/components/sheet.tsx`
- Create: `packages/ui/src/components/sidebar.tsx`
- Create: `packages/ui/src/components/skeleton.tsx`
- Create: `packages/ui/src/components/sonner.tsx`
- Create: `packages/ui/src/components/spinner.tsx`
- Create: `packages/ui/src/components/switch.tsx`
- Create: `packages/ui/src/components/table.tsx`
- Create: `packages/ui/src/components/tabs.tsx`
- Create: `packages/ui/src/components/tooltip.tsx`

App-level composite additions:

- Create: `apps/web/components/ui/page-header.tsx`
- Create: `apps/web/components/ui/surface-section.tsx`
- Create: `apps/web/components/ui/status-card.tsx`
- Create: `apps/web/components/ui/toolbar.tsx`
- Create: `apps/web/components/ui/confirm-action-dialog.tsx`
- Create: `apps/web/components/ui/loading-state.tsx`
- Create: `apps/web/components/ui/error-state.tsx`
- Create: `apps/web/components/ui/empty-state.tsx`
- Create: `apps/web/components/ui/filter-popover.tsx`
- Create: `apps/web/components/ui/mobile-action-sheet.tsx`
- Modify: `apps/web/components/data-table/data-table.tsx`
- Modify: `apps/web/components/data-table/data-table-empty-state.tsx`
- Modify: `apps/web/components/forms/form-error-summary.tsx`
- Modify: `apps/web/components/forms/form-field.tsx`
- Modify: `apps/web/components/right-panel/right-panel-root.tsx`
- Modify: `apps/web/components/right-panel/right-panel-trigger.tsx`
- Modify: `apps/web/components/shell/app-shell.tsx`
- Modify: `apps/web/components/shell/breadcrumbs.tsx`
- Modify: `apps/web/components/shell/command-palette-host.tsx`
- Modify: `apps/web/components/shell/mobile-shell-nav.tsx`
- Modify: `apps/web/components/shell/notification-host.tsx`
- Modify: `apps/web/components/shell/right-panel-host.tsx`
- Modify: `apps/web/components/shell/shell-footer.tsx`
- Modify: `apps/web/components/shell/shell-header.tsx`
- Modify: `apps/web/components/shell/shell-nav.tsx`
- Modify: `apps/web/components/workflow/activity-timeline.tsx`
- Modify: `apps/web/components/workflow/status-badge.tsx`
- Modify: `apps/web/components/workspace/record-workspace-layout.tsx`

Documentation and enforcement additions:

- Create: `docs/04-engineering/standards/shadcn-first-ui.md`
- Create: `tooling/scripts/audit-shadcn-first-ui.mjs`
- Modify: `package.json`

Feature files:

- Modify all files listed in "Screen Inventory To Convert" when a task reaches that screen family.

## Task 1: Baseline Audit And Shadcn Inventory Lock

**Files:**
- Create: `docs/04-engineering/standards/shadcn-first-ui.md`
- Create: `tooling/scripts/audit-shadcn-first-ui.mjs`
- Modify: `package.json`

- [ ] **Step 1: Confirm branch state**

Run:

```bash
git status --short --branch
```

Expected: current branch is shown. If unrelated user changes exist, do not revert them.

- [ ] **Step 2: Record current primitive exports**

Run:

```bash
sed -n '1,220p' packages/ui/src/index.ts
```

Expected: current exports include `Button`, `Card`, `Badge`, `Alert`, `Checkbox`, `Input`, `Label`, `NativeSelect`, `Separator`, and `Textarea`.

- [ ] **Step 3: Create the shadcn-first standard**

Create `docs/04-engineering/standards/shadcn-first-ui.md`:

```markdown
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
```

- [ ] **Step 4: Add an audit script**

Create `tooling/scripts/audit-shadcn-first-ui.mjs`:

```js
import { readdirSync, readFileSync, statSync } from "node:fs";
import { join } from "node:path";

const roots = ["apps/web/app", "apps/web/components", "apps/web/features"];
const ignoredSegments = new Set(["mocks", "types", "schemas"]);

function collectFiles(directory) {
  const entries = readdirSync(directory);
  const files = [];

  for (const entry of entries) {
    const path = join(directory, entry);
    const stat = statSync(path);

    if (stat.isDirectory()) {
      if (!ignoredSegments.has(entry)) {
        files.push(...collectFiles(path));
      }
      continue;
    }

    if (
      (path.endsWith(".tsx") || path.endsWith(".ts")) &&
      !path.endsWith(".test.ts") &&
      !path.endsWith(".test.tsx")
    ) {
      files.push(path);
    }
  }

  return files;
}

const files = roots.flatMap((root) => collectFiles(root));

const checks = [
  { pattern: /<button[\s>]/, label: "raw <button>" },
  { pattern: /<input[\s>]/, label: "raw <input>" },
  { pattern: /<select[\s>]/, label: "raw <select>" },
  { pattern: /<textarea[\s>]/, label: "raw <textarea>" },
  { pattern: /<table[\s>]/, label: "raw <table>" },
  { pattern: /role="dialog"/, label: "hand-rolled dialog" },
  { pattern: /className="[^"]*rounded-md border[^"]*p-[3456]/, label: "custom bordered panel" },
];

const allowed = new Map([
  ["apps/web/components/data-table/data-table.tsx", ["raw <table>"]],
  ["apps/web/features/procurement-calendar/components/procurement-calendar-month-view.tsx", []],
  ["apps/web/features/procurement-calendar/components/procurement-calendar-week-view.tsx", []],
]);

const failures = [];

for (const file of files) {
  const source = readFileSync(file, "utf8");
  const fileAllowed = allowed.get(file) ?? [];

  for (const check of checks) {
    if (fileAllowed.includes(check.label)) {
      continue;
    }

    if (check.pattern.test(source)) {
      failures.push(`${file}: ${check.label}`);
    }
  }
}

if (failures.length > 0) {
  console.error("Shadcn-first audit failed:");
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log(`Shadcn-first audit passed for ${files.length} files.`);
```

- [ ] **Step 5: Wire the audit script**

Modify the root `package.json` scripts:

```json
{
  "scripts": {
    "audit:shadcn-ui": "node tooling/scripts/audit-shadcn-first-ui.mjs"
  }
}
```

If `package.json` already has a `scripts` object, add only this script key and preserve all existing scripts.

- [ ] **Step 6: Run the audit and capture the failing baseline**

Run:

```bash
pnpm audit:shadcn-ui
```

Expected: FAIL. The output should list raw controls, hand-rolled dialogs, raw tables, and custom bordered panels. Keep this failing output as the task backlog; do not weaken the script to pass before conversions.

- [ ] **Step 7: Commit**

```bash
git add docs/04-engineering/standards/shadcn-first-ui.md tooling/scripts/audit-shadcn-first-ui.mjs package.json
git commit -m "docs: define shadcn first ui standard"
```

## Task 2: Expand `@cognify/ui` To Official Shadcn Primitive Coverage

**Files:**
- Modify: `packages/ui/package.json`
- Modify: `packages/ui/src/index.ts`
- Modify: `packages/ui/src/components/*.tsx`
- Create: new primitive files listed in the File Map

- [ ] **Step 1: Add required package dependencies**

Modify `packages/ui/package.json` so `dependencies` includes all primitive runtime dependencies used by the official shadcn items:

```json
{
  "dependencies": {
    "@hookform/resolvers": "^5.2.2",
    "@radix-ui/react-alert-dialog": "^1.1.15",
    "@radix-ui/react-checkbox": "^1.3.3",
    "@radix-ui/react-dialog": "^1.1.15",
    "@radix-ui/react-dropdown-menu": "^2.1.16",
    "@radix-ui/react-label": "^2.1.8",
    "@radix-ui/react-popover": "^1.1.15",
    "@radix-ui/react-progress": "^1.1.8",
    "@radix-ui/react-radio-group": "^1.3.8",
    "@radix-ui/react-scroll-area": "^1.2.10",
    "@radix-ui/react-select": "^2.2.6",
    "@radix-ui/react-separator": "^1.1.8",
    "@radix-ui/react-slot": "^1.2.4",
    "@radix-ui/react-switch": "^1.2.6",
    "@radix-ui/react-tabs": "^1.1.13",
    "@radix-ui/react-tooltip": "^1.2.8",
    "class-variance-authority": "^0.7.1",
    "clsx": "^2.1.1",
    "cmdk": "^1.1.1",
    "date-fns": "^4.1.0",
    "lucide-react": "^1.14.0",
    "next-themes": "^0.4.6",
    "react-day-picker": "^9.11.2",
    "react-hook-form": "^7.75.0",
    "sonner": "^2.0.7",
    "tailwind-merge": "^3.4.0",
    "zod": "^4.4.3"
  }
}
```

Preserve the existing `peerDependencies`, `devDependencies`, `type`, and `exports`.

- [ ] **Step 2: Install dependencies**

Run:

```bash
pnpm install
```

Expected: lockfile updates successfully.

- [ ] **Step 3: Use the shadcn CLI as source material**

Run:

```bash
pnpm dlx shadcn@latest add @shadcn/dialog @shadcn/sheet @shadcn/dropdown-menu @shadcn/table @shadcn/tabs @shadcn/form @shadcn/field @shadcn/select @shadcn/skeleton @shadcn/sonner @shadcn/tooltip @shadcn/command @shadcn/popover @shadcn/calendar @shadcn/breadcrumb @shadcn/pagination @shadcn/empty @shadcn/sidebar @shadcn/button-group @shadcn/input-group @shadcn/progress @shadcn/radio-group @shadcn/switch @shadcn/alert-dialog @shadcn/scroll-area
```

Expected: official component implementations are generated according to `apps/web/components.json`. Move generated primitive files into `packages/ui/src/components` if they land under `apps/web/components/ui`, then delete the generated app-local primitive duplicates after export parity is confirmed.

- [ ] **Step 4: Align existing primitive variants**

Modify existing primitives to match the official shadcn API shape used by the generated components:

```ts
type ButtonVariant = "default" | "destructive" | "outline" | "secondary" | "ghost" | "link";
type ButtonSize = "default" | "sm" | "lg" | "icon";
type BadgeVariant = "default" | "secondary" | "destructive" | "outline";
type AlertVariant = "default" | "destructive";
```

Keep `React.createElement(...)` style if that is required by the package boundary, but the public component names, variants, refs, and class behavior must match official shadcn usage.

- [ ] **Step 5: Export all primitives**

Modify `packages/ui/src/index.ts` so it exports every primitive added in this task:

```ts
export { Alert, AlertDescription, AlertTitle } from "./components/alert";
export {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "./components/alert-dialog";
export { Badge } from "./components/badge";
export type { BadgeProps } from "./components/badge";
export {
  Breadcrumb,
  BreadcrumbEllipsis,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "./components/breadcrumb";
export { Button, buttonVariants } from "./components/button";
export type { ButtonProps } from "./components/button";
export { ButtonGroup } from "./components/button-group";
export { Calendar } from "./components/calendar";
export {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "./components/card";
export { Checkbox } from "./components/checkbox";
export type { CheckboxProps } from "./components/checkbox";
export {
  Command,
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from "./components/command";
export {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "./components/dialog";
export {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuPortal,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from "./components/dropdown-menu";
export { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "./components/empty";
export {
  Field,
  FieldContent,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
  FieldLegend,
  FieldSeparator,
  FieldSet,
  FieldTitle,
} from "./components/field";
export {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "./components/form";
export { Input } from "./components/input";
export { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput, InputGroupText, InputGroupTextarea } from "./components/input-group";
export { Label } from "./components/label";
export { NativeSelect } from "./components/native-select";
export type { NativeSelectProps } from "./components/native-select";
export {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "./components/pagination";
export { Popover, PopoverAnchor, PopoverContent, PopoverTrigger } from "./components/popover";
export { Progress } from "./components/progress";
export { RadioGroup, RadioGroupItem } from "./components/radio-group";
export { ScrollArea, ScrollBar } from "./components/scroll-area";
export {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectScrollDownButton,
  SelectScrollUpButton,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
} from "./components/select";
export { Separator } from "./components/separator";
export {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "./components/sheet";
export { Skeleton } from "./components/skeleton";
export { Toaster } from "./components/sonner";
export { Spinner } from "./components/spinner";
export { Switch } from "./components/switch";
export {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from "./components/table";
export { Tabs, TabsContent, TabsList, TabsTrigger } from "./components/tabs";
export { Textarea } from "./components/textarea";
export type { TextareaProps } from "./components/textarea";
export {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "./components/tooltip";
export { cn } from "./lib/utils";
```

- [ ] **Step 6: Typecheck the package**

Run:

```bash
pnpm --filter @cognify/ui typecheck
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add packages/ui package.json pnpm-lock.yaml apps/web/components.json
git commit -m "feat: expand shadcn primitive exports"
```

## Task 3: Create Cognify App-Level Shadcn Composites

**Files:**
- Create: `apps/web/components/ui/page-header.tsx`
- Create: `apps/web/components/ui/surface-section.tsx`
- Create: `apps/web/components/ui/status-card.tsx`
- Create: `apps/web/components/ui/toolbar.tsx`
- Create: `apps/web/components/ui/confirm-action-dialog.tsx`
- Create: `apps/web/components/ui/loading-state.tsx`
- Create: `apps/web/components/ui/error-state.tsx`
- Create: `apps/web/components/ui/empty-state.tsx`
- Create: `apps/web/components/ui/filter-popover.tsx`
- Create: `apps/web/components/ui/mobile-action-sheet.tsx`
- Test: `apps/web/components/ui/ui-composites.test.tsx`

- [ ] **Step 1: Add composite tests**

Create `apps/web/components/ui/ui-composites.test.tsx`:

```tsx
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { PageHeader } from "./page-header";
import { ConfirmActionDialog } from "./confirm-action-dialog";
import { EmptyState } from "./empty-state";

describe("shadcn app composites", () => {
  it("renders a page header with title, description, and actions", () => {
    render(
      <PageHeader
        eyebrow="Workspace"
        title="Requisitions"
        description="Review intake and submission status."
        actions={<button type="button">New requisition</button>}
      />,
    );

    expect(screen.getByRole("heading", { name: "Requisitions" })).toBeInTheDocument();
    expect(screen.getByText("Review intake and submission status.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "New requisition" })).toBeInTheDocument();
  });

  it("renders a confirm action dialog with accessible cancel and confirm actions", async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <ConfirmActionDialog
        triggerLabel="Cancel draft"
        title="Cancel draft?"
        description="This keeps the audit trail and stops further editing."
        confirmLabel="Cancel draft"
        onConfirm={onConfirm}
      />,
    );

    await user.click(screen.getByRole("button", { name: "Cancel draft" }));
    expect(screen.getByRole("alertdialog")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Cancel draft" }));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it("renders empty state copy and optional action", () => {
    render(<EmptyState title="No records" description="Create the first record." action={<button type="button">Create</button>} />);

    expect(screen.getByText("No records")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Create" })).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run the failing tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/ui/ui-composites.test.tsx
```

Expected: FAIL because the composite files do not exist.

- [ ] **Step 3: Implement composites**

Create the files using these shapes:

```tsx
// apps/web/components/ui/page-header.tsx
import { ReactNode } from "react";

type PageHeaderProps = {
  eyebrow?: string;
  title: string;
  description?: string;
  actions?: ReactNode;
};

export function PageHeader({ eyebrow, title, description, actions }: PageHeaderProps) {
  return (
    <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-start md:justify-between">
      <div className="min-w-0 space-y-2">
        {eyebrow ? <p className="text-sm font-medium text-muted-foreground">{eyebrow}</p> : null}
        <h1 className="text-2xl font-semibold tracking-normal">{title}</h1>
        {description ? <p className="max-w-3xl text-sm leading-6 text-muted-foreground">{description}</p> : null}
      </div>
      {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
    </header>
  );
}
```

```tsx
// apps/web/components/ui/confirm-action-dialog.tsx
"use client";

import { ReactNode } from "react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
  Button,
} from "@cognify/ui";

type ConfirmActionDialogProps = {
  triggerLabel: string;
  title: string;
  description: string;
  confirmLabel: string;
  cancelLabel?: string;
  destructive?: boolean;
  disabled?: boolean;
  trigger?: ReactNode;
  onConfirm: () => void;
};

export function ConfirmActionDialog({
  triggerLabel,
  title,
  description,
  confirmLabel,
  cancelLabel = "Cancel",
  destructive = true,
  disabled = false,
  trigger,
  onConfirm,
}: ConfirmActionDialogProps) {
  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        {trigger ?? (
          <Button type="button" variant={destructive ? "destructive" : "outline"} disabled={disabled}>
            {triggerLabel}
          </Button>
        )}
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{cancelLabel}</AlertDialogCancel>
          <AlertDialogAction onClick={onConfirm}>{confirmLabel}</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
```

Implement the remaining composites as thin wrappers:

- `SurfaceSection`: `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, optional `CardFooter`.
- `StatusCard`: `Card` with a compact label, value, description, optional icon, and optional `Badge`.
- `Toolbar`: responsive `div` with `role="toolbar"` when actions are present.
- `LoadingState`: `Card` plus `Skeleton` rows or `Spinner`.
- `ErrorState`: `Alert variant="destructive"` plus optional retry `Button`.
- `EmptyState`: official `Empty` primitive with optional action.
- `FilterPopover`: `Popover`, `PopoverTrigger`, `PopoverContent`, `Button`.
- `MobileActionSheet`: `Sheet`, `SheetTrigger`, `SheetContent`, `SheetHeader`, `SheetTitle`, `SheetDescription`.

- [ ] **Step 4: Run tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/ui/ui-composites.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/web/components/ui
git commit -m "feat: add shadcn app ui composites"
```

## Task 4: Convert Shell, Navigation, Breadcrumbs, Command Palette, And Right Panel

**Files:**
- Modify: `apps/web/components/shell/app-shell.tsx`
- Modify: `apps/web/components/shell/breadcrumbs.tsx`
- Modify: `apps/web/components/shell/command-palette-host.tsx`
- Modify: `apps/web/components/shell/mobile-shell-nav.tsx`
- Modify: `apps/web/components/shell/notification-host.tsx`
- Modify: `apps/web/components/shell/right-panel-host.tsx`
- Modify: `apps/web/components/shell/shell-footer.tsx`
- Modify: `apps/web/components/shell/shell-header.tsx`
- Modify: `apps/web/components/shell/shell-nav.tsx`
- Modify: `apps/web/components/right-panel/right-panel-root.tsx`
- Modify: `apps/web/components/right-panel/right-panel-trigger.tsx`
- Test: existing shell and right-panel tests

- [ ] **Step 1: Add shell assertions for shadcn behavior**

Extend `apps/web/components/shell/app-shell.test.tsx`, `apps/web/components/shell/shell-footer.test.tsx`, and `apps/web/components/right-panel/right-panel.test.tsx` with these expectations:

```tsx
expect(screen.getByRole("button", { name: /open navigation/i })).toHaveClass("inline-flex");
expect(screen.getByRole("button", { name: /command palette/i })).toBeInTheDocument();
expect(screen.queryByRole("dialog", { name: /navigation/i })).not.toBeInTheDocument();
```

For right panel tests, assert the panel is a Radix dialog/sheet after opening:

```tsx
await user.click(screen.getByRole("button", { name: /open panel/i }));
expect(screen.getByRole("dialog")).toBeInTheDocument();
```

- [ ] **Step 2: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx components/shell/shell-footer.test.tsx components/right-panel/right-panel.test.tsx
```

Expected before conversion: FAIL if tests require `Sheet`, `Command`, or `Breadcrumb` semantics not yet present.

- [ ] **Step 3: Convert shell controls**

Required replacements:

- `mobile-shell-nav.tsx`: replace hand-rolled fixed overlay and close buttons with `Sheet`, `SheetTrigger`, `SheetContent`, `SheetHeader`, `SheetTitle`, `SheetClose`, and `Button size="icon"`.
- `breadcrumbs.tsx`: replace custom breadcrumb list with `Breadcrumb`, `BreadcrumbList`, `BreadcrumbItem`, `BreadcrumbLink`, `BreadcrumbPage`, and `BreadcrumbSeparator`.
- `command-palette-host.tsx`: replace custom dialog/list markup with `CommandDialog`, `CommandInput`, `CommandList`, `CommandGroup`, `CommandItem`, `CommandEmpty`, and `CommandShortcut`.
- `right-panel-root.tsx`: replace custom right panel overlay with `Sheet` using `side="right"` if the generated `SheetContent` supports side variants.
- `notification-host.tsx`: use `Toaster` from `@cognify/ui` and remove custom toast region only if existing notification tests remain equivalent.
- `shell-header.tsx` and `shell-nav.tsx`: replace raw buttons and menu surfaces with `Button`, `DropdownMenu`, `Tooltip`, `ScrollArea`, and `Separator`.

- [ ] **Step 4: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx components/shell/shell-footer.test.tsx components/shell/shell-route-config.test.tsx components/right-panel/right-panel.test.tsx features/search/tests/command-palette.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/web/components/shell apps/web/components/right-panel apps/web/features/search
git commit -m "refactor: convert shell surfaces to shadcn primitives"
```

## Task 5: Convert Data Tables, Empty States, Pagination, And List Toolbars

**Files:**
- Modify: `apps/web/components/data-table/data-table.tsx`
- Modify: `apps/web/components/data-table/data-table-empty-state.tsx`
- Modify: `apps/web/features/approvals/tables/approval-tasks-table.tsx`
- Modify: `apps/web/features/projects/tables/projects-table.tsx`
- Modify: `apps/web/features/quotations/tables/quotation-normalization-queue-table.tsx`
- Modify: `apps/web/features/requisitions/tables/requisitions-table.tsx`
- Modify: `apps/web/features/sourcing/tables/sourcing-intake-table.tsx`
- Test: existing table and workflow tests

- [ ] **Step 1: Add regression assertions**

Extend `apps/web/components/data-table/data-table.test.tsx`:

```tsx
expect(screen.getByRole("table", { name: /requisitions/i })).toBeInTheDocument();
expect(screen.getByRole("button", { name: /previous page/i })).toHaveClass("inline-flex");
expect(screen.getByRole("button", { name: /next page/i })).toHaveClass("inline-flex");
```

- [ ] **Step 2: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/data-table/data-table.test.tsx
```

Expected: PASS before markup changes if the current behavior is intact.

- [ ] **Step 3: Convert table primitives**

In `apps/web/components/data-table/data-table.tsx`:

- Replace raw table elements with `Table`, `TableHeader`, `TableBody`, `TableRow`, `TableHead`, `TableCell`, and `TableCaption`.
- Replace sort header raw buttons with `Button variant="ghost" size="sm"`.
- Replace pagination buttons with `Button variant="outline" size="sm"` or `Pagination` primitives if the component becomes page-number based.
- Replace mobile row `article` bordered cards with `Card`, `CardContent`, and optional `CardFooter`.

Keep TanStack-style sorting/pagination state and existing responsive behavior.

- [ ] **Step 4: Convert empty and error states**

In `data-table-empty-state.tsx`:

- Loading: `Card` plus `Skeleton`.
- Error: `Alert variant="destructive"` and retry `Button`.
- Empty: official `Empty` through the app-level `EmptyState` composite.

- [ ] **Step 5: Convert feature table wrappers**

For each feature table file, replace custom toolbar buttons, filter panels, and empty states with `Toolbar`, `Button`, `DropdownMenu`, `FilterPopover`, `EmptyState`, and `Badge`. Preserve columns, row actions, and route links.

- [ ] **Step 6: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/data-table/data-table.test.tsx features/approvals/tests/approval-queue-workflow.test.tsx features/projects/tests/projects-workflow.test.tsx features/quotations/tests/quotation-normalization-queue.test.tsx features/requisitions/tests/requisitions-workflow.test.tsx features/sourcing/tests/sourcing-intake-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add apps/web/components/data-table apps/web/features/approvals/tables apps/web/features/projects/tables apps/web/features/quotations/tables apps/web/features/requisitions/tables apps/web/features/sourcing/tables
git commit -m "refactor: convert tables to shadcn primitives"
```

## Task 6: Convert Shared Forms And Identity Screens

**Files:**
- Modify: `apps/web/components/forms/form-error-summary.tsx`
- Modify: `apps/web/components/forms/form-field.tsx`
- Modify: `apps/web/features/identity/components/tenant-selection.tsx`
- Modify: `apps/web/features/identity/forms/login-form.tsx`
- Modify: `apps/web/features/identity/forms/profile-form.tsx`
- Modify: `apps/web/features/identity/workflows/account-settings-page.tsx`
- Modify: `apps/web/features/identity/workflows/login-page.tsx`
- Test: `apps/web/features/identity/tests/identity-workflow.test.tsx`
- Test: `apps/web/components/forms/forms.test.tsx`

- [ ] **Step 1: Extend form tests**

Add expectations that form labels and errors remain accessible:

```tsx
expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
expect(screen.getByRole("alert")).toBeInTheDocument();
expect(screen.getByRole("button", { name: /sign in/i })).toHaveClass("inline-flex");
```

- [ ] **Step 2: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/forms/forms.test.tsx features/identity/tests/identity-workflow.test.tsx
```

Expected: PASS before conversion.

- [ ] **Step 3: Convert form helpers**

Required replacements:

- `form-field.tsx`: use `Field`, `FieldLabel`, `FieldDescription`, `FieldError`, `Input`, `Textarea`, `Select`, and `Checkbox` where the helper renders controls.
- `form-error-summary.tsx`: use `Alert variant="destructive"`.
- `login-form.tsx`: use `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `Field`, `Label`, `Input`, `Button`, and `Alert`.
- `tenant-selection.tsx`: use `RadioGroup` or `Select` depending on current interaction. Use `Button` for submit/switch actions.
- `profile-form.tsx`: use `Form` or `Field` primitives, `Input`, `Textarea`, `Button`, and `Alert`.

- [ ] **Step 4: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/forms/forms.test.tsx features/identity/tests/identity-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/web/components/forms apps/web/features/identity
git commit -m "refactor: convert identity forms to shadcn"
```

## Task 7: Convert Requisition Workspace Screens

**Files:**
- Modify: `apps/web/features/requisitions/components/*.tsx`
- Modify: `apps/web/features/requisitions/forms/requisition-form.tsx`
- Modify: `apps/web/features/requisitions/workflows/*.tsx`
- Modify: `apps/web/app/(workspace)/requisitions/**/*.tsx`
- Test: existing requisition tests

- [ ] **Step 1: Run current requisition tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/requisitions/tests/requisitions-workflow.test.tsx features/requisitions/tests/requisition-comments.test.tsx features/requisitions/tests/requisition-draft-save-controller.test.tsx
```

Expected: PASS before conversion.

- [ ] **Step 2: Convert requisition dialogs and confirmations**

Required replacements:

- `submit-requisition-dialog.tsx`: `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter`, `DialogClose`, `Button`.
- `requisition-action-dialog.tsx`: `Dialog` for normal actions and `AlertDialog` for destructive or irreversible actions.
- `requisition-save-conflict-panel.tsx`: `Alert` and `Button`.
- `requisition-correction-panel.tsx`: `Card`, `Alert`, `Textarea`, `Button`.

- [ ] **Step 3: Convert requisition form and line-item interactions**

Required replacements:

- Raw inputs and textareas: `Input`, `Textarea`, `Select`, `Checkbox`, `Field`, `Label`.
- Template picker: `Popover`, `Command`, `CommandInput`, `CommandList`, `CommandItem`, `CommandEmpty`.
- Line item suggestions: `Popover` plus `Command` or `Combobox` pattern from the shadcn registry.
- Checklist: `Card`, `Checkbox`, `Progress`, `Badge`.
- Comments and mention input: `Card`, `Textarea`, `Button`, `ScrollArea`, `Badge`.

- [ ] **Step 4: Convert requisition workflows**

Use `PageHeader`, `SurfaceSection`, `StatusCard`, `Toolbar`, `EmptyState`, and `RecordWorkspaceLayout` with shadcn internals. Preserve routes and hook calls.

- [ ] **Step 5: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/requisitions/tests/requisitions-workflow.test.tsx features/requisitions/tests/requisition-comments.test.tsx features/requisitions/tests/requisition-form-schema.test.ts features/requisitions/tests/requisition-totals.test.ts
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/web/features/requisitions apps/web/app/\(workspace\)/requisitions
git commit -m "refactor: convert requisition screens to shadcn"
```

## Task 8: Convert Projects And Approval Policy Screens

**Files:**
- Modify: `apps/web/features/projects/{components,forms,tables,workflows}/**/*.tsx`
- Modify: `apps/web/features/approvals/{components,forms,tables,workflows}/**/*.tsx`
- Modify: `apps/web/app/(workspace)/projects/**/*.tsx`
- Modify: `apps/web/app/(workspace)/approval-policies/**/*.tsx`
- Modify: `apps/web/app/(workspace)/approvals/**/*.tsx`
- Test: project and approval tests

- [ ] **Step 1: Run current tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/projects/tests/projects-workflow.test.tsx features/approvals/tests/approval-policy-form.test.tsx features/approvals/tests/approval-policy-preview.test.tsx features/approvals/tests/approval-queue-workflow.test.tsx features/approvals/tests/approval-task-actions.test.tsx features/approvals/tests/approval-delegation.test.tsx
```

Expected: PASS before conversion.

- [ ] **Step 2: Convert projects**

Required replacements:

- Project create/edit forms: `Form`, `Field`, `Input`, `Textarea`, `Select`, `Button`, `Alert`.
- Project action dialogs: `Dialog` or `AlertDialog`.
- Budget summary and requisition pipeline: `Card`, `Progress`, `Badge`, `Table`.
- Project activity timeline: `Card`, `ScrollArea`, `Separator`, `Badge`.
- Project list/detail pages: `PageHeader`, `Toolbar`, `DataTable`, `SurfaceSection`, `StatusCard`.

- [ ] **Step 3: Convert approvals**

Required replacements:

- Approval action/delegation dialogs: `Dialog`, `DialogFooter`, `Button`, `Select`, `Textarea`, `Alert`.
- Approval policy form: `Form`, `Field`, `Input`, `Textarea`, `Select`, `Switch`, `Button`, `Alert`.
- Approval preview/stage map/SLA summary: `Card`, `Badge`, `Progress`, `Separator`, `Tooltip`.
- Approval task comments: `Card`, `Textarea`, `Button`, `ScrollArea`.
- Approval queue/detail pages: `PageHeader`, `Toolbar`, `DataTable`, `StatusCard`.

- [ ] **Step 4: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/projects/tests/projects-workflow.test.tsx features/approvals/tests/approval-policy-form.test.tsx features/approvals/tests/approval-policy-preview.test.tsx features/approvals/tests/approval-queue-workflow.test.tsx features/approvals/tests/approval-task-actions.test.tsx features/approvals/tests/approval-delegation.test.tsx features/approvals/tests/approval-task-comments.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/web/features/projects apps/web/features/approvals apps/web/app/\(workspace\)/projects apps/web/app/\(workspace\)/approval-policies apps/web/app/\(workspace\)/approvals
git commit -m "refactor: convert project and approval screens to shadcn"
```

## Task 9: Convert Sourcing, RFQ, Quotation Capture, And Vendor Portal Screens

**Files:**
- Modify: `apps/web/features/sourcing/{components,tables,workflows}/**/*.tsx`
- Modify: `apps/web/features/vendor-portal/{components,workflows}/**/*.tsx`
- Modify: `apps/web/app/(workspace)/sourcing/**/*.tsx`
- Modify: `apps/web/app/vendor/rfq-invitations/[token]/page.tsx`
- Test: sourcing and vendor portal tests

- [ ] **Step 1: Run current tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/sourcing/tests/sourcing-intake-workflow.test.tsx features/sourcing/tests/rfq-draft-workflow.test.tsx features/sourcing/tests/rfq-invitations-workflow.test.tsx features/sourcing/tests/quotation-manual-entry-panel.test.tsx features/vendor-portal/tests/vendor-rfq-portal.test.tsx features/vendor-portal/tests/vendor-quotation-manual-entry-panel.test.tsx
```

Expected: PASS before conversion.

- [ ] **Step 2: Convert sourcing intake and RFQ surfaces**

Required replacements:

- Sourcing intake decision dialog and RFQ invitation dialog: `Dialog`, `DialogFooter`, `Button`, `Select`, `Textarea`, `Alert`.
- RFQ draft form: `Card`, `Form`, `Field`, `Input`, `Textarea`, `Select`, `Button`, `Alert`, `AlertDialog`.
- Required documents editor: `Card`, `Input`, `Button`, `Table`, `DropdownMenu`.
- Vendor picker: `Popover` plus `Command`, or `RadioGroup` inside `Card` if list selection stays visible.
- RFQ line items table: `Table`, `Input`, `Textarea`, `Select`, `Button`.

- [ ] **Step 3: Convert quotation capture and version surfaces**

Required replacements:

- Quotation manual entry panels: `Card`, `Form`, `Field`, `Input`, `Textarea`, `Select`, `Button`, `Alert`.
- Quotation line item editors: `Table`, `Input`, `Textarea`, `Select`, `Button`.
- Evidence panel and version history: `Card`, `Tabs`, `ScrollArea`, `Badge`, `Button`, `Alert`.

- [ ] **Step 4: Convert vendor portal**

Required replacements:

- Vendor RFQ package: `Card`, `Accordion` if sections are collapsible, `Badge`, `Separator`.
- Vendor upload panel: `Card`, `Input`, `Button`, `Progress`, `Alert`.
- Vendor manual entry: same pattern as buyer quotation entry, without buyer-only fields.
- Vendor version history: `Tabs`, `Card`, `ScrollArea`, `Badge`.

- [ ] **Step 5: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/sourcing/tests/sourcing-intake-workflow.test.tsx features/sourcing/tests/rfq-draft-workflow.test.tsx features/sourcing/tests/rfq-invitations-workflow.test.tsx features/sourcing/tests/quotation-manual-entry-panel.test.tsx features/sourcing/tests/use-quotation-versions.test.tsx features/vendor-portal/tests/vendor-rfq-portal.test.tsx features/vendor-portal/tests/vendor-quotation-manual-entry-panel.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/web/features/sourcing apps/web/features/vendor-portal apps/web/app/\(workspace\)/sourcing apps/web/app/vendor
git commit -m "refactor: convert sourcing and vendor portal screens to shadcn"
```

## Task 10: Convert Quotation Evaluation, Normalization, Scoring, And Award Screens

**Files:**
- Modify: `apps/web/features/quotations/{components,tables,workflows}/**/*.tsx`
- Modify: `apps/web/app/(workspace)/quotations/**/*.tsx`
- Test: quotation tests

- [ ] **Step 1: Run current tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/quotations/tests/quotation-comparison-workspace.test.tsx features/quotations/tests/quotation-normalization-workspace.test.tsx features/quotations/tests/quotation-normalization-queue.test.tsx features/quotations/tests/quotation-scoring-template-form.test.tsx features/quotations/tests/rfq-award-recommendation-workspace.test.tsx features/quotations/tests/rfq-scoring-workspace.test.tsx
```

Expected: PASS before conversion.

- [ ] **Step 2: Convert comparison and commercial terms tables**

Required replacements:

- Tables: `Table` primitives with existing comparison data mapping.
- Vendor summaries: `Card`, `Badge`, `Progress`, `Tooltip`.
- Notes panel: `Card`, `Textarea`, `Button`, `Alert`.
- Readiness banner: `Alert` with semantic variant.

- [ ] **Step 3: Convert normalization workspace**

Required replacements:

- Field review: `Card`, `Input`, `Textarea`, `Select`, `Checkbox`, `Alert`.
- Issue list and issue badges: `Badge`, `Card`, `ScrollArea`.
- Line mapping panel: `Table`, `Select`, `Button`, `AlertDialog` where destructive reset exists.
- Attachment and approval panels: `Card`, `Button`, `Alert`, `Progress`.

- [ ] **Step 4: Convert scoring and award workspace**

Required replacements:

- Scoring template form and picker: `Form`, `Field`, `Input`, `Textarea`, `Select`, `Button`, `Card`, `Tabs`.
- Scorecard matrix: `Table`, `Badge`, `Tooltip`, `Progress`.
- Award vendor option list and evidence selector: `Card`, `RadioGroup`, `Checkbox`, `Badge`, `Button`.
- Award rationale form: `Textarea`, `Field`, `Alert`, `Button`.
- Award approval and PO handoff panels: `Card`, `Alert`, `Badge`, `Button`.

- [ ] **Step 5: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/quotations/tests/quotation-comparison-workspace.test.tsx features/quotations/tests/quotation-normalization-workspace.test.tsx features/quotations/tests/quotation-normalization-queue.test.tsx features/quotations/tests/quotation-normalization-approval-panel.test.tsx features/quotations/tests/quotation-normalization-field-review.test.tsx features/quotations/tests/quotation-scoring-template-form.test.tsx features/quotations/tests/rfq-award-recommendation-workspace.test.tsx features/quotations/tests/rfq-scoring-workspace.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/web/features/quotations apps/web/app/\(workspace\)/quotations
git commit -m "refactor: convert quotation workspaces to shadcn"
```

## Task 11: Convert Procurement Calendar, Attachments, Notifications, System Readiness, And Marketing/Dashboard Screens

**Files:**
- Modify: `apps/web/app/page.tsx`
- Modify: `apps/web/app/(dashboard)/dashboard/page.tsx`
- Modify: `apps/web/app/(workspace)/calendar/page.tsx`
- Modify: `apps/web/app/(workspace)/system/page.tsx`
- Modify: `apps/web/features/procurement-calendar/{components,workflows}/**/*.tsx`
- Modify: `apps/web/features/attachments/components/**/*.tsx`
- Modify: `apps/web/features/notifications/components/**/*.tsx`
- Modify: `apps/web/features/system-readiness/{components,workflows}/**/*.tsx`
- Test: related tests

- [ ] **Step 1: Run current tests**

Run:

```bash
pnpm --filter @cognify/web test -- app/page.test.tsx features/procurement-calendar/tests/procurement-calendar-workflow.test.tsx features/attachments/tests/attachments-workflow.test.tsx features/notifications/tests/notification-center.test.tsx features/system-readiness/tests/system-status-page.test.tsx features/system-readiness/tests/system-status-footer.test.tsx
```

Expected: PASS before conversion.

- [ ] **Step 2: Convert procurement calendar controls and detail surfaces**

Required replacements:

- Filters: `Popover`, `Select`, `Checkbox`, `Button`, `Badge`.
- View switcher: `Tabs` or `ToggleGroup`.
- Event detail: `Dialog` or `Sheet`, `Card`, `Badge`, `Separator`.
- Summary: `Card`, `StatusCard`, `Progress`.
- Month/week/agenda layouts may keep custom grid markup, but their event chips must use `Badge` or `Button` and their framed panels must use `Card`.

- [ ] **Step 3: Convert attachments and notifications**

Required replacements:

- Attachment uploader: `Card`, `Input`, `Button`, `Progress`, `Alert`.
- Attachment list and preview: `Table` or `Card`, `DropdownMenu`, `Dialog`, `ScrollArea`.
- Notification center and items: `Sheet` or `Popover`, `ScrollArea`, `Card`, `Badge`, `Button`.
- Notification preferences: `Field`, `Switch`, `Checkbox`, `Select`, `Button`.

- [ ] **Step 4: Convert system readiness and public/dashboard screens**

Required replacements:

- System summary/check list/demo dataset summary: `Card`, `Badge`, `Progress`, `Alert`, `Table`.
- Public home and dashboard cards: official `Card`, `Badge`, `Button`, and `Separator`; no custom bordered card divs.
- Keep the current dark mode and layout intent unless tests or visual inspection show contrast issues.

- [ ] **Step 5: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- app/page.test.tsx features/procurement-calendar/tests/procurement-calendar-workflow.test.tsx features/attachments/tests/attachments-workflow.test.tsx features/notifications/tests/notification-center.test.tsx features/system-readiness/tests/system-status-page.test.tsx features/system-readiness/tests/system-status-footer.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/web/app/page.tsx apps/web/app/\(dashboard\) apps/web/app/\(workspace\)/calendar apps/web/app/\(workspace\)/system apps/web/features/procurement-calendar apps/web/features/attachments apps/web/features/notifications apps/web/features/system-readiness
git commit -m "refactor: convert calendar and utility screens to shadcn"
```

## Task 12: Convert Record Workspace And Workflow Primitives

**Files:**
- Modify: `apps/web/components/workspace/record-workspace-layout.tsx`
- Modify: `apps/web/components/workflow/activity-timeline.tsx`
- Modify: `apps/web/components/workflow/status-badge.tsx`
- Test: existing workspace/workflow primitive tests

- [ ] **Step 1: Run current tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/workspace/record-workspace-layout.test.tsx components/workflow/workflow-primitives.test.tsx
```

Expected: PASS before conversion.

- [ ] **Step 2: Convert record workspace layout**

Required replacements:

- Back link: `Button variant="outline"` with `asChild` if supported, or `buttonVariants`.
- Metadata/action summary panel: `Card`, `CardHeader`, `CardContent`.
- Section navigation: `Tabs` if it controls local content, otherwise `Button variant="ghost"` links with `ScrollArea`.
- Sidebar panels: require children to be `Card`-like or wrap plain sidebar content in `SurfaceSection`.

- [ ] **Step 3: Convert workflow primitives**

Required replacements:

- Status badge: official `Badge` variants with status-to-variant mapping.
- Activity timeline: `Card`, `ScrollArea`, `Separator`, `Badge`, and lucide icons.

- [ ] **Step 4: Run focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/workspace/record-workspace-layout.test.tsx components/workflow/workflow-primitives.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/web/components/workspace apps/web/components/workflow
git commit -m "refactor: convert workspace primitives to shadcn"
```

## Task 13: Run Shadcn Audit And Remove Remaining Raw UI

**Files:**
- Modify: any files reported by `pnpm audit:shadcn-ui`
- Modify: `tooling/scripts/audit-shadcn-first-ui.mjs` only to add justified exceptions

- [ ] **Step 1: Run audit**

Run:

```bash
pnpm audit:shadcn-ui
```

Expected: FAIL only for justified exceptions such as custom calendar grid layout or `DataTable` internals if `Table` primitive wrapping still produces a raw-table regex hit. No raw styled buttons, inputs, selects, textareas, hand-rolled dialogs, or custom bordered panels should remain.

- [ ] **Step 2: Fix remaining violations**

For every reported file:

- Replace raw controls with `@cognify/ui` primitives.
- Replace hand-rolled overlays with `Dialog`, `Sheet`, `Popover`, `DropdownMenu`, or `Command`.
- Replace custom panel divs with `Card`, `Alert`, `Empty`, or an app-level composite.
- Add a script exception only when the markup is a semantic layout that has no official shadcn component equivalent.

- [ ] **Step 3: Run audit again**

Run:

```bash
pnpm audit:shadcn-ui
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add apps/web packages/ui tooling/scripts/audit-shadcn-first-ui.mjs docs/04-engineering/standards/shadcn-first-ui.md
git commit -m "chore: enforce shadcn first ui audit"
```

## Task 14: Accessibility, Responsive, And Visual Verification

**Files:**
- Modify: tests only if they need stronger accessibility assertions

- [ ] **Step 1: Run component and workflow tests**

Run:

```bash
pnpm --filter @cognify/web test
```

Expected: PASS.

- [ ] **Step 2: Run typecheck**

Run:

```bash
pnpm --filter @cognify/ui typecheck
pnpm --filter @cognify/web typecheck
```

Expected: PASS.

- [ ] **Step 3: Run lint**

Run:

```bash
pnpm --filter @cognify/web lint
```

Expected: PASS.

- [ ] **Step 4: Run build**

Run:

```bash
pnpm --filter @cognify/web build
```

Expected: PASS.

- [ ] **Step 5: Run E2E checks**

Run:

```bash
pnpm --filter @cognify/web test:e2e
```

Expected: PASS. If local services are required, run `pnpm dev:services` first and use documented local setup from `docs/05-runbooks/local-development.md`.

- [ ] **Step 6: Run manual responsive smoke**

Start the dev server:

```bash
pnpm --filter @cognify/web dev
```

Open the app at `http://127.0.0.1:8880` and inspect these viewport widths:

- 375px: login, requisition detail, sourcing RFQ draft, approval task, vendor portal invitation.
- 768px: projects list, quotations comparison, calendar, system readiness.
- 1440px: app shell, command palette, right panel, data tables, record workspace sidebars.

Expected:

- No horizontal scroll except intentionally scrollable dense tables.
- No text overlap inside buttons, cards, badges, tabs, menus, or dialogs.
- Focus rings visible on all keyboard-reachable controls.
- Dialogs, sheets, popovers, selects, and command palette trap or restore focus correctly.
- Icon-only buttons have accessible names.

- [ ] **Step 7: Stop dev server**

Stop the running dev server with `Ctrl+C`.

- [ ] **Step 8: Commit verification-only test changes**

If tests were modified during this task:

```bash
git add apps/web
git commit -m "test: harden shadcn ui verification"
```

If no files changed, skip this commit.

## Task 15: Final Documentation And Completion Gate

**Files:**
- Modify: `docs/04-engineering/standards/shadcn-first-ui.md`
- Modify: this plan file only to check completed boxes if the executor tracks progress in the document

- [ ] **Step 1: Update standard with approved exceptions**

If Task 13 found justified exceptions, add them under an "Approved Exceptions" section in `docs/04-engineering/standards/shadcn-first-ui.md` with the file path and reason. Use this exact format:

```markdown
## Approved Exceptions

- `apps/web/features/procurement-calendar/components/procurement-calendar-month-view.tsx`: custom calendar grid layout is retained for procurement event density; controls and event detail surfaces still use shadcn primitives.
```

Do not add exceptions for raw buttons, inputs, selects, textareas, hand-rolled dialogs, or custom bordered cards.

- [ ] **Step 2: Run final verification**

Run:

```bash
pnpm audit:shadcn-ui
pnpm --filter @cognify/ui typecheck
pnpm --filter @cognify/web test
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web build
pnpm --filter @cognify/web test:e2e
```

Expected: all commands PASS.

- [ ] **Step 3: Check git status**

Run:

```bash
git status --short --branch
```

Expected: clean working tree except intentional uncommitted files if the user explicitly requested no commit.

- [ ] **Step 4: Final commit**

If documentation changed:

```bash
git add docs/04-engineering/standards/shadcn-first-ui.md docs/superpowers/plans/2026-05-29-shadcn-first-screen-conversion.md
git commit -m "docs: finalize shadcn first ui conversion plan"
```

If the plan file must remain uncommitted for review, do not run this commit.

## Final Acceptance Criteria

- `packages/ui` exports official shadcn primitives needed by the current app.
- All current app routes listed in the screen inventory render through shadcn primitives or Cognify composites built from them.
- No raw styled `button`, `input`, `select`, `textarea`, `table`, hand-rolled dialog, or custom bordered panel remains without a documented exception.
- App shell, mobile nav, command palette, right panel, forms, tables, dialogs, sheets, empty states, loading states, alerts, and toasts use official shadcn/Radix behavior where available.
- `packages/ui` remains business-neutral.
- Cognify-specific components remain in `apps/web`.
- Existing MSW and generated-client boundaries are unchanged.
- Focus management and keyboard navigation are improved or preserved for overlays, menus, command palette, tabs, selects, and popovers.
- Final verification commands pass:

```bash
pnpm audit:shadcn-ui
pnpm --filter @cognify/ui typecheck
pnpm --filter @cognify/web test
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web build
pnpm --filter @cognify/web test:e2e
```
