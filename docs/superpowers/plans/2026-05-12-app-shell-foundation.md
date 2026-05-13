# App Shell Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Cognify's authenticated operational app shell and reusable record workspace layout so P0 and P1 procurement workflows share one tenant-aware, accessible frontend workspace.

**Architecture:** Keep all shell and workspace composition inside `apps/web`, because it contains Cognify product navigation, tenant context, and procurement workflow conventions. Consolidate the current dashboard and workspace shell scaffolds into one client-side `AppShell`, then add a slot-based `RecordWorkspaceLayout` that feature workflows can compose without moving business meaning into `packages/ui`.

**Tech Stack:** Next.js App Router, React 19, TypeScript, Tailwind CSS v4, TanStack Query, MSW, Vitest, Testing Library, lucide-react.

---

## Runbook Alignment

Follow `docs/05-runbooks/feature-development.md`:

1. Start from the workflow and route map.
2. Write shell and record-layout regression tests before editing production components.
3. Keep Cognify-specific shell code in `apps/web`.
4. Use existing identity hooks and MSW handlers instead of importing fixtures into production components.
5. Avoid API and OpenAPI changes unless implementation discovers a real contract gap.
6. Run narrow web tests first, then typecheck and lint.

## File Structure

Create:

- `apps/web/components/shell/app-shell.tsx`: authenticated operational shell composition.
- `apps/web/components/shell/app-shell.test.tsx`: shell rendering, route state, permissions, and mobile behavior tests.
- `apps/web/components/shell/breadcrumbs.tsx`: breadcrumb landmark and links.
- `apps/web/components/shell/mobile-shell-nav.tsx`: mobile menu button and overlay navigation.
- `apps/web/components/shell/notification-host.tsx`: inert notification host placement.
- `apps/web/components/shell/shell-footer.tsx`: compact local status/footer strip.
- `apps/web/components/shell/shell-header.tsx`: tenant, user, role, breadcrumbs, and extension hosts.
- `apps/web/components/shell/shell-nav.tsx`: desktop navigation and shared nav list renderer.
- `apps/web/components/shell/shell-route-config.ts`: route metadata, nav groups, breadcrumb resolver.
- `apps/web/components/shell/shell-types.ts`: shell-specific TypeScript contracts.
- `apps/web/components/shell/shell-utils.ts`: permission, active route, and display helpers.
- `apps/web/components/workspace/record-workspace-layout.tsx`: reusable record detail layout.
- `apps/web/components/workspace/record-workspace-layout.test.tsx`: record layout contract tests.

Modify:

- `apps/web/app/(dashboard)/layout.tsx`: protect dashboard routes with `SessionGate` and render `AppShell`.
- `apps/web/app/(workspace)/layout.tsx`: render `AppShell` instead of the old workspace shell.
- `apps/web/components/shell/command-palette-host.tsx`: refine host styling and labels without adding command behavior.
- `apps/web/components/shell/right-panel-host.tsx`: keep an inert host boundary.
- `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`: compose `RecordWorkspaceLayout`.
- `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`: add detail-workspace regression coverage.

Delete after migration:

- `apps/web/components/shell/dashboard-shell.tsx`
- `apps/web/components/shell/workspace-shell.tsx`

Do not modify:

- `packages/ui`
- `packages/api-client`
- `apps/api`
- `apps/api/storage/openapi/openapi.json`

## Workflow Map

```txt
Actors:
  authenticated Cognify user, requester, buyer, approver, admin

Shell entry:
  operational route -> SessionGate -> AppShell -> feature page

Tenant context:
  useCurrentUser() returns active tenant, active role, user, and permissions
  AppShell displays but does not authorize tenant context
  API calls still enforce tenant authorization server-side

Route state:
  usePathname() -> nav active state and route breadcrumbs
  shell-route-config owns top-level route names and disabled future destinations

Record workspace:
  feature workflow loads record data
  feature workflow handles loading and errors
  RecordWorkspaceLayout renders stable detail-page chrome and slots

Failure paths:
  unauthenticated -> SessionGate sign-in required state
  missing multi-tenant selection -> SessionGate tenant selection state
  transient identity failure -> SessionGate workspace unavailable state
  disabled future destination -> visible disabled nav text, no broken link
```

## Task 1: Baseline And Shell Utility Tests

**Files:**

- Read: `docs/superpowers/specs/2026-05-12-app-shell-foundation-design.md`
- Read: `docs/05-runbooks/feature-development.md`
- Read: `apps/web/components/shell/workspace-shell.tsx`
- Read: `apps/web/components/shell/dashboard-shell.tsx`
- Create: `apps/web/components/shell/app-shell.test.tsx`

- [ ] **Step 1: Confirm baseline**

Run:

```bash
git status --short --branch
```

Expected: working tree may include this design and plan if they have not been committed. Do not revert unrelated files.

- [ ] **Step 2: Add failing shell tests**

Create `apps/web/components/shell/app-shell.test.tsx` with this initial test suite:

```tsx
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "../../tests/msw/server";
import { requesterIdentity } from "../../features/identity/mocks/identity-fixtures";
import type { CurrentUserContext } from "../../features/identity/types/identity-view-model";
import { AppShell } from "./app-shell";
import { getBreadcrumbs, shellNavGroups } from "./shell-route-config";
import { getVisibleNavGroups, isActivePath } from "./shell-utils";

let mockPathname = "/dashboard";

vi.mock("next/navigation", () => ({
  usePathname: () => mockPathname,
}));

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

function mockIdentity(identity: CurrentUserContext) {
  server.use(
    http.get("/api/me", () => {
      return HttpResponse.json({ data: identity });
    }),
  );
}

describe("app shell", () => {
  beforeEach(() => {
    mockPathname = "/dashboard";
  });

  it("renders tenant, user, role, landmarks, breadcrumbs, and shell hosts", async () => {
    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    expect(await screen.findAllByText("Acme Procurement")).not.toHaveLength(0);
    expect(screen.getByText("Test User")).toBeInTheDocument();
    expect(screen.getByText("Requester")).toBeInTheDocument();
    expect(screen.getByRole("navigation", { name: "Primary" })).toBeInTheDocument();
    expect(screen.getByRole("navigation", { name: "Breadcrumb" })).toHaveTextContent("Dashboard");
    expect(screen.getByRole("button", { name: "Open command palette" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Open notifications" })).toBeDisabled();
    expect(screen.getByRole("main")).toHaveAttribute("id", "main-content");
    expect(screen.getByRole("contentinfo")).toHaveTextContent("Cognify");
  });

  it("marks the active navigation item without relying on color alone", async () => {
    mockPathname = "/requisitions/req-1";

    renderWithQuery(
      <AppShell>
        <h1>Requisition workspace</h1>
      </AppShell>,
    );

    const primaryNav = await screen.findByRole("navigation", { name: "Primary" });
    expect(within(primaryNav).getByRole("link", { name: "Requisitions" })).toHaveAttribute(
      "aria-current",
      "page",
    );
  });

  it("hides admin-only audit navigation from requester identities", async () => {
    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    expect(await screen.findByRole("navigation", { name: "Primary" })).toBeInTheDocument();
    expect(screen.queryByText("Audit")).not.toBeInTheDocument();
  });

  it("shows admin-only audit navigation when the identity can access admin areas", async () => {
    mockIdentity({
      ...requesterIdentity,
      activeRole: "admin",
      tenants: [{ id: "1", name: "Acme Procurement", role: "admin" }],
      permissions: {
        ...requesterIdentity.permissions,
        canAccessAdmin: true,
      },
    });

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    expect(await screen.findByText("Audit")).toBeInTheDocument();
    expect(screen.getByText("Audit")).toHaveAttribute("aria-disabled", "true");
  });

  it("opens and closes mobile navigation with the keyboard", async () => {
    const user = userEvent.setup();

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await screen.findAllByText("Acme Procurement");
    await user.click(screen.getByRole("button", { name: "Open navigation" }));
    expect(screen.getByRole("dialog", { name: "Navigation" })).toBeInTheDocument();

    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog", { name: "Navigation" })).not.toBeInTheDocument();
  });
});

describe("shell route helpers", () => {
  it("resolves route breadcrumbs", () => {
    expect(getBreadcrumbs("/requisitions/req-1/edit")).toEqual([
      { label: "Requisitions", href: "/requisitions" },
      { label: "Requisition workspace", href: "/requisitions/req-1" },
      { label: "Edit" },
    ]);
  });

  it("computes active paths for nested operational routes", () => {
    expect(isActivePath("/requisitions", "/requisitions/req-1")).toBe(true);
    expect(isActivePath("/dashboard", "/dashboard")).toBe(true);
    expect(isActivePath("/dashboard", "/dashboarding")).toBe(false);
  });

  it("filters navigation by permissions and implementation state", () => {
    const groups = getVisibleNavGroups(shellNavGroups, requesterIdentity.permissions);
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("Dashboard");
    expect(labels).toContain("Requisitions");
    expect(labels).toContain("Account");
    expect(labels).not.toContain("Audit");
  });
});
```

- [ ] **Step 3: Run the failing shell tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx
```

Expected: FAIL because `app-shell`, route config, utility helpers, notification host, and mobile nav files do not exist.

## Task 2: Shell Route Config And Utility Implementation

**Files:**

- Create: `apps/web/components/shell/shell-types.ts`
- Create: `apps/web/components/shell/shell-route-config.ts`
- Create: `apps/web/components/shell/shell-utils.ts`
- Test: `apps/web/components/shell/app-shell.test.tsx`

- [ ] **Step 1: Add shell-specific types**

Create `apps/web/components/shell/shell-types.ts`:

```ts
import type { LucideIcon } from "lucide-react";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";

export type ShellNavItem = {
  label: string;
  href: string;
  icon: LucideIcon;
  implemented: boolean;
  permission?: (permissions: IdentityPermissions) => boolean;
};

export type ShellNavGroup = {
  label: string;
  items: ShellNavItem[];
};

export type BreadcrumbItem = {
  label: string;
  href?: string;
};
```

- [ ] **Step 2: Add route config**

Create `apps/web/components/shell/shell-route-config.ts`:

```ts
import {
  Archive,
  Building2,
  CheckSquare,
  FileSearch,
  FileText,
  Gauge,
  ReceiptText,
  Scale,
  UserRound,
} from "lucide-react";
import type { BreadcrumbItem, ShellNavGroup } from "./shell-types";

const canUseRequisitions = (permissions: {
  canCreateRequisition: boolean;
  canViewSubmittedRequisitions: boolean;
  canUpdateOwnDraftRequisition: boolean;
  canSubmitOwnDraftRequisition: boolean;
}) =>
  permissions.canCreateRequisition ||
  permissions.canViewSubmittedRequisitions ||
  permissions.canUpdateOwnDraftRequisition ||
  permissions.canSubmitOwnDraftRequisition;

export const shellNavGroups: ShellNavGroup[] = [
  {
    label: "Work",
    items: [
      { label: "Dashboard", href: "/dashboard", icon: Gauge, implemented: true },
      {
        label: "Requisitions",
        href: "/requisitions",
        icon: FileText,
        implemented: true,
        permission: canUseRequisitions,
      },
      { label: "Approvals", href: "/approvals", icon: CheckSquare, implemented: false },
    ],
  },
  {
    label: "Sourcing",
    items: [
      { label: "Vendors", href: "/vendors", icon: Building2, implemented: false },
      { label: "Quotations", href: "/quotations", icon: ReceiptText, implemented: false },
      { label: "Comparison", href: "/comparison", icon: Scale, implemented: false },
    ],
  },
  {
    label: "Governance",
    items: [
      { label: "Evidence", href: "/evidence", icon: Archive, implemented: false },
      {
        label: "Audit",
        href: "/audit",
        icon: FileSearch,
        implemented: false,
        permission: (permissions) => permissions.canAccessAdmin,
      },
    ],
  },
  {
    label: "Manage",
    items: [{ label: "Account", href: "/account", icon: UserRound, implemented: true }],
  },
];

export function getBreadcrumbs(pathname: string): BreadcrumbItem[] {
  if (pathname === "/dashboard" || pathname === "/") {
    return [{ label: "Dashboard" }];
  }

  if (pathname === "/account") {
    return [{ label: "Account" }];
  }

  if (pathname === "/requisitions") {
    return [{ label: "Requisitions" }];
  }

  if (pathname === "/requisitions/new") {
    return [{ label: "Requisitions", href: "/requisitions" }, { label: "New" }];
  }

  const requisitionEditMatch = pathname.match(/^\/requisitions\/([^/]+)\/edit$/);
  if (requisitionEditMatch) {
    return [
      { label: "Requisitions", href: "/requisitions" },
      { label: "Requisition workspace", href: `/requisitions/${requisitionEditMatch[1]}` },
      { label: "Edit" },
    ];
  }

  if (/^\/requisitions\/[^/]+$/.test(pathname)) {
    return [{ label: "Requisitions", href: "/requisitions" }, { label: "Requisition workspace" }];
  }

  return [{ label: "Workspace" }];
}
```

- [ ] **Step 3: Add shell utilities**

Create `apps/web/components/shell/shell-utils.ts`:

```ts
import type {
  IdentityPermissions,
  TenantRole,
} from "@/features/identity/types/identity-view-model";
import type { ShellNavGroup } from "./shell-types";

export function formatTenantRole(role: TenantRole | null | undefined): string {
  if (!role) return "Member";

  return role
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

export function isActivePath(itemHref: string, pathname: string): boolean {
  if (itemHref === "/dashboard") return pathname === "/dashboard" || pathname === "/";
  if (itemHref === "/") return pathname === "/";

  return pathname === itemHref || pathname.startsWith(`${itemHref}/`);
}

export function getVisibleNavGroups(
  groups: ShellNavGroup[],
  permissions: IdentityPermissions,
): ShellNavGroup[] {
  return groups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => (item.permission ? item.permission(permissions) : true)),
    }))
    .filter((group) => group.items.length > 0);
}
```

- [ ] **Step 4: Run the shell helper tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx
```

Expected: still FAIL because `AppShell` and rendering components do not exist. The route helper tests should now compile once imports resolve through the missing `app-shell` file in the next task.

## Task 3: App Shell Component Implementation

**Files:**

- Create: `apps/web/components/shell/app-shell.tsx`
- Create: `apps/web/components/shell/breadcrumbs.tsx`
- Create: `apps/web/components/shell/mobile-shell-nav.tsx`
- Create: `apps/web/components/shell/notification-host.tsx`
- Create: `apps/web/components/shell/shell-footer.tsx`
- Create: `apps/web/components/shell/shell-header.tsx`
- Create: `apps/web/components/shell/shell-nav.tsx`
- Modify: `apps/web/components/shell/command-palette-host.tsx`
- Modify: `apps/web/components/shell/right-panel-host.tsx`
- Test: `apps/web/components/shell/app-shell.test.tsx`

- [ ] **Step 1: Implement breadcrumbs**

Create `apps/web/components/shell/breadcrumbs.tsx`:

```tsx
import Link from "next/link";
import { ChevronRight } from "lucide-react";
import type { BreadcrumbItem } from "./shell-types";

export function Breadcrumbs({ items }: { items: BreadcrumbItem[] }) {
  return (
    <nav aria-label="Breadcrumb" className="min-w-0 text-sm text-muted-foreground">
      <ol className="flex min-w-0 items-center gap-1">
        {items.map((item, index) => {
          const current = index === items.length - 1;

          return (
            <li key={`${item.label}-${index}`} className="flex min-w-0 items-center gap-1">
              {index > 0 ? <ChevronRight className="h-4 w-4 shrink-0" aria-hidden="true" /> : null}
              {item.href && !current ? (
                <Link className="truncate hover:text-foreground" href={item.href}>
                  {item.label}
                </Link>
              ) : (
                <span
                  className="truncate text-foreground"
                  aria-current={current ? "page" : undefined}
                >
                  {item.label}
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
```

- [ ] **Step 2: Refine command palette host**

Replace `apps/web/components/shell/command-palette-host.tsx` with:

```tsx
"use client";

import { Search } from "lucide-react";

export function CommandPaletteHost() {
  return (
    <button
      type="button"
      className="inline-flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm text-muted-foreground hover:text-foreground"
      aria-label="Open command palette"
    >
      <Search className="h-4 w-4" aria-hidden="true" />
      <span className="hidden sm:inline">Search</span>
    </button>
  );
}
```

- [ ] **Step 3: Add notification host**

Create `apps/web/components/shell/notification-host.tsx`:

```tsx
"use client";

import { Bell } from "lucide-react";

export function NotificationHost() {
  return (
    <button
      type="button"
      className="inline-flex min-h-10 w-10 items-center justify-center rounded-md border text-muted-foreground"
      aria-label="Open notifications"
      disabled
    >
      <Bell className="h-4 w-4" aria-hidden="true" />
    </button>
  );
}
```

- [ ] **Step 4: Refine right panel host**

Replace `apps/web/components/shell/right-panel-host.tsx` with:

```tsx
"use client";

export function RightPanelHost() {
  return <div id="right-panel-host" aria-hidden="true" />;
}
```

- [ ] **Step 5: Implement desktop navigation**

Create `apps/web/components/shell/shell-nav.tsx`:

```tsx
import Link from "next/link";
import type { ShellNavGroup } from "./shell-types";
import { isActivePath } from "./shell-utils";

export function ShellNav({
  groups,
  pathname,
  onNavigate,
}: {
  groups: ShellNavGroup[];
  pathname: string;
  onNavigate?: () => void;
}) {
  return (
    <nav aria-label="Primary" className="space-y-6">
      {groups.map((group) => (
        <div key={group.label}>
          <div className="px-2 text-xs font-semibold uppercase tracking-normal text-muted-foreground">
            {group.label}
          </div>
          <div className="mt-2 space-y-1">
            {group.items.map((item) => {
              const Icon = item.icon;
              const active = isActivePath(item.href, pathname);

              if (!item.implemented) {
                return (
                  <span
                    key={item.href}
                    className="flex min-h-10 items-center gap-3 rounded-md px-2 text-sm text-muted-foreground opacity-70"
                    aria-disabled="true"
                  >
                    <Icon className="h-4 w-4" aria-hidden="true" />
                    {item.label}
                  </span>
                );
              }

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  onClick={onNavigate}
                  aria-current={active ? "page" : undefined}
                  className={[
                    "flex min-h-10 items-center gap-3 rounded-md px-2 text-sm font-medium",
                    active
                      ? "border border-foreground bg-foreground text-background"
                      : "text-muted-foreground hover:bg-card hover:text-foreground",
                  ].join(" ")}
                >
                  <Icon className="h-4 w-4" aria-hidden="true" />
                  {item.label}
                </Link>
              );
            })}
          </div>
        </div>
      ))}
    </nav>
  );
}
```

- [ ] **Step 6: Implement mobile navigation**

Create `apps/web/components/shell/mobile-shell-nav.tsx`:

```tsx
"use client";

import { useEffect } from "react";
import { Menu, X } from "lucide-react";
import { ShellNav } from "./shell-nav";
import type { ShellNavGroup } from "./shell-types";

export function MobileShellNav({
  groups,
  pathname,
  open,
  onOpenChange,
}: {
  groups: ShellNavGroup[];
  pathname: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  useEffect(() => {
    if (!open) return;

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") onOpenChange(false);
    }

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [onOpenChange, open]);

  return (
    <>
      <button
        type="button"
        className="inline-flex min-h-10 w-10 items-center justify-center rounded-md border md:hidden"
        aria-label="Open navigation"
        aria-expanded={open}
        onClick={() => onOpenChange(true)}
      >
        <Menu className="h-4 w-4" aria-hidden="true" />
      </button>
      {open ? (
        <div className="fixed inset-0 z-40 md:hidden">
          <button
            type="button"
            className="absolute inset-0 bg-black/30"
            aria-label="Close navigation"
            onClick={() => onOpenChange(false)}
          />
          <div
            role="dialog"
            aria-label="Navigation"
            className="absolute inset-y-0 left-0 flex w-[min(22rem,90vw)] flex-col border-r bg-background p-4 shadow-lg"
          >
            <div className="flex items-center justify-between border-b pb-3">
              <div className="text-base font-semibold">Cognify</div>
              <button
                type="button"
                className="inline-flex min-h-10 w-10 items-center justify-center rounded-md border"
                aria-label="Close navigation"
                onClick={() => onOpenChange(false)}
              >
                <X className="h-4 w-4" aria-hidden="true" />
              </button>
            </div>
            <div className="mt-4 overflow-y-auto">
              <ShellNav
                groups={groups}
                pathname={pathname}
                onNavigate={() => onOpenChange(false)}
              />
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
```

- [ ] **Step 7: Implement shell header**

Create `apps/web/components/shell/shell-header.tsx`:

```tsx
import Link from "next/link";
import { CommandPaletteHost } from "./command-palette-host";
import { Breadcrumbs } from "./breadcrumbs";
import { NotificationHost } from "./notification-host";
import type { BreadcrumbItem } from "./shell-types";

export function ShellHeader({
  tenantName,
  userName,
  roleLabel,
  breadcrumbs,
  mobileNav,
}: {
  tenantName: string;
  userName: string;
  roleLabel: string;
  breadcrumbs: BreadcrumbItem[];
  mobileNav: React.ReactNode;
}) {
  return (
    <header className="sticky top-0 z-30 border-b bg-background/95 backdrop-blur">
      <div className="flex min-h-16 items-center gap-3 px-4 md:px-6">
        {mobileNav}
        <div className="min-w-0 flex-1">
          <div className="flex min-w-0 items-center gap-2 text-sm">
            <span className="truncate font-medium">{tenantName}</span>
            <span className="text-muted-foreground" aria-hidden="true">
              /
            </span>
            <span className="shrink-0 text-muted-foreground">{roleLabel}</span>
          </div>
          <div className="mt-1">
            <Breadcrumbs items={breadcrumbs} />
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <CommandPaletteHost />
          <NotificationHost />
          <Link
            href="/account"
            className="hidden min-h-10 max-w-44 items-center truncate rounded-md border px-3 text-sm text-muted-foreground hover:text-foreground sm:inline-flex"
          >
            {userName}
          </Link>
        </div>
      </div>
    </header>
  );
}
```

- [ ] **Step 8: Implement shell footer**

Create `apps/web/components/shell/shell-footer.tsx`:

```tsx
export function ShellFooter({ tenantName }: { tenantName: string }) {
  return (
    <footer className="border-t px-4 py-3 text-xs text-muted-foreground md:px-6" role="contentinfo">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <span>Cognify</span>
        <span className="truncate">Workspace: {tenantName}</span>
      </div>
    </footer>
  );
}
```

- [ ] **Step 9: Implement app shell**

Create `apps/web/components/shell/app-shell.tsx`:

```tsx
"use client";

import { useMemo, useState } from "react";
import { usePathname } from "next/navigation";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { getBreadcrumbs, shellNavGroups } from "./shell-route-config";
import { formatTenantRole, getVisibleNavGroups } from "./shell-utils";
import { MobileShellNav } from "./mobile-shell-nav";
import { RightPanelHost } from "./right-panel-host";
import { ShellFooter } from "./shell-footer";
import { ShellHeader } from "./shell-header";
import { ShellNav } from "./shell-nav";

export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname() || "/dashboard";
  const { data } = useCurrentUser();
  const context = data?.data;
  const tenantName = context?.activeTenant?.name ?? "Operational workspace";
  const userName = context?.user?.name ?? "Account";
  const roleLabel = formatTenantRole(context?.activeRole);
  const permissions = context?.permissions;
  const [mobileOpen, setMobileOpen] = useState(false);
  const breadcrumbs = useMemo(() => getBreadcrumbs(pathname), [pathname]);
  const groups = useMemo(
    () => (permissions ? getVisibleNavGroups(shellNavGroups, permissions) : []),
    [permissions],
  );

  return (
    <div className="min-h-screen bg-background text-foreground">
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-foreground focus:px-3 focus:py-2 focus:text-background"
      >
        Skip to main content
      </a>
      <aside className="fixed inset-y-0 left-0 hidden w-72 border-r bg-card px-4 py-5 md:block">
        <div className="text-lg font-semibold">Cognify</div>
        <div className="mt-1 truncate text-xs text-muted-foreground">{tenantName}</div>
        <div className="mt-8">
          <ShellNav groups={groups} pathname={pathname} />
        </div>
      </aside>
      <div className="flex min-h-screen flex-col md:pl-72">
        <ShellHeader
          tenantName={tenantName}
          userName={userName}
          roleLabel={roleLabel}
          breadcrumbs={breadcrumbs}
          mobileNav={
            <MobileShellNav
              groups={groups}
              pathname={pathname}
              open={mobileOpen}
              onOpenChange={setMobileOpen}
            />
          }
        />
        <main id="main-content" className="flex-1 px-4 py-6 md:px-6" tabIndex={-1}>
          {children}
        </main>
        <ShellFooter tenantName={tenantName} />
      </div>
      <RightPanelHost />
    </div>
  );
}
```

- [ ] **Step 10: Run shell tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx
```

Expected: PASS.

## Task 4: Route Layout Integration

**Files:**

- Modify: `apps/web/app/(dashboard)/layout.tsx`
- Modify: `apps/web/app/(workspace)/layout.tsx`
- Delete: `apps/web/components/shell/dashboard-shell.tsx`
- Delete: `apps/web/components/shell/workspace-shell.tsx`
- Test: `apps/web/components/shell/app-shell.test.tsx`

- [ ] **Step 1: Add dashboard layout integration coverage**

In `apps/web/components/shell/app-shell.test.tsx`, add this import:

```tsx
import DashboardLayout from "../../app/(dashboard)/layout";
```

Then add this test inside `describe("app shell", () => { ... })`:

```tsx
it("protects dashboard routes with the operational shell", async () => {
  renderWithQuery(
    <DashboardLayout>
      <h1>Dashboard content</h1>
    </DashboardLayout>,
  );

  expect(await screen.findAllByText("Acme Procurement")).not.toHaveLength(0);
  expect(screen.getByRole("heading", { name: "Dashboard content" })).toBeInTheDocument();
});
```

- [ ] **Step 2: Run the failing dashboard layout integration test**

Run:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx
```

Expected: FAIL because the dashboard route still uses the old dashboard shell and is not wrapped by `SessionGate`.

- [ ] **Step 3: Protect dashboard layout and render `AppShell`**

Replace `apps/web/app/(dashboard)/layout.tsx` with:

```tsx
import { AppShell } from "@/components/shell/app-shell";
import { SessionGate } from "@/features/identity/workflows/session-gate";

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return (
    <SessionGate>
      <AppShell>{children}</AppShell>
    </SessionGate>
  );
}
```

- [ ] **Step 4: Render `AppShell` in workspace layout**

Replace `apps/web/app/(workspace)/layout.tsx` with:

```tsx
import { AppShell } from "@/components/shell/app-shell";
import { SessionGate } from "@/features/identity/workflows/session-gate";

export default function WorkspaceLayout({ children }: { children: React.ReactNode }) {
  return (
    <SessionGate>
      <AppShell>{children}</AppShell>
    </SessionGate>
  );
}
```

- [ ] **Step 5: Remove obsolete shells**

Delete:

```txt
apps/web/components/shell/dashboard-shell.tsx
apps/web/components/shell/workspace-shell.tsx
```

- [ ] **Step 6: Verify no imports reference obsolete shells**

Run:

```bash
rg "DashboardShell|WorkspaceShell|dashboard-shell|workspace-shell" apps/web
```

Expected: no output.

- [ ] **Step 7: Run shell tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx
```

Expected: PASS.

## Task 5: Record Workspace Layout Tests

**Files:**

- Create: `apps/web/components/workspace/record-workspace-layout.test.tsx`
- Create in Task 6: `apps/web/components/workspace/record-workspace-layout.tsx`

- [ ] **Step 1: Add failing record workspace tests**

Create `apps/web/components/workspace/record-workspace-layout.test.tsx`:

```tsx
import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { RecordWorkspaceLayout } from "./record-workspace-layout";

describe("record workspace layout", () => {
  it("renders title, status, metadata, actions, sections, main content, and sidebar", () => {
    render(
      <RecordWorkspaceLayout
        backHref="/requisitions"
        backLabel="Back to requisitions"
        eyebrow="REQ-2026-000001"
        title="Laptop refresh"
        status={<span>Draft</span>}
        primaryActions={<button type="button">Review and submit</button>}
        secondaryActions={<button type="button">Edit draft</button>}
        metadata={[
          { label: "Estimated total", value: "MYR 3,600.00" },
          { label: "Needed by", value: "2026-06-15" },
          { label: "Requester", value: "Test User" },
        ]}
        sections={[
          { id: "overview", label: "Overview" },
          { id: "line-items", label: "Line items" },
          { id: "activity", label: "Activity" },
        ]}
        sidebar={<aside aria-label="Readiness">Checklist</aside>}
      >
        <section id="overview">
          <h2>Overview</h2>
        </section>
      </RecordWorkspaceLayout>,
    );

    expect(screen.getByRole("link", { name: "Back to requisitions" })).toHaveAttribute(
      "href",
      "/requisitions",
    );
    expect(screen.getByRole("heading", { name: "Laptop refresh", level: 1 })).toBeInTheDocument();
    expect(screen.getByText("REQ-2026-000001")).toBeInTheDocument();
    expect(screen.getByText("Draft")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Review and submit" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Edit draft" })).toBeInTheDocument();

    const metadata = screen.getByRole("group", { name: "Record metadata" });
    expect(within(metadata).getByText("Estimated total")).toBeInTheDocument();
    expect(within(metadata).getByText("MYR 3,600.00")).toBeInTheDocument();

    expect(screen.getByRole("navigation", { name: "Record sections" })).toHaveTextContent(
      "Overview",
    );
    expect(screen.getByRole("complementary", { name: "Record sidebar" })).toHaveTextContent(
      "Checklist",
    );
  });
});
```

- [ ] **Step 2: Run the failing record layout test**

Run:

```bash
pnpm --filter @cognify/web test -- components/workspace/record-workspace-layout.test.tsx
```

Expected: FAIL because `record-workspace-layout.tsx` does not exist.

## Task 6: Record Workspace Layout Implementation

**Files:**

- Create: `apps/web/components/workspace/record-workspace-layout.tsx`
- Test: `apps/web/components/workspace/record-workspace-layout.test.tsx`

- [ ] **Step 1: Implement record workspace layout**

Create `apps/web/components/workspace/record-workspace-layout.tsx`:

```tsx
import Link from "next/link";
import { ArrowLeft } from "lucide-react";

export type RecordWorkspaceMetadataItem = {
  label: string;
  value: React.ReactNode;
};

export type RecordWorkspaceSection = {
  id: string;
  label: string;
};

export function RecordWorkspaceLayout({
  backHref,
  backLabel,
  eyebrow,
  title,
  status,
  metadata,
  sections,
  primaryActions,
  secondaryActions,
  sidebar,
  children,
}: {
  backHref: string;
  backLabel: string;
  eyebrow?: React.ReactNode;
  title: string;
  status?: React.ReactNode;
  metadata: RecordWorkspaceMetadataItem[];
  sections: RecordWorkspaceSection[];
  primaryActions?: React.ReactNode;
  secondaryActions?: React.ReactNode;
  sidebar?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <section className="space-y-5">
      <Link
        href={backHref}
        className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm font-medium"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
        {backLabel}
      </Link>

      <header className="grid gap-4 border-b pb-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-3">
            {eyebrow ? (
              <div className="font-mono text-xs text-muted-foreground">{eyebrow}</div>
            ) : null}
            {status}
          </div>
          <h1 className="mt-3 text-2xl font-semibold">{title}</h1>
          <dl
            className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3"
            role="group"
            aria-label="Record metadata"
          >
            {metadata.map((item) => (
              <div key={item.label} className="min-w-0">
                <dt className="text-muted-foreground">{item.label}</dt>
                <dd className="truncate font-medium">{item.value}</dd>
              </div>
            ))}
          </dl>
        </div>

        {(primaryActions || secondaryActions) && (
          <div className="flex flex-col gap-2 rounded-md border p-3">
            {primaryActions}
            {secondaryActions}
          </div>
        )}
      </header>

      {sections.length > 0 ? (
        <nav aria-label="Record sections" className="overflow-x-auto border-b">
          <div className="flex min-w-max gap-1">
            {sections.map((section) => (
              <a
                key={section.id}
                href={`#${section.id}`}
                className="min-h-10 rounded-t-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
              >
                {section.label}
              </a>
            ))}
          </div>
        </nav>
      ) : null}

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="min-w-0 space-y-5">{children}</div>
        {sidebar ? (
          <aside aria-label="Record sidebar" className="space-y-5">
            {sidebar}
          </aside>
        ) : null}
      </div>
    </section>
  );
}
```

- [ ] **Step 2: Run record layout test**

Run:

```bash
pnpm --filter @cognify/web test -- components/workspace/record-workspace-layout.test.tsx
```

Expected: PASS.

## Task 7: Requisition Detail Migration

**Files:**

- Modify: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`
- Modify: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`
- Test: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`

- [ ] **Step 1: Add requisition detail regression test**

Append this test to `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`:

```tsx
import { RequisitionDetailPage } from "../workflows/requisition-detail-page";
```

If the import block already exists, add only the new import beside the existing workflow imports. Then add this test inside `describe("requisitions workflow", () => { ... })`:

```tsx
it("renders requisition detail inside the record workspace layout", async () => {
  renderWithQuery(<RequisitionDetailPage requisitionId="req-1" />);

  expect(await screen.findByRole("link", { name: "Back to requisitions" })).toHaveAttribute(
    "href",
    "/requisitions",
  );
  expect(
    await screen.findByRole("heading", { name: "Field laptop refresh", level: 1 }),
  ).toBeInTheDocument();
  expect(screen.getByRole("group", { name: "Record metadata" })).toHaveTextContent(
    "Estimated total",
  );
  expect(screen.getByRole("navigation", { name: "Record sections" })).toHaveTextContent("Activity");
  expect(screen.getByRole("complementary", { name: "Record sidebar" })).toHaveTextContent(
    "Approval readiness",
  );
});
```

- [ ] **Step 2: Run the failing requisition detail test**

Run:

```bash
pnpm --filter @cognify/web test -- features/requisitions/tests/requisitions-workflow.test.tsx
```

Expected: FAIL because the current detail page does not expose `RecordWorkspaceLayout` landmarks.

- [ ] **Step 3: Migrate requisition detail page**

Replace `apps/web/features/requisitions/workflows/requisition-detail-page.tsx` with:

```tsx
"use client";

import Link from "next/link";
import { Pencil, Send } from "lucide-react";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { RequisitionActivityTimeline } from "../components/requisition-activity-timeline";
import { RequisitionStatusBadge } from "../components/requisition-status-badge";
import { SubmissionChecklist } from "../components/submission-checklist";
import { useRequisition, useRequisitionActivity } from "../hooks/use-requisition";
import { formatMoney } from "../utils/requisition-totals";

export function RequisitionDetailPage({ requisitionId }: { requisitionId: string }) {
  const requisitionQuery = useRequisition(requisitionId);
  const activityQuery = useRequisitionActivity(requisitionId);
  const requisition = requisitionQuery.data;

  if (requisitionQuery.isLoading) {
    return (
      <div className="rounded-md border p-4 text-sm text-muted-foreground">
        Loading requisition workspace
      </div>
    );
  }

  if (requisitionQuery.isError || !requisition) {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        Requisition could not be loaded.
      </div>
    );
  }

  const actions = (
    <>
      {requisition.permissions.canSubmit ? (
        <Link
          href={`/requisitions/${requisition.id}/edit`}
          className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-foreground px-3 text-sm font-medium text-background"
        >
          <Send className="h-4 w-4" aria-hidden="true" />
          Review and submit
        </Link>
      ) : null}
      {requisition.permissions.canUpdate ? (
        <Link
          href={`/requisitions/${requisition.id}/edit`}
          className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"
        >
          <Pencil className="h-4 w-4" aria-hidden="true" />
          Edit draft
        </Link>
      ) : null}
      {!requisition.permissions.canSubmit && !requisition.permissions.canUpdate ? (
        <p className="text-sm text-muted-foreground">
          Requester editing is locked after submission.
        </p>
      ) : null}
    </>
  );

  return (
    <RecordWorkspaceLayout
      backHref="/requisitions"
      backLabel="Back to requisitions"
      eyebrow={requisition.number}
      title={requisition.title}
      status={<RequisitionStatusBadge status={requisition.status} />}
      metadata={[
        {
          label: "Estimated total",
          value: (
            <span className="font-mono tabular-nums">
              {formatMoney(requisition.estimatedTotal, requisition.currency)}
            </span>
          ),
        },
        { label: "Needed by", value: requisition.neededByDate },
        { label: "Requester", value: requisition.requester.name },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "line-items", label: "Line items" },
        { id: "activity", label: "Activity" },
        { id: "readiness", label: "Readiness" },
      ]}
      primaryActions={actions}
      sidebar={
        <>
          <SubmissionChecklist
            values={{
              title: requisition.title,
              businessJustification: requisition.businessJustification,
              neededByDate: requisition.neededByDate,
              department: requisition.department ?? "",
              costCenter: requisition.costCenter ?? "",
              deliveryLocation: requisition.deliveryLocation ?? "",
              currency: requisition.currency,
              lineItems: requisition.lineItems,
            }}
          />
          <section id="readiness" className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Approval readiness</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              Approval routing will attach here after the submitted workflow is active.
            </p>
          </section>
        </>
      }
    >
      <section id="overview" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Overview</h2>
        <p className="mt-2 text-sm leading-6">{requisition.businessJustification}</p>
      </section>

      <section id="line-items" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Line items</h2>
        <div className="mt-3 space-y-2">
          {requisition.lineItems.map((item) => (
            <div
              key={item.id ?? item.name}
              className="grid gap-2 rounded-md border p-3 text-sm sm:grid-cols-[minmax(0,1fr)_7rem_8rem]"
            >
              <span className="font-medium">{item.name}</span>
              <span className="tabular-nums">
                {item.quantity} {item.unit}
              </span>
              <span className="font-mono tabular-nums">
                {formatMoney(
                  item.estimatedLineTotal ?? item.quantity * item.estimatedUnitPrice,
                  item.currency,
                )}
              </span>
            </div>
          ))}
        </div>
      </section>

      <section id="activity" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Activity</h2>
        <div className="mt-3">
          <RequisitionActivityTimeline events={activityQuery.data?.data ?? []} />
        </div>
      </section>
    </RecordWorkspaceLayout>
  );
}
```

- [ ] **Step 4: Run requisition workflow tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/requisitions/tests/requisitions-workflow.test.tsx
```

Expected: PASS.

## Task 8: Focused Verification And Cleanup

**Files:**

- Review: `apps/web/components/shell/*`
- Review: `apps/web/components/workspace/*`
- Review: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`
- Review: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`

- [ ] **Step 1: Run the focused shell and workspace tests**

Run:

```bash
pnpm --filter @cognify/web test -- components/shell/app-shell.test.tsx components/workspace/record-workspace-layout.test.tsx features/requisitions/tests/requisitions-workflow.test.tsx
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

- [ ] **Step 4: Confirm no boundary violations**

Run:

```bash
git diff --name-only
```

Expected touched implementation paths are limited to:

```txt
apps/web/app/(dashboard)/layout.tsx
apps/web/app/(workspace)/layout.tsx
apps/web/components/shell/*
apps/web/components/workspace/*
apps/web/features/requisitions/workflows/requisition-detail-page.tsx
apps/web/features/requisitions/tests/requisitions-workflow.test.tsx
```

No files under `packages/ui`, `packages/api-client`, or `apps/api` should be changed for this epic.

- [ ] **Step 5: Commit implementation when executing this plan**

Do not run this step while only drafting the design and plan documents. When implementation is executed later and verification passes, commit with:

```bash
git add 'apps/web/app/(dashboard)/layout.tsx' 'apps/web/app/(workspace)/layout.tsx' apps/web/components/shell apps/web/components/workspace apps/web/features/requisitions/workflows/requisition-detail-page.tsx apps/web/features/requisitions/tests/requisitions-workflow.test.tsx
git commit -m "feat(web): add app shell foundation"
```

## Self-Review Checklist

- Spec coverage: operational shell, tenant header, nav registry, breadcrumbs, extension hosts, responsive/mobile shell, accessibility landmarks, and record workspace layout are each covered by a task.
- TDD coverage: shell tests, record layout tests, and requisition detail regression test are written before production code in their tasks.
- Boundary check: implementation stays in `apps/web`; no shared package, API, or OpenAPI changes are planned.
- Type consistency: `ShellNavGroup`, `ShellNavItem`, `BreadcrumbItem`, `RecordWorkspaceMetadataItem`, and `RecordWorkspaceSection` are defined before use.
- Verification: focused Vitest, typecheck, lint, and diff boundary checks are included.
