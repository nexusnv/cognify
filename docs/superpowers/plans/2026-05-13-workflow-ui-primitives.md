# Workflow UI Primitives Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Cognify's app-owned workflow UI primitives for right panels, dense data tables, validation-heavy forms, workflow states, and audit timelines, then prove them by migrating the requisition workflow.

**Architecture:** Keep primitives in `apps/web/components` because they encode Cognify workflow conventions while remaining reusable across app features. Preserve backend/API contracts, keep `packages/ui` primitive-only, and migrate requisition components as the first consumer so each primitive is exercised by real MSW-backed workflow tests.

**Tech Stack:** Next.js App Router, React 19, TypeScript, Tailwind CSS v4, TanStack Query, MSW, Vitest, Testing Library, Zod, lucide-react.

---

## Execution Rules

- Do not commit the design or plan documents while preparing the review handoff.
- During implementation, use the commit checkpoints in this plan only after each task passes its verification.
- Do not edit `packages/ui`, `packages/api-client`, `apps/api`, or `apps/api/storage/openapi/openapi.json` for this slice.
- Do not import MSW fixtures into production components.
- Keep data loading in feature hooks. Primitives render state and interactions only.
- If implementation reveals an API contract change, stop and update the design spec before coding the contract change.

## Source Documents

- Spec: `docs/superpowers/specs/2026-05-13-workflow-ui-primitives-design.md`
- Feature runbook: `docs/05-runbooks/feature-development.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Greenfield runbook design: `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`

## File Structure

Create:

- `apps/web/components/data-table/data-table.tsx`: typed desktop table plus mobile list fallback.
- `apps/web/components/data-table/data-table-empty-state.tsx`: reusable empty/error/loading support views.
- `apps/web/components/data-table/data-table-types.ts`: table column, row action, sorting, pagination contracts.
- `apps/web/components/data-table/use-data-table-state.ts`: lightweight sort state hook.
- `apps/web/components/data-table/data-table.test.tsx`: table states, sorting, actions, mobile fallback coverage.
- `apps/web/components/forms/form-error-summary.tsx`: accessible validation summary.
- `apps/web/components/forms/form-field.tsx`: labeled field wrapper with description/error wiring.
- `apps/web/components/forms/validation-errors.ts`: Zod/API validation normalization helpers.
- `apps/web/components/forms/forms.test.tsx`: form helper tests.
- `apps/web/components/right-panel/right-panel-provider.tsx`: context provider and hook.
- `apps/web/components/right-panel/right-panel-root.tsx`: dialog surface, overlay, keyboard close, scroll lock.
- `apps/web/components/right-panel/right-panel-trigger.tsx`: helper trigger for feature actions.
- `apps/web/components/right-panel/right-panel-types.ts`: right panel contracts.
- `apps/web/components/right-panel/right-panel.test.tsx`: provider/root behavior tests.
- `apps/web/components/workflow/activity-timeline.tsx`: reusable audit-shaped timeline.
- `apps/web/components/workflow/status-badge.tsx`: reusable workflow status badge.
- `apps/web/components/workflow/workflow-state.ts`: workflow tone/config types and class mapping.
- `apps/web/components/workflow/workflow-primitives.test.tsx`: status and timeline tests.

Modify:

- `apps/web/components/providers/app-providers.tsx`: include `RightPanelProvider`.
- `apps/web/components/shell/right-panel-host.tsx`: render `RightPanelRoot`.
- `apps/web/features/requisitions/components/requisition-status-badge.tsx`: become a typed wrapper around `StatusBadge`.
- `apps/web/features/requisitions/components/requisition-activity-timeline.tsx`: become a typed wrapper around `ActivityTimeline`.
- `apps/web/features/requisitions/forms/requisition-form.tsx`: use form primitives and validation helpers.
- `apps/web/features/requisitions/tables/requisitions-table.tsx`: use `DataTable`.
- `apps/web/features/requisitions/workflows/requisition-list-page.tsx`: pass table state, meta, and quick-panel actions.
- `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`: continue using the requisition timeline wrapper.
- `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`: add quick-panel and primitive regression coverage.

Do not delete the requisition wrapper files. They are useful feature-level adapters that keep status maps and action icon maps local to the requisition domain.

## Workflow Map

```txt
Authenticated user
  -> AppShell from existing layouts
  -> RightPanelProvider from AppProviders
  -> feature workflow page
  -> app-level primitives
  -> feature hooks backed by generated client or MSW
```

Failure paths:

- API validation errors normalize into field-level messages and a summary.
- Empty table data renders an explicit empty state.
- Loading table data renders fixed-height skeleton rows.
- Right panel closes by button, Escape, overlay, and route change.
- Unavailable actions remain hidden by feature permission checks.

## Task 1: Baseline and Primitive Test Scaffolds

**Files:**

- Read: `docs/superpowers/specs/2026-05-13-workflow-ui-primitives-design.md`
- Read: `docs/05-runbooks/feature-development.md`
- Read: `apps/web/features/requisitions/tables/requisitions-table.tsx`
- Read: `apps/web/features/requisitions/forms/requisition-form.tsx`
- Create: `apps/web/components/right-panel/right-panel.test.tsx`
- Create: `apps/web/components/workflow/workflow-primitives.test.tsx`
- Create: `apps/web/components/forms/forms.test.tsx`
- Create: `apps/web/components/data-table/data-table.test.tsx`

- [ ] **Step 1: Confirm clean execution baseline**

Run:

```bash
git status --short --branch
```

Expected:

```txt
## main...origin/main
?? docs/superpowers/specs/2026-05-13-workflow-ui-primitives-design.md
?? docs/superpowers/plans/2026-05-13-workflow-ui-primitives.md
```

Additional untracked or modified files are user work. Do not revert them.

- [ ] **Step 2: Add failing right panel tests**

Create `apps/web/components/right-panel/right-panel.test.tsx`:

```tsx
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { RightPanelProvider, useRightPanel } from "./right-panel-provider";
import { RightPanelRoot } from "./right-panel-root";

vi.mock("next/navigation", () => ({
  usePathname: () => "/requisitions",
}));

function PanelHarness() {
  const rightPanel = useRightPanel();

  return (
    <>
      <button
        type="button"
        onClick={() =>
          rightPanel.openPanel({
            id: "requisition-preview",
            title: "Field laptop refresh",
            description: "REQ-2026-000001",
            size: "md",
            content: <p>Requester: Test User</p>,
            footer: <a href="/requisitions/req-1">Open workspace</a>,
          })
        }
      >
        Open panel
      </button>
      <button
        type="button"
        onClick={() =>
          rightPanel.openPanel({
            id: "replacement",
            title: "Replacement panel",
            content: <p>Second panel content</p>,
          })
        }
      >
        Replace panel
      </button>
      <RightPanelRoot />
    </>
  );
}

function renderPanel() {
  return render(
    <RightPanelProvider>
      <PanelHarness />
    </RightPanelProvider>,
  );
}

describe("right panel", () => {
  it("opens a labelled dialog with content and footer", async () => {
    const user = userEvent.setup();
    renderPanel();

    await user.click(screen.getByRole("button", { name: "Open panel" }));

    expect(screen.getByRole("dialog", { name: "Field laptop refresh" })).toHaveAttribute(
      "aria-modal",
      "true",
    );
    expect(screen.getByText("REQ-2026-000001")).toBeInTheDocument();
    expect(screen.getByText("Requester: Test User")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open workspace" })).toHaveAttribute(
      "href",
      "/requisitions/req-1",
    );
  });

  it("replaces panel content without rendering stale content", async () => {
    const user = userEvent.setup();
    renderPanel();

    await user.click(screen.getByRole("button", { name: "Open panel" }));
    await user.click(screen.getByRole("button", { name: "Replace panel" }));

    expect(screen.getByRole("dialog", { name: "Replacement panel" })).toBeInTheDocument();
    expect(screen.getByText("Second panel content")).toBeInTheDocument();
    expect(screen.queryByText("Requester: Test User")).not.toBeInTheDocument();
  });

  it("closes by close button, Escape, and overlay", async () => {
    const user = userEvent.setup();
    renderPanel();

    await user.click(screen.getByRole("button", { name: "Open panel" }));
    await user.click(screen.getByRole("button", { name: "Close panel" }));
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Open panel" }));
    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Open panel" }));
    await user.click(screen.getByTestId("right-panel-overlay"));
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});
```

- [ ] **Step 3: Add failing workflow primitive tests**

Create `apps/web/components/workflow/workflow-primitives.test.tsx`:

```tsx
import { CheckCircle2, CircleDot, FileClock, Send } from "lucide-react";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ActivityTimeline } from "./activity-timeline";
import { StatusBadge } from "./status-badge";
import type { WorkflowStateConfig } from "./workflow-state";

type TestStatus = "draft" | "submitted";

const statusConfig = {
  draft: {
    label: "Draft",
    description: "The requester can still edit this record.",
    tone: "draft",
    icon: CircleDot,
  },
  submitted: {
    label: "Submitted",
    description: "The record has been submitted for review.",
    tone: "success",
    icon: CheckCircle2,
  },
} satisfies WorkflowStateConfig<TestStatus>;

describe("StatusBadge", () => {
  it("renders icon, label, and accessible description", () => {
    render(<StatusBadge status="draft" config={statusConfig} />);

    expect(screen.getByText("Draft")).toBeInTheDocument();
    expect(screen.getByText("The requester can still edit this record.")).toHaveClass("sr-only");
  });

  it("supports compact rendering", () => {
    render(<StatusBadge status="submitted" config={statusConfig} size="compact" />);

    expect(screen.getByText("Submitted")).toBeInTheDocument();
  });
});

describe("ActivityTimeline", () => {
  it("renders an empty state", () => {
    render(<ActivityTimeline events={[]} emptyMessage="No activity yet." />);

    expect(screen.getByText("No activity yet.")).toBeInTheDocument();
  });

  it("renders audit-shaped events with actor and time", () => {
    render(
      <ActivityTimeline
        events={[
          {
            id: "audit-1",
            action: "requisition.submitted",
            message: "Requisition submitted",
            occurredAt: "2026-05-13T08:00:00.000Z",
            actor: { id: "user-1", name: "Test User", email: "test@example.com" },
          },
        ]}
        actionIcons={{
          "requisition.submitted": Send,
          default: FileClock,
        }}
      />,
    );

    expect(screen.getByRole("list")).toBeInTheDocument();
    expect(screen.getByText("Requisition submitted")).toBeInTheDocument();
    expect(screen.getByText(/Test User/)).toBeInTheDocument();
    expect(screen.getByText(/2026/)).toBeInTheDocument();
  });
});
```

- [ ] **Step 4: Add failing form primitive tests**

Create `apps/web/components/forms/forms.test.tsx`:

```tsx
import { z } from "zod";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { FormErrorSummary } from "./form-error-summary";
import { FormField } from "./form-field";
import {
  flattenZodFieldErrors,
  focusFirstInvalidField,
  normalizeValidationErrors,
} from "./validation-errors";

describe("FormField", () => {
  it("wires labels, descriptions, and errors to the control", () => {
    render(
      <FormField
        htmlFor="title"
        label="Title"
        description="Use a short procurement title."
        error="Title is required."
        required
      >
        <input id="title" aria-invalid="true" />
      </FormField>,
    );

    expect(screen.getByLabelText(/Title/)).toHaveAccessibleDescription(
      "Use a short procurement title. Title is required.",
    );
    expect(screen.getByText("Required")).toBeInTheDocument();
  });
});

describe("FormErrorSummary", () => {
  it("renders linked field errors", () => {
    render(
      <FormErrorSummary
        title="Complete the highlighted fields before submitting."
        errors={[
          { field: "title", fieldId: "title", message: "Title is required." },
          { field: "neededByDate", fieldId: "needed-by", message: "Needed-by date is required." },
        ]}
      />,
    );

    expect(screen.getByRole("alert")).toHaveTextContent(
      "Complete the highlighted fields before submitting.",
    );
    expect(screen.getByRole("link", { name: "Title is required." })).toHaveAttribute(
      "href",
      "#title",
    );
  });
});

describe("validation error helpers", () => {
  it("flattens zod field errors", () => {
    const schema = z.object({
      title: z.string().min(1, "Title is required."),
    });
    const result = schema.safeParse({ title: "" });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(flattenZodFieldErrors(result.error.flatten().fieldErrors)).toEqual([
        { field: "title", message: "Title is required." },
      ]);
    }
  });

  it("normalizes API validation error shapes", () => {
    const error = {
      details: {
        fields: {
          title: ["Title is required."],
          neededByDate: ["Needed-by date is required."],
        },
      },
    };

    expect(normalizeValidationErrors(error)).toEqual([
      { field: "title", message: "Title is required." },
      { field: "neededByDate", message: "Needed-by date is required." },
    ]);
  });

  it("focuses the first invalid field", () => {
    render(
      <form>
        <input id="first" aria-invalid="true" />
        <input id="second" aria-invalid="true" />
      </form>,
    );

    focusFirstInvalidField(document);

    expect(screen.getByRole("textbox")).toHaveFocus();
  });
});
```

- [ ] **Step 5: Add failing data table tests**

Create `apps/web/components/data-table/data-table.test.tsx`:

```tsx
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { DataTable } from "./data-table";
import { useDataTableState } from "./use-data-table-state";
import type { DataTableColumn } from "./data-table-types";

type Row = {
  id: string;
  number: string;
  title: string;
  status: string;
  total: string;
};

const rows: Row[] = [
  { id: "req-1", number: "REQ-2026-000001", title: "Field laptop refresh", status: "Draft", total: "MYR 3,600.00" },
  { id: "req-2", number: "REQ-2026-000002", title: "Office chairs", status: "Submitted", total: "MYR 1,200.00" },
];

const columns: DataTableColumn<Row>[] = [
  { id: "number", header: "Number", cell: (row) => row.number, widthClassName: "w-36" },
  { id: "title", header: "Title", cell: (row) => row.title, sortable: true },
  { id: "status", header: "Status", cell: (row) => row.status, hideOnMobile: false },
  { id: "total", header: "Estimated total", cell: (row) => row.total, align: "right" },
];

function SortHarness() {
  const tableState = useDataTableState({ initialSort: { columnId: "title", direction: "asc" } });

  return (
    <DataTable
      caption="Requisitions"
      rows={rows}
      columns={columns}
      getRowId={(row) => row.id}
      sort={tableState.sort}
      onSortChange={tableState.setSort}
      renderMobileRow={(row) => (
        <a href={`/requisitions/${row.id}`}>
          {row.title}
          <span>{row.total}</span>
        </a>
      )}
    />
  );
}

describe("DataTable", () => {
  it("renders desktop table rows with a caption and row actions", () => {
    render(
      <DataTable
        caption="Requisitions"
        rows={rows}
        columns={columns}
        getRowId={(row) => row.id}
        renderRowActions={(row) => <a href={`/requisitions/${row.id}`}>Open {row.number}</a>}
        renderMobileRow={(row) => <a href={`/requisitions/${row.id}`}>{row.title}</a>}
      />,
    );

    expect(screen.getByRole("table", { name: "Requisitions" })).toBeInTheDocument();
    expect(screen.getByText("REQ-2026-000001")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open REQ-2026-000001" })).toHaveAttribute(
      "href",
      "/requisitions/req-1",
    );
  });

  it("renders loading, error, and empty states", () => {
    const retry = vi.fn();
    const { rerender } = render(
      <DataTable
        caption="Requisitions"
        rows={[]}
        columns={columns}
        getRowId={(row) => row.id}
        state="loading"
        loadingLabel="Loading requisitions"
      />,
    );
    expect(screen.getByLabelText("Loading requisitions")).toBeInTheDocument();

    rerender(
      <DataTable
        caption="Requisitions"
        rows={[]}
        columns={columns}
        getRowId={(row) => row.id}
        state="error"
        errorTitle="Requisitions could not be loaded."
        onRetry={retry}
      />,
    );
    screen.getByRole("button", { name: "Retry" }).click();
    expect(retry).toHaveBeenCalledTimes(1);

    rerender(
      <DataTable
        caption="Requisitions"
        rows={[]}
        columns={columns}
        getRowId={(row) => row.id}
        state="empty"
        emptyTitle="No requisitions yet"
        emptyDescription="Create the first draft requisition for this tenant."
      />,
    );
    expect(screen.getByText("No requisitions yet")).toBeInTheDocument();
  });

  it("toggles sort state for sortable headers", async () => {
    const user = userEvent.setup();
    render(<SortHarness />);

    const titleHeader = screen.getByRole("columnheader", { name: /Title/ });
    expect(titleHeader).toHaveAttribute("aria-sort", "ascending");

    await user.click(within(titleHeader).getByRole("button", { name: "Sort by Title descending" }));
    expect(screen.getByRole("columnheader", { name: /Title/ })).toHaveAttribute(
      "aria-sort",
      "descending",
    );
  });
});
```

- [ ] **Step 6: Run the failing primitive tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/right-panel/right-panel.test.tsx components/workflow/workflow-primitives.test.tsx components/forms/forms.test.tsx components/data-table/data-table.test.tsx
```

Expected: FAIL because the imported primitive files do not exist.

## Task 2: Implement Right Panel Provider and Root

**Files:**

- Create: `apps/web/components/right-panel/right-panel-types.ts`
- Create: `apps/web/components/right-panel/right-panel-provider.tsx`
- Create: `apps/web/components/right-panel/right-panel-root.tsx`
- Create: `apps/web/components/right-panel/right-panel-trigger.tsx`
- Modify: `apps/web/components/providers/app-providers.tsx`
- Modify: `apps/web/components/shell/right-panel-host.tsx`

- [ ] **Step 1: Add right panel types**

Create `apps/web/components/right-panel/right-panel-types.ts`:

```tsx
import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

export type RightPanelSize = "sm" | "md" | "lg";

export type RightPanelDefinition = {
  id: string;
  title: string;
  description?: string;
  icon?: LucideIcon;
  size?: RightPanelSize;
  content: ReactNode;
  footer?: ReactNode;
};

export type RightPanelContextValue = {
  panel: RightPanelDefinition | null;
  openPanel: (panel: RightPanelDefinition) => void;
  closePanel: () => void;
};
```

- [ ] **Step 2: Add provider and hook**

Create `apps/web/components/right-panel/right-panel-provider.tsx`:

```tsx
"use client";

import { createContext, useCallback, useContext, useMemo, useState } from "react";
import type { ReactNode } from "react";
import type { RightPanelContextValue, RightPanelDefinition } from "./right-panel-types";

const RightPanelContext = createContext<RightPanelContextValue | undefined>(undefined);

export function RightPanelProvider({ children }: { children: ReactNode }) {
  const [panel, setPanel] = useState<RightPanelDefinition | null>(null);
  const openPanel = useCallback((nextPanel: RightPanelDefinition) => setPanel(nextPanel), []);
  const closePanel = useCallback(() => setPanel(null), []);

  const value = useMemo<RightPanelContextValue>(
    () => ({
      panel,
      openPanel,
      closePanel,
    }),
    [closePanel, openPanel, panel],
  );

  return <RightPanelContext.Provider value={value}>{children}</RightPanelContext.Provider>;
}

export function useRightPanel() {
  const context = useContext(RightPanelContext);

  if (!context) {
    throw new Error("useRightPanel must be used within RightPanelProvider.");
  }

  return context;
}
```

- [ ] **Step 3: Add root dialog surface**

Create `apps/web/components/right-panel/right-panel-root.tsx`:

```tsx
"use client";

import { X } from "lucide-react";
import { useEffect, useRef } from "react";
import { usePathname } from "next/navigation";
import { useRightPanel } from "./right-panel-provider";
import type { RightPanelSize } from "./right-panel-types";

const widthClassBySize: Record<RightPanelSize, string> = {
  sm: "sm:max-w-96",
  md: "sm:max-w-[32rem]",
  lg: "sm:max-w-[40rem]",
};

export function RightPanelRoot() {
  const { panel, closePanel } = useRightPanel();
  const pathname = usePathname();
  const previousPathname = useRef(pathname);

  useEffect(() => {
    if (!panel) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [panel]);

  useEffect(() => {
    if (!panel) return undefined;

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") closePanel();
    }

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [closePanel, panel]);

  useEffect(() => {
    if (previousPathname.current !== pathname) {
      previousPathname.current = pathname;
      closePanel();
    }
  }, [closePanel, pathname]);

  if (!panel) return null;

  const titleId = `${panel.id}-title`;
  const descriptionId = panel.description ? `${panel.id}-description` : undefined;
  const Icon = panel.icon;
  const widthClassName = widthClassBySize[panel.size ?? "md"];

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <button
        type="button"
        data-testid="right-panel-overlay"
        aria-label="Close panel overlay"
        className="absolute inset-0 cursor-default bg-black/30"
        onClick={closePanel}
      />
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descriptionId}
        className={`relative flex h-full w-full flex-col border-l bg-background shadow-xl ${widthClassName}`}
      >
        <header className="flex items-start justify-between gap-3 border-b p-4">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              {Icon ? <Icon className="h-4 w-4 shrink-0" aria-hidden="true" /> : null}
              <h2 id={titleId} className="truncate text-base font-semibold">
                {panel.title}
              </h2>
            </div>
            {panel.description ? (
              <p id={descriptionId} className="mt-1 text-sm text-muted-foreground">
                {panel.description}
              </p>
            ) : null}
          </div>
          <button
            type="button"
            className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border"
            onClick={closePanel}
            aria-label="Close panel"
          >
            <X className="h-4 w-4" aria-hidden="true" />
          </button>
        </header>
        <div className="min-h-0 flex-1 overflow-y-auto p-4">{panel.content}</div>
        {panel.footer ? <footer className="border-t p-4">{panel.footer}</footer> : null}
      </section>
    </div>
  );
}
```

- [ ] **Step 4: Add trigger helper**

Create `apps/web/components/right-panel/right-panel-trigger.tsx`:

```tsx
"use client";

import { PanelRightOpen } from "lucide-react";
import type { ReactNode } from "react";
import { useRightPanel } from "./right-panel-provider";
import type { RightPanelDefinition } from "./right-panel-types";

export function RightPanelTrigger({
  panel,
  children,
  className,
  ariaLabel,
}: {
  panel: RightPanelDefinition;
  children?: ReactNode;
  className?: string;
  ariaLabel?: string;
}) {
  const rightPanel = useRightPanel();

  return (
    <button
      type="button"
      className={
        className ??
        "inline-flex min-h-11 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"
      }
      onClick={() => rightPanel.openPanel(panel)}
      aria-label={ariaLabel}
    >
      {children ?? (
        <>
          <PanelRightOpen className="h-4 w-4" aria-hidden="true" />
          Open panel
        </>
      )}
    </button>
  );
}
```

- [ ] **Step 5: Wire provider into app providers**

Modify `apps/web/components/providers/app-providers.tsx` so the rendered provider stack includes `RightPanelProvider` inside the existing query provider:

```tsx
"use client";

import { Toaster } from "sonner";
import type { ReactNode } from "react";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { AccessibilityProvider } from "./accessibility-provider";
import { AnalyticsProvider } from "./analytics-provider";
import { ErrorReportingProvider } from "./error-reporting-provider";
import { QueryProvider } from "./query-provider";
import { ThemeProvider } from "./theme-provider";

export function AppProviders({ children }: { children: ReactNode }) {
  return (
    <ErrorReportingProvider>
      <ThemeProvider>
        <AccessibilityProvider>
          <AnalyticsProvider>
            <QueryProvider>
              <RightPanelProvider>
                {children}
              </RightPanelProvider>
            </QueryProvider>
            <Toaster richColors />
          </AnalyticsProvider>
        </AccessibilityProvider>
      </ThemeProvider>
    </ErrorReportingProvider>
  );
}
```

Preserve the existing provider order and add only `RightPanelProvider` inside `QueryProvider`.

- [ ] **Step 6: Render root in shell host**

Modify `apps/web/components/shell/right-panel-host.tsx`:

```tsx
"use client";

import { RightPanelRoot } from "@/components/right-panel/right-panel-root";

export function RightPanelHost() {
  return (
    <div id="right-panel-host">
      <RightPanelRoot />
    </div>
  );
}
```

- [ ] **Step 7: Verify right panel tests pass**

Run:

```bash
pnpm --filter @cognify/web test -- components/right-panel/right-panel.test.tsx
```

Expected: PASS.

- [ ] **Step 8: Commit checkpoint**

Run during implementation only:

```bash
git add apps/web/components/right-panel apps/web/components/providers/app-providers.tsx apps/web/components/shell/right-panel-host.tsx
git commit -m "feat(web): add workflow right panel primitive"
```

Expected: commit created with only right panel files.

## Task 3: Implement Workflow Status and Activity Primitives

**Files:**

- Create: `apps/web/components/workflow/workflow-state.ts`
- Create: `apps/web/components/workflow/status-badge.tsx`
- Create: `apps/web/components/workflow/activity-timeline.tsx`
- Modify: `apps/web/features/requisitions/components/requisition-status-badge.tsx`
- Modify: `apps/web/features/requisitions/components/requisition-activity-timeline.tsx`

- [ ] **Step 1: Add workflow state contracts**

Create `apps/web/components/workflow/workflow-state.ts`:

```tsx
import type { LucideIcon } from "lucide-react";

export type WorkflowTone = "neutral" | "draft" | "info" | "success" | "warning" | "danger" | "locked";

export type WorkflowStateConfig<TStatus extends string> = Record<
  TStatus,
  {
    label: string;
    description: string;
    tone: WorkflowTone;
    icon: LucideIcon;
  }
>;

export const workflowToneClassNames: Record<WorkflowTone, string> = {
  neutral: "border-slate-300 bg-slate-50 text-slate-900",
  draft: "border-amber-300 bg-amber-50 text-amber-950",
  info: "border-blue-300 bg-blue-50 text-blue-950",
  success: "border-emerald-300 bg-emerald-50 text-emerald-950",
  warning: "border-orange-300 bg-orange-50 text-orange-950",
  danger: "border-red-300 bg-red-50 text-red-950",
  locked: "border-zinc-300 bg-zinc-100 text-zinc-950",
};
```

- [ ] **Step 2: Add status badge primitive**

Create `apps/web/components/workflow/status-badge.tsx`:

```tsx
import type { WorkflowStateConfig } from "./workflow-state";
import { workflowToneClassNames } from "./workflow-state";

export function StatusBadge<TStatus extends string>({
  status,
  config,
  size = "default",
}: {
  status: TStatus;
  config: WorkflowStateConfig<TStatus>;
  size?: "default" | "compact";
}) {
  const state = config[status];
  const Icon = state.icon;
  const sizeClassName =
    size === "compact"
      ? "min-h-6 gap-1 px-2 text-[0.75rem]"
      : "min-h-7 gap-1.5 px-2.5 text-xs";

  return (
    <span
      className={`inline-flex items-center rounded-md border font-medium ${sizeClassName} ${
        workflowToneClassNames[state.tone]
      }`}
    >
      <Icon className={size === "compact" ? "h-3 w-3" : "h-3.5 w-3.5"} aria-hidden="true" />
      <span>{state.label}</span>
      <span className="sr-only">{state.description}</span>
    </span>
  );
}
```

- [ ] **Step 3: Add activity timeline primitive**

Create `apps/web/components/workflow/activity-timeline.tsx`:

```tsx
import { FileClock } from "lucide-react";
import type { LucideIcon } from "lucide-react";

export type ActivityTimelineActor = {
  id?: string;
  name?: string | null;
  email?: string | null;
};

export type ActivityTimelineEvent = {
  id: string;
  action: string;
  message: string;
  occurredAt: string;
  actor?: ActivityTimelineActor | null;
  targetDisplay?: string | null;
  metadata?: Record<string, unknown> | null;
};

export type ActivityTimelineActionIcons = Partial<Record<string, LucideIcon>> & {
  default?: LucideIcon;
};

export function ActivityTimeline({
  events,
  emptyMessage = "No activity has been recorded yet.",
  actionIcons = {},
}: {
  events: ActivityTimelineEvent[];
  emptyMessage?: string;
  actionIcons?: ActivityTimelineActionIcons;
}) {
  if (events.length === 0) {
    return <p className="text-sm text-muted-foreground">{emptyMessage}</p>;
  }

  return (
    <ol className="space-y-3">
      {events.map((event) => {
        const Icon = actionIcons[event.action] ?? actionIcons.default ?? FileClock;
        const actorName = event.actor?.name ?? "System";
        const formattedTime = new Date(event.occurredAt).toLocaleString();

        return (
          <li key={event.id} className="flex gap-3 rounded-md border p-3">
            <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md border bg-card">
              <Icon className="h-4 w-4" aria-hidden="true" />
            </span>
            <div className="min-w-0">
              <p className="text-sm font-medium">{event.message}</p>
              <p className="text-sm text-muted-foreground">
                {actorName} · {formattedTime}
              </p>
              {event.targetDisplay ? (
                <p className="mt-1 text-xs text-muted-foreground">{event.targetDisplay}</p>
              ) : null}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
```

- [ ] **Step 4: Migrate requisition status wrapper**

Replace `apps/web/features/requisitions/components/requisition-status-badge.tsx` with:

```tsx
import { CheckCircle2, CircleDot, Clock3 } from "lucide-react";
import { StatusBadge } from "@/components/workflow/status-badge";
import type { WorkflowStateConfig } from "@/components/workflow/workflow-state";
import type { RequisitionStatus } from "../types/requisition-view-model";

const requisitionStatusConfig = {
  draft: {
    label: "Draft",
    description: "The requester can still edit and submit this requisition.",
    tone: "draft",
    icon: CircleDot,
  },
  submitted: {
    label: "Submitted",
    description: "The requisition has been submitted for procurement review.",
    tone: "success",
    icon: CheckCircle2,
  },
  pending_approval: {
    label: "Pending approval",
    description: "The requisition is waiting for an approval decision.",
    tone: "info",
    icon: Clock3,
  },
} satisfies WorkflowStateConfig<RequisitionStatus>;

export function RequisitionStatusBadge({
  status,
  size,
}: {
  status: RequisitionStatus;
  size?: "default" | "compact";
}) {
  return <StatusBadge status={status} config={requisitionStatusConfig} size={size} />;
}
```

- [ ] **Step 5: Migrate requisition timeline wrapper**

Replace `apps/web/features/requisitions/components/requisition-activity-timeline.tsx` with:

```tsx
import { CheckCircle2, FileClock, Send } from "lucide-react";
import type { AuditEvent } from "@cognify/api-client/schemas";
import { ActivityTimeline } from "@/components/workflow/activity-timeline";

export function RequisitionActivityTimeline({ events }: { events: AuditEvent[] }) {
  return (
    <ActivityTimeline
      events={events}
      actionIcons={{
        "requisition.submitted": Send,
        "requisition.updated": CheckCircle2,
        default: FileClock,
      }}
    />
  );
}
```

- [ ] **Step 6: Verify workflow primitive tests pass**

Run:

```bash
pnpm --filter @cognify/web test -- components/workflow/workflow-primitives.test.tsx features/requisitions/tests/requisitions-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 7: Commit checkpoint**

Run during implementation only:

```bash
git add apps/web/components/workflow apps/web/features/requisitions/components/requisition-status-badge.tsx apps/web/features/requisitions/components/requisition-activity-timeline.tsx
git commit -m "feat(web): add workflow status and timeline primitives"
```

Expected: commit created with workflow primitive and requisition adapter changes.

## Task 4: Implement Form and Validation Primitives

**Files:**

- Create: `apps/web/components/forms/form-field.tsx`
- Create: `apps/web/components/forms/form-error-summary.tsx`
- Create: `apps/web/components/forms/validation-errors.ts`
- Modify: `apps/web/features/requisitions/forms/requisition-form.tsx`

- [ ] **Step 1: Add form field primitive**

Create `apps/web/components/forms/form-field.tsx`:

```tsx
import { cloneElement } from "react";

export function FormField({
  htmlFor,
  label,
  description,
  error,
  required = false,
  children,
}: {
  htmlFor: string;
  label: string;
  description?: string;
  error?: string;
  required?: boolean;
  children: React.ReactElement<{ id?: string; "aria-describedby"?: string; "aria-invalid"?: boolean }>;
}) {
  const descriptionId = description ? `${htmlFor}-description` : undefined;
  const errorId = error ? `${htmlFor}-error` : undefined;
  const describedBy = [descriptionId, errorId].filter(Boolean).join(" ") || undefined;

  return (
    <div className="space-y-1.5">
      <label htmlFor={htmlFor} className="block text-sm font-medium">
        {label}
        {required ? <span className="ml-2 text-xs text-muted-foreground">Required</span> : null}
      </label>
      {description ? (
        <p id={descriptionId} className="text-sm text-muted-foreground">
          {description}
        </p>
      ) : null}
      {cloneElement(children, {
        id: children.props.id ?? htmlFor,
        "aria-describedby": describedBy,
        "aria-invalid": Boolean(error) || children.props["aria-invalid"],
      })}
      {error ? (
        <p id={errorId} className="text-sm text-red-700">
          {error}
        </p>
      ) : null}
    </div>
  );
}
```

- [ ] **Step 2: Add error summary primitive**

Create `apps/web/components/forms/form-error-summary.tsx`:

```tsx
export type FormSummaryError = {
  field?: string;
  fieldId?: string;
  message: string;
};

export function FormErrorSummary({
  title,
  errors,
}: {
  title: string;
  errors: FormSummaryError[];
}) {
  if (errors.length === 0) return null;

  return (
    <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
      <p className="font-medium">{title}</p>
      <ul className="mt-2 list-disc space-y-1 pl-5">
        {errors.map((error, index) => (
          <li key={`${error.field ?? "form"}-${index}`}>
            {error.fieldId ? (
              <a className="underline" href={`#${error.fieldId}`}>
                {error.message}
              </a>
            ) : (
              error.message
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
```

- [ ] **Step 3: Add validation helpers**

Create `apps/web/components/forms/validation-errors.ts`:

```tsx
import type { FormSummaryError } from "./form-error-summary";

type FieldErrors = Record<string, string[] | undefined>;

export function flattenZodFieldErrors(fieldErrors: FieldErrors): FormSummaryError[] {
  return Object.entries(fieldErrors).flatMap(([field, messages]) =>
    (messages ?? []).map((message) => ({ field, message })),
  );
}

export function normalizeValidationErrors(error: unknown): FormSummaryError[] {
  if (!error || typeof error !== "object") return [];

  const maybeError = error as {
    errors?: FieldErrors;
    details?: {
      fields?: FieldErrors;
    };
    response?: {
      data?: {
        error?: {
          details?: {
            fields?: FieldErrors;
          };
        };
      };
    };
  };

  const fields =
    maybeError.details?.fields ??
    maybeError.errors ??
    maybeError.response?.data?.error?.details?.fields ??
    {};

  return flattenZodFieldErrors(fields);
}

export function focusFirstInvalidField(root: ParentNode = document) {
  const firstInvalid = root.querySelector<HTMLElement>("[aria-invalid='true']");
  firstInvalid?.focus();
}

export function withFieldIds(
  errors: FormSummaryError[],
  fieldIds: Record<string, string>,
): FormSummaryError[] {
  return errors.map((error) => ({
    ...error,
    fieldId: error.field ? fieldIds[error.field] : undefined,
  }));
}
```

- [ ] **Step 4: Migrate requisition form imports and field IDs**

Modify `apps/web/features/requisitions/forms/requisition-form.tsx` imports:

```tsx
import { Plus, Trash2 } from "lucide-react";
import { useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { FormErrorSummary } from "@/components/forms/form-error-summary";
import { FormField } from "@/components/forms/form-field";
import {
  flattenZodFieldErrors,
  focusFirstInvalidField,
  withFieldIds,
} from "@/components/forms/validation-errors";
import { SubmitRequisitionDialog } from "../components/submit-requisition-dialog";
import { SubmissionChecklist } from "../components/submission-checklist";
import { useSaveRequisitionDraft } from "../hooks/use-save-requisition-draft";
import { useSubmitRequisition } from "../hooks/use-submit-requisition";
import { requisitionSubmitSchema } from "../schemas/requisition-form-schema";
import type { Requisition, RequisitionFormValues } from "../types/requisition-view-model";
```

Add this constant below `emptyLineItem`:

```tsx
const requisitionFieldIds: Record<string, string> = {
  title: "title",
  businessJustification: "business-justification",
  neededByDate: "needed-by",
  currency: "currency",
  lineItems: "line-items",
};
```

- [ ] **Step 5: Replace local error summary logic**

In `RequisitionForm`, replace:

```tsx
const errorSummary = useMemo(() => Object.values(errors).flat(), [errors]);
```

with:

```tsx
const errorSummary = useMemo(
  () => withFieldIds(flattenZodFieldErrors(errors), requisitionFieldIds),
  [errors],
);
```

Replace the submit validation failure block:

```tsx
setErrors(result.error.flatten().fieldErrors);
focusFirstInvalidField();
return;
```

with:

```tsx
setErrors(result.error.flatten().fieldErrors);
window.setTimeout(() => focusFirstInvalidField(formRef.current ?? document), 0);
return;
```

Replace the local `focusFirstInvalidField` function with no local function. Use the imported helper in `handleSaveDraft`:

```tsx
window.setTimeout(() => focusFirstInvalidField(formRef.current ?? document), 0);
```

- [ ] **Step 6: Replace ad hoc alert with FormErrorSummary**

Replace the alert block with:

```tsx
<FormErrorSummary
  title="Complete the highlighted fields before submitting."
  errors={errorSummary}
/>
```

- [ ] **Step 7: Replace local Field usages**

For each form control, replace `Field` with `FormField`.

Example for title:

```tsx
<FormField htmlFor="title" label="Title" error={errors.title?.[0]} required>
  <input
    id="title"
    className="min-h-11 w-full rounded-md border px-3 text-base"
    value={values.title}
    aria-invalid={Boolean(errors.title)}
    onChange={(event) => updateValue("title", event.target.value)}
  />
</FormField>
```

Example for business justification:

```tsx
<FormField
  htmlFor="business-justification"
  label="Business justification"
  error={errors.businessJustification?.[0]}
  required
>
  <textarea
    id="business-justification"
    className="min-h-28 w-full rounded-md border px-3 py-2 text-base"
    value={values.businessJustification}
    aria-invalid={Boolean(errors.businessJustification)}
    onChange={(event) => updateValue("businessJustification", event.target.value)}
  />
</FormField>
```

Example for line item error anchor:

```tsx
<section id="line-items" className="space-y-3 rounded-md border p-4">
```

After all usages are migrated, remove the local `Field` function from the bottom of the file.

- [ ] **Step 8: Verify form tests and requisition workflow pass**

Run:

```bash
pnpm --filter @cognify/web test -- components/forms/forms.test.tsx features/requisitions/tests/requisitions-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 9: Commit checkpoint**

Run during implementation only:

```bash
git add apps/web/components/forms apps/web/features/requisitions/forms/requisition-form.tsx
git commit -m "feat(web): add workflow form validation primitives"
```

Expected: commit created with form primitive and requisition form changes.

## Task 5: Implement Data Table Primitive

**Files:**

- Create: `apps/web/components/data-table/data-table-types.ts`
- Create: `apps/web/components/data-table/data-table-empty-state.tsx`
- Create: `apps/web/components/data-table/use-data-table-state.ts`
- Create: `apps/web/components/data-table/data-table.tsx`
- Modify: `apps/web/features/requisitions/tables/requisitions-table.tsx`
- Modify: `apps/web/features/requisitions/workflows/requisition-list-page.tsx`

- [ ] **Step 1: Add data table types**

Create `apps/web/components/data-table/data-table-types.ts`:

```tsx
import type { ReactNode } from "react";

export type DataTableSortDirection = "asc" | "desc";

export type DataTableSort = {
  columnId: string;
  direction: DataTableSortDirection;
};

export type DataTableColumn<TRow> = {
  id: string;
  header: string;
  cell: (row: TRow) => ReactNode;
  sortable?: boolean;
  align?: "left" | "right" | "center";
  widthClassName?: string;
  hideOnMobile?: boolean;
};

export type DataTableState = "idle" | "loading" | "error" | "empty";

export type DataTablePagination = {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
};
```

- [ ] **Step 2: Add empty/loading/error support**

Create `apps/web/components/data-table/data-table-empty-state.tsx`:

```tsx
export function DataTableLoading({ label = "Loading rows" }: { label?: string }) {
  return (
    <div className="space-y-2 rounded-md border p-3" aria-label={label}>
      {Array.from({ length: 5 }).map((_, index) => (
        <div key={index} className="h-12 rounded-md bg-card" />
      ))}
    </div>
  );
}

export function DataTableError({
  title,
  onRetry,
}: {
  title: string;
  onRetry?: () => void;
}) {
  return (
    <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
      <p className="font-medium">{title}</p>
      {onRetry ? (
        <button type="button" className="mt-3 min-h-11 rounded-md border bg-white px-3" onClick={onRetry}>
          Retry
        </button>
      ) : null}
    </div>
  );
}

export function DataTableEmpty({
  title,
  description,
}: {
  title: string;
  description?: string;
}) {
  return (
    <div className="rounded-md border p-6">
      <h2 className="text-base font-semibold">{title}</h2>
      {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
    </div>
  );
}
```

- [ ] **Step 3: Add data table state hook**

Create `apps/web/components/data-table/use-data-table-state.ts`:

```tsx
"use client";

import { useState } from "react";
import type { DataTableSort } from "./data-table-types";

export function useDataTableState({ initialSort }: { initialSort?: DataTableSort } = {}) {
  const [sort, setSort] = useState<DataTableSort | undefined>(initialSort);

  return {
    sort,
    setSort,
  };
}
```

- [ ] **Step 4: Add data table component**

Create `apps/web/components/data-table/data-table.tsx`:

```tsx
"use client";

import { ArrowDown, ArrowUp, ChevronsUpDown } from "lucide-react";
import type { ReactNode } from "react";
import {
  DataTableEmpty,
  DataTableError,
  DataTableLoading,
} from "./data-table-empty-state";
import type {
  DataTableColumn,
  DataTablePagination,
  DataTableSort,
  DataTableState,
} from "./data-table-types";

export function DataTable<TRow>({
  caption,
  rows,
  columns,
  getRowId,
  state = "idle",
  loadingLabel,
  errorTitle,
  emptyTitle,
  emptyDescription,
  onRetry,
  sort,
  onSortChange,
  pagination,
  renderRowActions,
  renderMobileRow,
}: {
  caption: string;
  rows: TRow[];
  columns: DataTableColumn<TRow>[];
  getRowId: (row: TRow) => string;
  state?: DataTableState;
  loadingLabel?: string;
  errorTitle?: string;
  emptyTitle?: string;
  emptyDescription?: string;
  onRetry?: () => void;
  sort?: DataTableSort;
  onSortChange?: (sort: DataTableSort) => void;
  pagination?: DataTablePagination;
  renderRowActions?: (row: TRow) => ReactNode;
  renderMobileRow: (row: TRow) => ReactNode;
}) {
  if (state === "loading") return <DataTableLoading label={loadingLabel} />;
  if (state === "error") return <DataTableError title={errorTitle ?? "Rows could not be loaded."} onRetry={onRetry} />;
  if (state === "empty") {
    return <DataTableEmpty title={emptyTitle ?? "No rows found"} description={emptyDescription} />;
  }

  return (
    <div className="space-y-3">
      <div className="hidden overflow-hidden rounded-md border md:block">
        <table className="w-full table-fixed text-left text-sm">
          <caption className="sr-only">{caption}</caption>
          <thead className="border-b bg-card text-xs uppercase text-muted-foreground">
            <tr>
              {columns.map((column) => {
                const activeSort = sort?.columnId === column.id ? sort.direction : undefined;
                const ariaSort =
                  activeSort === "asc" ? "ascending" : activeSort === "desc" ? "descending" : "none";

                return (
                  <th
                    key={column.id}
                    scope="col"
                    aria-sort={column.sortable ? ariaSort : undefined}
                    className={`${column.widthClassName ?? ""} px-3 py-3 ${alignClassName(column.align)}`}
                  >
                    {column.sortable && onSortChange ? (
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 font-medium uppercase"
                        onClick={() => onSortChange(nextSort(column.id, sort))}
                        aria-label={`Sort by ${column.header} ${
                          activeSort === "asc" ? "descending" : "ascending"
                        }`}
                      >
                        {column.header}
                        {activeSort === "asc" ? (
                          <ArrowUp className="h-3.5 w-3.5" aria-hidden="true" />
                        ) : activeSort === "desc" ? (
                          <ArrowDown className="h-3.5 w-3.5" aria-hidden="true" />
                        ) : (
                          <ChevronsUpDown className="h-3.5 w-3.5" aria-hidden="true" />
                        )}
                      </button>
                    ) : (
                      column.header
                    )}
                  </th>
                );
              })}
              {renderRowActions ? <th scope="col" className="w-32 px-3 py-3">Actions</th> : null}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={getRowId(row)} className="border-b last:border-b-0">
                {columns.map((column) => (
                  <td key={column.id} className={`px-3 py-4 ${alignClassName(column.align)}`}>
                    {column.cell(row)}
                  </td>
                ))}
                {renderRowActions ? <td className="px-3 py-4">{renderRowActions(row)}</td> : null}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="space-y-3 md:hidden">{rows.map((row) => <div key={getRowId(row)}>{renderMobileRow(row)}</div>)}</div>

      {pagination ? (
        <p className="text-sm text-muted-foreground">
          Showing {rows.length} of {pagination.total} records
        </p>
      ) : null}
    </div>
  );
}

function alignClassName(align: DataTableColumn<unknown>["align"]) {
  if (align === "right") return "text-right";
  if (align === "center") return "text-center";
  return "text-left";
}

function nextSort(columnId: string, current?: DataTableSort): DataTableSort {
  if (current?.columnId === columnId && current.direction === "asc") {
    return { columnId, direction: "desc" };
  }

  return { columnId, direction: "asc" };
}
```

- [ ] **Step 5: Migrate requisitions table**

Replace `apps/web/features/requisitions/tables/requisitions-table.tsx` with:

```tsx
"use client";

import Link from "next/link";
import { ExternalLink, PanelRightOpen } from "lucide-react";
import { DataTable } from "@/components/data-table/data-table";
import type { DataTableColumn, DataTablePagination, DataTableSort } from "@/components/data-table/data-table-types";
import { useRightPanel } from "@/components/right-panel/right-panel-provider";
import { RequisitionStatusBadge } from "../components/requisition-status-badge";
import type { Requisition } from "../types/requisition-view-model";
import { formatMoney } from "../utils/requisition-totals";

const requisitionColumns: DataTableColumn<Requisition>[] = [
  {
    id: "number",
    header: "Number",
    widthClassName: "w-36",
    cell: (requisition) => <span className="font-mono text-xs tabular-nums">{requisition.number}</span>,
  },
  {
    id: "title",
    header: "Title",
    sortable: true,
    cell: (requisition) => <span className="font-medium">{requisition.title}</span>,
  },
  {
    id: "status",
    header: "Status",
    widthClassName: "w-36",
    cell: (requisition) => <RequisitionStatusBadge status={requisition.status} />,
  },
  {
    id: "requester",
    header: "Requester",
    widthClassName: "w-36",
    cell: (requisition) => <span className="text-muted-foreground">{requisition.requester.name}</span>,
  },
  {
    id: "neededByDate",
    header: "Needed by",
    widthClassName: "w-32",
    cell: (requisition) => <span className="tabular-nums">{requisition.neededByDate}</span>,
  },
  {
    id: "estimatedTotal",
    header: "Estimated total",
    widthClassName: "w-36",
    align: "right",
    cell: (requisition) => (
      <span className="font-mono tabular-nums">
        {formatMoney(requisition.estimatedTotal, requisition.currency)}
      </span>
    ),
  },
];

export function RequisitionsTable({
  requisitions,
  state = "idle",
  filtered = false,
  pagination,
  onRetry,
  sort,
  onSortChange,
}: {
  requisitions: Requisition[];
  state?: "idle" | "loading" | "error" | "empty";
  filtered?: boolean;
  pagination?: DataTablePagination;
  onRetry?: () => void;
  sort?: DataTableSort;
  onSortChange?: (sort: DataTableSort) => void;
}) {
  const rightPanel = useRightPanel();

  return (
    <DataTable
      caption="Requisitions"
      rows={requisitions}
      columns={requisitionColumns}
      getRowId={(requisition) => requisition.id}
      state={state}
      loadingLabel="Loading requisitions"
      errorTitle="Requisitions could not be loaded."
      emptyTitle={filtered ? "No requisitions match these filters" : "No requisitions yet"}
      emptyDescription={
        filtered
          ? "Clear filters to see the full work queue."
          : "Create the first draft requisition for this tenant."
      }
      onRetry={onRetry}
      sort={sort}
      onSortChange={onSortChange}
      pagination={pagination}
      renderRowActions={(requisition) => (
        <div className="flex items-center gap-2">
          <button
            type="button"
            className="inline-flex min-h-11 items-center justify-center rounded-md border px-3"
            onClick={() => rightPanel.openPanel(requisitionPanel(requisition))}
            aria-label={`Open details panel for ${requisition.number}`}
          >
            <PanelRightOpen className="h-4 w-4" aria-hidden="true" />
          </button>
          <Link
            href={`/requisitions/${requisition.id}`}
            className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3"
          >
            Open
            <ExternalLink className="h-4 w-4" aria-hidden="true" />
          </Link>
        </div>
      )}
      renderMobileRow={(requisition) => (
        <Link href={`/requisitions/${requisition.id}`} className="block rounded-md border p-4">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="font-medium">{requisition.title}</p>
              <p className="mt-1 font-mono text-xs text-muted-foreground">{requisition.number}</p>
            </div>
            <RequisitionStatusBadge status={requisition.status} size="compact" />
          </div>
          <div className="mt-3 flex items-center justify-between text-sm">
            <span>Needed {requisition.neededByDate}</span>
            <span className="font-mono tabular-nums">
              {formatMoney(requisition.estimatedTotal, requisition.currency)}
            </span>
          </div>
        </Link>
      )}
    />
  );
}

function requisitionPanel(requisition: Requisition) {
  return {
    id: `requisition-${requisition.id}`,
    title: requisition.title,
    description: requisition.number,
    size: "md" as const,
    content: (
      <div className="space-y-4 text-sm">
        <div className="flex items-center justify-between gap-3">
          <span className="text-muted-foreground">Status</span>
          <RequisitionStatusBadge status={requisition.status} />
        </div>
        <dl className="grid gap-3">
          <div>
            <dt className="text-muted-foreground">Requester</dt>
            <dd className="font-medium">{requisition.requester.name}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Needed by</dt>
            <dd className="font-medium tabular-nums">{requisition.neededByDate}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Estimated total</dt>
            <dd className="font-mono font-medium tabular-nums">
              {formatMoney(requisition.estimatedTotal, requisition.currency)}
            </dd>
          </div>
        </dl>
      </div>
    ),
    footer: (
      <div className="flex flex-col gap-2 sm:flex-row">
        <Link
          href={`/requisitions/${requisition.id}`}
          className="inline-flex min-h-11 items-center justify-center rounded-md bg-foreground px-3 text-sm font-medium text-background"
        >
          Open workspace
        </Link>
        {requisition.permissions.canUpdate ? (
          <Link
            href={`/requisitions/${requisition.id}/edit`}
            className="inline-flex min-h-11 items-center justify-center rounded-md border px-3 text-sm font-medium"
          >
            Edit draft
          </Link>
        ) : null}
      </div>
    ),
  };
}
```

- [ ] **Step 6: Migrate requisition list page state rendering**

Modify `apps/web/features/requisitions/workflows/requisition-list-page.tsx`:

```tsx
"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useDataTableState } from "@/components/data-table/use-data-table-state";
import { useRequisitions } from "../hooks/use-requisitions";
import { RequisitionsTable } from "../tables/requisitions-table";

export function RequisitionListPage() {
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);
  const [status, setStatus] = useState("");
  const tableState = useDataTableState({ initialSort: { columnId: "title", direction: "asc" } });
  const query = useMemo(() => ({ search: debouncedSearch, status }), [debouncedSearch, status]);
  const { data, isLoading, isError, refetch } = useRequisitions(query);
  const requisitions = data?.data ?? [];
  const filtered = Boolean(search || status);
  const renderedState = isLoading ? "loading" : isError ? "error" : requisitions.length === 0 ? "empty" : "idle";

  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Requisitions</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Find drafts, submitted requests, and sourcing handoffs.
          </p>
        </div>
        <Link
          href="/requisitions/new"
          className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-foreground px-4 text-sm font-medium text-background"
        >
          <Plus className="h-4 w-4" aria-hidden="true" />
          New requisition
        </Link>
      </div>

      <div className="grid gap-3 rounded-md border p-3 md:grid-cols-[minmax(0,1fr)_12rem_8rem]">
        <label className="space-y-1.5 text-sm font-medium">
          Search
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Status
          <select
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            value={status}
            onChange={(event) => setStatus(event.target.value)}
          >
            <option value="">All</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
          </select>
        </label>
        <button
          type="button"
          className="min-h-11 self-end rounded-md border px-3 text-sm font-medium"
          onClick={() => {
            setSearch("");
            setStatus("");
          }}
        >
          Clear
        </button>
      </div>

      <RequisitionsTable
        requisitions={requisitions}
        state={renderedState}
        filtered={filtered}
        pagination={data?.meta}
        onRetry={() => refetch()}
        sort={tableState.sort}
        onSortChange={tableState.setSort}
      />
    </section>
  );
}

function useDebouncedValue<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedValue(value), delay);

    return () => window.clearTimeout(timeout);
  }, [delay, value]);

  return debouncedValue;
}
```

- [ ] **Step 7: Verify table tests pass**

Run:

```bash
pnpm --filter @cognify/web test -- components/data-table/data-table.test.tsx
```

Expected: PASS.

- [ ] **Step 8: Commit checkpoint**

Run during implementation only:

```bash
git add apps/web/components/data-table apps/web/features/requisitions/tables/requisitions-table.tsx apps/web/features/requisitions/workflows/requisition-list-page.tsx
git commit -m "feat(web): add operational data table primitive"
```

Expected: commit created with data table primitive and requisition list migration.

## Task 6: Add Requisition Regression Coverage for Integrated Primitives

**Files:**

- Modify: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`

- [ ] **Step 1: Ensure test render includes app providers for right panel**

In `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`, add:

```tsx
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
```

Replace `renderWithQuery` with:

```tsx
function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <RightPanelProvider>
        {ui}
        <RightPanelRoot />
      </RightPanelProvider>
    </QueryClientProvider>,
  );
}
```

- [ ] **Step 2: Add quick panel regression test**

Append this test inside `describe("requisitions workflow", () => { ... })`:

```tsx
it("opens a requisition details panel from the work queue", async () => {
  const user = userEvent.setup();

  renderWithQuery(<RequisitionListPage />);

  expect(await screen.findByRole("heading", { name: "Requisitions" })).toBeInTheDocument();
  await user.click(
    screen.getByRole("button", { name: "Open details panel for REQ-2026-000001" }),
  );

  expect(
    screen.getByRole("dialog", { name: "Field laptop refresh" }),
  ).toBeInTheDocument();
  expect(screen.getByText("REQ-2026-000001")).toBeInTheDocument();
  expect(screen.getByText("Requester")).toBeInTheDocument();
  expect(screen.getByRole("link", { name: "Open workspace" })).toHaveAttribute(
    "href",
    "/requisitions/req-1",
  );
});
```

- [ ] **Step 3: Add form summary regression expectation**

In the existing invalid submit test, after the alert assertion, add:

```tsx
expect(
  screen.getByRole("link", { name: "Business justification is required before submission." }),
).toHaveAttribute("href", "#business-justification");
```

- [ ] **Step 4: Run requisition workflow tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/requisitions/tests/requisitions-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit checkpoint**

Run during implementation only:

```bash
git add apps/web/features/requisitions/tests/requisitions-workflow.test.tsx
git commit -m "test(web): cover workflow primitive integration"
```

Expected: commit created with requisition regression tests.

## Task 7: Accessibility and Type Hardening

**Files:**

- Modify as needed after checks:
  - `apps/web/components/data-table/data-table.tsx`
  - `apps/web/components/forms/form-field.tsx`
  - `apps/web/components/right-panel/right-panel-root.tsx`
  - `apps/web/features/requisitions/forms/requisition-form.tsx`
  - `apps/web/features/requisitions/tables/requisitions-table.tsx`

- [ ] **Step 1: Run typecheck**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: PASS.

If typecheck fails on React `cloneElement` props in `FormField`, update `FormField` children typing to:

```tsx
children: React.ReactElement<
  React.InputHTMLAttributes<HTMLInputElement> &
    React.TextareaHTMLAttributes<HTMLTextAreaElement> &
    React.SelectHTMLAttributes<HTMLSelectElement>
>;
```

If typecheck fails on `alignClassName` generic inference in `DataTable`, replace its signature with:

```tsx
function alignClassName(align?: "left" | "right" | "center") {
  if (align === "right") return "text-right";
  if (align === "center") return "text-center";
  return "text-left";
}
```

- [ ] **Step 2: Run lint**

Run:

```bash
pnpm --filter @cognify/web lint
```

Expected: PASS.

If lint flags line length or JSX wrapping, format the affected JSX without changing behavior.

- [ ] **Step 3: Run targeted primitive and requisition tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/right-panel/right-panel.test.tsx components/workflow/workflow-primitives.test.tsx components/forms/forms.test.tsx components/data-table/data-table.test.tsx features/requisitions/tests/requisitions-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 4: Commit checkpoint**

Run during implementation only if hardening edits were needed:

```bash
git add apps/web/components apps/web/features/requisitions
git commit -m "fix(web): harden workflow primitive accessibility"
```

Expected: commit created only when Step 1 or Step 2 required source edits.

## Task 8: Final Verification

**Files:**

- Read: `docs/superpowers/specs/2026-05-13-workflow-ui-primitives-design.md`
- Read: `docs/superpowers/plans/2026-05-13-workflow-ui-primitives.md`
- Verify: all modified `apps/web` files.

- [ ] **Step 1: Run full web tests**

Run:

```bash
pnpm --filter @cognify/web test
```

Expected: PASS.

- [ ] **Step 2: Run web typecheck**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: PASS.

- [ ] **Step 3: Run web lint**

Run:

```bash
pnpm --filter @cognify/web lint
```

Expected: PASS.

- [ ] **Step 4: Confirm no backend/API contract changes**

Run:

```bash
git diff --name-only
```

Expected changed paths are limited to:

```txt
apps/web/components/data-table/*
apps/web/components/forms/*
apps/web/components/right-panel/*
apps/web/components/workflow/*
apps/web/components/providers/app-providers.tsx
apps/web/components/shell/right-panel-host.tsx
apps/web/features/requisitions/components/requisition-status-badge.tsx
apps/web/features/requisitions/components/requisition-activity-timeline.tsx
apps/web/features/requisitions/forms/requisition-form.tsx
apps/web/features/requisitions/tables/requisitions-table.tsx
apps/web/features/requisitions/workflows/requisition-list-page.tsx
apps/web/features/requisitions/tests/requisitions-workflow.test.tsx
```

Also acceptable in the current review handoff:

```txt
docs/superpowers/specs/2026-05-13-workflow-ui-primitives-design.md
docs/superpowers/plans/2026-05-13-workflow-ui-primitives.md
```

- [ ] **Step 5: Final implementation commit**

Run during implementation only:

```bash
git status --short
```

Expected: no uncommitted app changes unless the user asked to keep them staged or unstaged.

## Self-Review Checklist

- Spec coverage: right panel, data table, form validation, status badge/workflow state, and activity timeline are each covered by tasks and tests.
- Repo boundary: all source work stays in `apps/web`; no shared package or API changes are planned.
- TDD: each primitive has a failing test step before implementation.
- Requisition proof: current requisition list, form, badge, and timeline are migrated.
- Accessibility: labels, dialog semantics, table captions, error summaries, status descriptions, and list semantics are explicit.
- Verification: web tests, typecheck, and lint are required before completion.
