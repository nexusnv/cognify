# Cognify Desktop Design System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Cognify's authenticated web shell with a desktop-only, shadcn `sidebar-07`-style shell that supports route-aware primary and secondary left sidebars while keeping shadcn primitives uncustomized.

**Architecture:** Keep all Cognify shell and navigation behavior in `apps/web/components/shell`. Reuse factory shadcn primitives from `@cognify/ui/components/sidebar`, `button`, `tooltip`, and existing header/footer hosts without changing `packages/ui`. Add a typed route context that decides whether the current route has secondary navigation; the shell then controls either the primary sidebar state or the secondary sidebar state according to the approved design.

**Tech Stack:** Next.js App Router, React, TypeScript, Vitest, Testing Library, MSW, lucide-react icons, factory shadcn/Radix primitives from `@cognify/ui`.

---

## Approved Design

Spec: `docs/superpowers/specs/2026-06-08-cognify-desktop-design-system-design.md`

Core constraints:

- Desktop web only. Do not preserve the authenticated mobile drawer.
- `packages/ui` remains factory shadcn primitives only.
- Use current default shadcn theme and token classes.
- Use shadcn `Sidebar` primitives in app composition.
- If a route has secondary navigation, primary sidebar is forced into icon mode and the visible toggle controls only the secondary sidebar.
- If a route has no secondary navigation, primary sidebar defaults expanded and the visible toggle controls primary collapse.
- First slice includes one Procurement secondary sidebar example and keeps feature workflows unchanged.

## File Structure

- Modify `apps/web/components/shell/shell-types.ts`
  - Add primary/secondary navigation types and route context types.
- Modify `apps/web/components/shell/shell-route-config.ts`
  - Convert the existing global navigation into stable primary areas plus contextual secondary groups.
  - Add `getShellRouteContext(pathname, permissions)` while preserving `getBreadcrumbs`.
- Modify `apps/web/components/shell/shell-utils.ts`
  - Keep active-path and visibility filtering helpers.
  - Add route-family matching helpers for secondary navigation.
- Modify `apps/web/components/shell/shell-nav.tsx`
  - Render primary and secondary navigation through factory shadcn sidebar menu primitives.
  - Keep link accessibility and `aria-current` behavior.
- Modify `apps/web/components/shell/shell-header.tsx`
  - Remove mobile navigation prop.
  - Add a desktop sidebar toggle slot rendered inside the content header.
- Modify `apps/web/components/shell/app-shell.tsx`
  - Replace single-sidebar plus mobile drawer with desktop shell composition.
  - Add primary/secondary controlled sidebar state.
  - Mount two independent `SidebarProvider` instances when secondary navigation exists so each sidebar has its own collapse state.
- Modify `apps/web/components/shell/app-shell.test.tsx`
  - Remove mobile drawer assertions.
  - Add tests for primary-only and primary-plus-secondary sidebar modes.
- Modify `apps/web/components/shell/shell-route-config.test.tsx`
  - Add route-context and secondary-navigation visibility tests.
- Modify `apps/web/components/shell/shell-utils.test.tsx`
  - Add visible primary-navigation helper coverage.
- Do not modify `packages/ui/src/components/sidebar.tsx`.
- Do not modify `apps/web/app/globals.css`.

---

## Task 1: Route Context Types and Registry

**Files:**

- Modify: `apps/web/components/shell/shell-types.ts`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Test: `apps/web/components/shell/shell-route-config.test.tsx`

- [ ] **Step 1: Write route-context tests**

Add these tests to `apps/web/components/shell/shell-route-config.test.tsx`.

```tsx
import { describe, expect, it } from "vitest";
import { requesterIdentity } from "../../features/identity/mocks/identity-fixtures";
import {
  getBreadcrumbs,
  getShellRouteContext,
  primaryShellNavItems,
} from "./shell-route-config";

describe("shell route context", () => {
  it("uses primary-only dashboard navigation by default", () => {
    const context = getShellRouteContext("/dashboard", requesterIdentity.permissions);

    expect(context.primaryArea).toBe("home");
    expect(context.secondaryGroups).toEqual([]);
    expect(context.hasSecondarySidebar).toBe(false);
  });

  it("uses procurement secondary navigation for requisition routes", () => {
    const context = getShellRouteContext("/requisitions/req-1", requesterIdentity.permissions);

    expect(context.primaryArea).toBe("procurement");
    expect(context.hasSecondarySidebar).toBe(true);
    expect(context.secondaryGroups.map((group) => group.id)).toContain("procurement-work");
    expect(
      context.secondaryGroups.flatMap((group) => group.items).map((item) => item.label),
    ).toContain("Requisitions");
  });

  it("keeps unimplemented secondary links disabled instead of active links", () => {
    const context = getShellRouteContext("/requisitions", requesterIdentity.permissions);
    const purchaseOrders = context.secondaryGroups
      .flatMap((group) => group.items)
      .find((item) => item.label === "Purchase orders");

    expect(purchaseOrders).toMatchObject({
      href: "/purchase-orders",
      implemented: false,
    });
  });

  it("filters admin-only primary areas by permission", () => {
    const requesterLabels = primaryShellNavItems
      .filter((item) =>
        item.permission ? item.permission(requesterIdentity.permissions) : true,
      )
      .map((item) => item.label);

    expect(requesterLabels).not.toContain("Admin");
  });

  it("preserves existing breadcrumbs while adding route context", () => {
    expect(getBreadcrumbs("/requisitions/req-1")).toEqual([
      { label: "Requisitions", href: "/requisitions" },
      { label: "Requisition workspace" },
    ]);
  });
});
```

- [ ] **Step 2: Run the route-context tests and confirm they fail**

Run:

```bash
pnpm --dir apps/web exec vitest run components/shell/shell-route-config.test.tsx
```

Expected: FAIL because `getShellRouteContext` and `primaryShellNavItems` are not exported yet.

- [ ] **Step 3: Add route-context types**

Update `apps/web/components/shell/shell-types.ts` to include these types while keeping `BreadcrumbItem`.

```ts
import type { LucideIcon } from "lucide-react";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";

export type ShellPrimaryArea =
  | "home"
  | "my-work"
  | "procurement"
  | "vendors"
  | "finance"
  | "evidence"
  | "analytics"
  | "governance"
  | "admin"
  | "integrations"
  | "account";

export type ShellPageTemplate =
  | "dashboard"
  | "module-landing"
  | "work-queue"
  | "record-detail"
  | "form-workspace"
  | "utility";

export type ShellNavItem = {
  id: string;
  label: string;
  href: string;
  icon: LucideIcon;
  implemented: boolean;
  permission?: (permissions: IdentityPermissions) => boolean;
};

export type ShellPrimaryNavItem = ShellNavItem & {
  area: ShellPrimaryArea;
};

export type ShellSecondaryNavItem = ShellNavItem;

export type ShellNavGroup = {
  id: string;
  label: string;
  items: ShellSecondaryNavItem[];
};

export type ShellRouteContext = {
  primaryArea: ShellPrimaryArea;
  pageTemplate: ShellPageTemplate;
  secondaryGroups: ShellNavGroup[];
  hasSecondarySidebar: boolean;
};

export type BreadcrumbItem = {
  id?: string;
  label: string;
  href?: string;
};
```

- [ ] **Step 4: Add primary and secondary registry exports**

In `apps/web/components/shell/shell-route-config.ts`, keep the existing permission helpers and breadcrumb logic. Replace `shellNavGroups` with `primaryShellNavItems`, `procurementSecondaryNavGroups`, and `getShellRouteContext`.

Use these primary items:

```ts
export const primaryShellNavItems: ShellPrimaryNavItem[] = [
  { id: "home", area: "home", label: "Home", href: "/dashboard", icon: Gauge, implemented: true },
  { id: "my-work", area: "my-work", label: "My Work", href: "/approvals", icon: CheckSquare, implemented: true },
  {
    id: "procurement",
    area: "procurement",
    label: "Procurement",
    href: "/requisitions",
    icon: FileText,
    implemented: true,
    permission: canUseRequisitions,
  },
  { id: "vendors", area: "vendors", label: "Vendors", href: "/vendors", icon: Building2, implemented: false },
  {
    id: "finance",
    area: "finance",
    label: "Finance",
    href: "/finance",
    icon: ReceiptText,
    implemented: false,
  },
  { id: "evidence", area: "evidence", label: "Evidence", href: "/evidence", icon: Archive, implemented: false },
  { id: "analytics", area: "analytics", label: "Analytics", href: "/analytics", icon: Activity, implemented: false },
  {
    id: "governance",
    area: "governance",
    label: "Governance",
    href: "/approval-policies",
    icon: ClipboardCheck,
    implemented: true,
    permission: canUseAudit,
  },
  {
    id: "admin",
    area: "admin",
    label: "Admin",
    href: "/system",
    icon: FileSearch,
    implemented: true,
    permission: canUseAudit,
  },
  { id: "integrations", area: "integrations", label: "Integrations", href: "/integrations", icon: UserRound, implemented: false },
  { id: "account", area: "account", label: "Account", href: "/account", icon: UserRound, implemented: true },
];
```

Use these Procurement secondary groups:

```ts
export const procurementSecondaryNavGroups: ShellNavGroup[] = [
  {
    id: "procurement-work",
    label: "Procurement",
    items: [
      {
        id: "requisitions",
        label: "Requisitions",
        href: "/requisitions",
        icon: FileText,
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        id: "projects",
        label: "Projects",
        href: "/projects",
        icon: FolderKanban,
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        id: "buyer-intake",
        label: "Buyer intake",
        href: "/sourcing/intake",
        icon: ClipboardCheck,
        implemented: true,
        permission: canUseSourcingIntake,
      },
      {
        id: "calendar",
        label: "Calendar",
        href: "/calendar",
        icon: CalendarDays,
        implemented: true,
        permission: canUseCalendar,
      },
    ],
  },
  {
    id: "procurement-sourcing",
    label: "Sourcing",
    items: [
      {
        id: "quotations",
        label: "Quotations",
        href: "/quotations/normalizations",
        icon: ReceiptText,
        implemented: true,
        permission: canUseQuotationNormalizations,
      },
      { id: "rfqs", label: "RFQs", href: "/sourcing/rfqs", icon: ClipboardCheck, implemented: false },
      { id: "awards", label: "Awards", href: "/quotations/awards", icon: CheckSquare, implemented: false },
    ],
  },
  {
    id: "procurement-fulfillment",
    label: "Fulfillment",
    items: [
      { id: "purchase-orders", label: "Purchase orders", href: "/purchase-orders", icon: ReceiptText, implemented: false },
      { id: "receiving", label: "Receiving", href: "/receiving", icon: ClipboardCheck, implemented: false },
    ],
  },
];
```

Add this route context helper:

```ts
const PROCUREMENT_ROUTE_PATTERNS = [
  /^\/requisitions(?:\/.*)?$/,
  /^\/projects(?:\/.*)?$/,
  /^\/sourcing(?:\/.*)?$/,
  /^\/quotations(?:\/.*)?$/,
  /^\/calendar$/,
];

function matchesAnyPattern(pathname: string, patterns: RegExp[]) {
  return patterns.some((pattern) => pattern.test(pathname));
}

export function getShellRouteContext(
  pathname: string,
  permissions: IdentityPermissions | undefined,
): ShellRouteContext {
  const normalizedPathname = pathname.replace(/\/+$/, "") || "/";

  if (matchesAnyPattern(normalizedPathname, PROCUREMENT_ROUTE_PATTERNS)) {
    const secondaryGroups = permissions
      ? getVisibleNavGroups(procurementSecondaryNavGroups, permissions)
      : [];

    return {
      primaryArea: "procurement",
      pageTemplate: normalizedPathname.includes("/new") ? "form-workspace" : "work-queue",
      secondaryGroups,
      hasSecondarySidebar: secondaryGroups.length > 0,
    };
  }

  if (normalizedPathname === "/approvals" || normalizedPathname.startsWith("/approvals/")) {
    return {
      primaryArea: "my-work",
      pageTemplate: normalizedPathname.startsWith("/approvals/tasks/")
        ? "record-detail"
        : "work-queue",
      secondaryGroups: [],
      hasSecondarySidebar: false,
    };
  }

  if (normalizedPathname === "/approval-policies" || normalizedPathname.startsWith("/approval-policies/")) {
    return {
      primaryArea: "governance",
      pageTemplate: normalizedPathname.endsWith("/new") ? "form-workspace" : "work-queue",
      secondaryGroups: [],
      hasSecondarySidebar: false,
    };
  }

  if (normalizedPathname === "/system") {
    return {
      primaryArea: "admin",
      pageTemplate: "utility",
      secondaryGroups: [],
      hasSecondarySidebar: false,
    };
  }

  if (normalizedPathname === "/account") {
    return {
      primaryArea: "account",
      pageTemplate: "utility",
      secondaryGroups: [],
      hasSecondarySidebar: false,
    };
  }

  return {
    primaryArea: "home",
    pageTemplate: "dashboard",
    secondaryGroups: [],
    hasSecondarySidebar: false,
  };
}
```

- [ ] **Step 5: Run route-context tests**

Run:

```bash
pnpm --dir apps/web exec vitest run components/shell/shell-route-config.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Checkpoint commit**

Run:

```bash
git add apps/web/components/shell/shell-types.ts apps/web/components/shell/shell-route-config.ts apps/web/components/shell/shell-route-config.test.tsx
git commit -m "feat(web): add desktop shell route context"
```

---

## Task 2: Primary and Secondary Navigation Components

**Files:**

- Modify: `apps/web/components/shell/shell-nav.tsx`
- Test: `apps/web/components/shell/app-shell.test.tsx`

- [ ] **Step 1: Write navigation rendering tests**

In `apps/web/components/shell/app-shell.test.tsx`, update imports only as needed and add these tests inside `describe("app shell", () => { ... })`.

```tsx
it("renders primary-only desktop navigation on dashboard routes", async () => {
  mockPathname = "/dashboard";

  renderWithQuery(
    <AppShell>
      <h1>Dashboard content</h1>
    </AppShell>,
  );

  await expectIdentityLoaded();

  expect(screen.getByRole("navigation", { name: "Primary product areas" })).toBeInTheDocument();
  expect(screen.queryByRole("navigation", { name: "Secondary workspace navigation" })).not.toBeInTheDocument();
  expect(screen.getByRole("link", { name: /Home/i })).toHaveAttribute("aria-current", "page");
  expect(screen.getByRole("button", { name: "Collapse primary sidebar" })).toBeInTheDocument();
});

it("renders procurement secondary navigation for requisition routes", async () => {
  mockPathname = "/requisitions";

  renderWithQuery(
    <AppShell>
      <h1>Requisitions</h1>
    </AppShell>,
  );

  await expectIdentityLoaded();

  expect(screen.getByRole("navigation", { name: "Primary product areas" })).toBeInTheDocument();
  const secondaryNav = screen.getByRole("navigation", { name: "Secondary workspace navigation" });
  expect(within(secondaryNav).getByRole("link", { name: /Requisitions/i })).toHaveAttribute(
    "aria-current",
    "page",
  );
  expect(within(secondaryNav).getByText("Fulfillment")).toBeInTheDocument();
  expect(within(secondaryNav).getByRole("link", { name: /Purchase orders/i })).toHaveAttribute(
    "aria-disabled",
    "true",
  );
  expect(screen.getByRole("button", { name: "Collapse secondary sidebar" })).toBeInTheDocument();
  expect(screen.queryByRole("button", { name: "Collapse primary sidebar" })).not.toBeInTheDocument();
});
```

- [ ] **Step 2: Run app-shell tests and confirm they fail**

Run:

```bash
pnpm --dir apps/web exec vitest run components/shell/app-shell.test.tsx
```

Expected: FAIL because the new navigation landmarks and desktop toggle labels do not exist yet.

- [ ] **Step 3: Replace `ShellNav` with primary and secondary renderers**

Update `apps/web/components/shell/shell-nav.tsx` to export `PrimaryShellNav` and `SecondaryShellNav`.

```tsx
import Link from "next/link";
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@cognify/ui/components/sidebar";
import type { ShellNavGroup, ShellPrimaryArea, ShellPrimaryNavItem } from "./shell-types";
import { isActivePath } from "./shell-utils";

function DisabledNavItem({ item }: { item: ShellPrimaryNavItem | ShellNavGroup["items"][number] }) {
  const Icon = item.icon;

  return (
    <SidebarMenuItem key={item.href}>
      <SidebarMenuButton aria-disabled="true" disabled tooltip={item.label}>
        <Icon aria-hidden="true" />
        <span>{item.label}</span>
      </SidebarMenuButton>
    </SidebarMenuItem>
  );
}

export function PrimaryShellNav({
  items,
  activeArea,
  pathname,
}: {
  items: ShellPrimaryNavItem[];
  activeArea: ShellPrimaryArea;
  pathname: string;
}) {
  return (
    <nav aria-label="Primary product areas">
      <SidebarMenu>
        {items.map((item) => {
          const Icon = item.icon;
          const active = item.area === activeArea || isActivePath(item.href, pathname);

          if (!item.implemented) {
            return <DisabledNavItem key={item.href} item={item} />;
          }

          return (
            <SidebarMenuItem key={item.href}>
              <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                <Link href={item.href} aria-current={active ? "page" : undefined}>
                  <Icon aria-hidden="true" />
                  <span>{item.label}</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          );
        })}
      </SidebarMenu>
    </nav>
  );
}

export function SecondaryShellNav({
  groups,
  pathname,
}: {
  groups: ShellNavGroup[];
  pathname: string;
}) {
  return (
    <nav aria-label="Secondary workspace navigation" className="space-y-2">
      {groups.map((group) => (
        <SidebarGroup key={group.id}>
          <SidebarGroupLabel asChild>
            <h2>{group.label}</h2>
          </SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {group.items.map((item) => {
                const Icon = item.icon;
                const active = isActivePath(item.href, pathname);

                if (!item.implemented) {
                  return <DisabledNavItem key={item.href} item={item} />;
                }

                return (
                  <SidebarMenuItem key={item.href}>
                    <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                      <Link href={item.href} aria-current={active ? "page" : undefined}>
                        <Icon aria-hidden="true" />
                        <span>{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                );
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      ))}
    </nav>
  );
}
```

- [ ] **Step 4: Run app-shell tests**

Run:

```bash
pnpm --dir apps/web exec vitest run components/shell/app-shell.test.tsx
```

Expected: still FAIL because `AppShell` does not consume these components yet.

- [ ] **Step 5: Checkpoint commit**

Run:

```bash
git add apps/web/components/shell/shell-nav.tsx apps/web/components/shell/app-shell.test.tsx
git commit -m "feat(web): add desktop shell navigation renderers"
```

---

## Task 3: Desktop Shell State and Layout

**Files:**

- Modify: `apps/web/components/shell/app-shell.tsx`
- Modify: `apps/web/components/shell/shell-header.tsx`
- Modify: `apps/web/components/shell/app-shell.test.tsx`

- [ ] **Step 1: Add sidebar state tests**

Add these tests to `apps/web/components/shell/app-shell.test.tsx`.

```tsx
it("collapses and expands the primary sidebar when no secondary sidebar is present", async () => {
  const user = userEvent.setup();
  mockPathname = "/dashboard";

  renderWithQuery(
    <AppShell>
      <h1>Dashboard content</h1>
    </AppShell>,
  );

  await expectIdentityLoaded();

  const shell = screen.getByTestId("desktop-app-shell");
  expect(shell).toHaveAttribute("data-primary-state", "expanded");
  expect(shell).toHaveAttribute("data-secondary-present", "false");

  await user.click(screen.getByRole("button", { name: "Collapse primary sidebar" }));
  expect(shell).toHaveAttribute("data-primary-state", "collapsed");
  expect(screen.getByRole("button", { name: "Expand primary sidebar" })).toBeInTheDocument();
});

it("forces primary sidebar to icon mode and toggles only secondary sidebar on procurement routes", async () => {
  const user = userEvent.setup();
  mockPathname = "/requisitions";

  renderWithQuery(
    <AppShell>
      <h1>Requisitions</h1>
    </AppShell>,
  );

  await expectIdentityLoaded();

  const shell = screen.getByTestId("desktop-app-shell");
  expect(shell).toHaveAttribute("data-primary-state", "collapsed");
  expect(shell).toHaveAttribute("data-secondary-present", "true");
  expect(shell).toHaveAttribute("data-secondary-state", "expanded");

  await user.click(screen.getByRole("button", { name: "Collapse secondary sidebar" }));
  expect(shell).toHaveAttribute("data-primary-state", "collapsed");
  expect(shell).toHaveAttribute("data-secondary-state", "collapsed");
  expect(screen.getByRole("button", { name: "Expand secondary sidebar" })).toBeInTheDocument();
});
```

- [ ] **Step 2: Run app-shell tests and confirm they fail**

Run:

```bash
pnpm --dir apps/web exec vitest run components/shell/app-shell.test.tsx
```

Expected: FAIL because the shell has not been reworked yet.

- [ ] **Step 3: Remove mobile nav from header props**

Update `apps/web/components/shell/shell-header.tsx`.

```tsx
export interface ShellHeaderProps {
  tenantName: string;
  userName: string;
  roleLabel: string;
  breadcrumbs: BreadcrumbItem[];
  sidebarToggle: React.ReactNode;
  logoutPending?: boolean;
  onLogout?: () => void;
}
```

In the component parameters, replace `mobileNav` with `sidebarToggle`, and replace `{mobileNav}` in the header with:

```tsx
<div className="shrink-0">{sidebarToggle}</div>
```

- [ ] **Step 4: Rework `AppShell` desktop layout**

Update `apps/web/components/shell/app-shell.tsx` with this structure.

```tsx
"use client";

import { useMemo, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { PanelLeft } from "lucide-react";
import { Button } from "@cognify/ui/components/button";
import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarProvider,
} from "@cognify/ui/components/sidebar";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { useLogout } from "@/features/identity/hooks/use-logout";
import { useSystemStatus } from "@/features/system-readiness/hooks/use-system-status";
import {
  getBreadcrumbs,
  getShellRouteContext,
  primaryShellNavItems,
} from "./shell-route-config";
import { formatTenantRole, formatWorkspaceLabel, getVisiblePrimaryNavItems } from "./shell-utils";
import { RightPanelHost } from "./right-panel-host";
import { ShellFooter } from "./shell-footer";
import { ShellHeader } from "./shell-header";
import { PrimaryShellNav, SecondaryShellNav } from "./shell-nav";

function ShellSidebarToggle({
  open,
  target,
  onToggle,
}: {
  open: boolean;
  target: "primary" | "secondary";
  onToggle: () => void;
}) {
  const action = open ? "Collapse" : "Expand";

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon-sm"
      aria-label={`${action} ${target} sidebar`}
      onClick={onToggle}
    >
      <PanelLeft className="h-4 w-4" aria-hidden="true" />
    </Button>
  );
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname() || "/dashboard";
  const { data } = useCurrentUser();
  const logoutMutation = useLogout();
  const context = data?.data;
  const tenantName = formatWorkspaceLabel(context?.activeTenant?.name);
  const tenantId = context?.activeTenant?.id ?? null;
  const userName = context?.user?.name ?? "Account";
  const roleLabel = formatTenantRole(context?.activeRole);
  const permissions = context?.permissions;
  const canViewSystemStatus = Boolean(permissions?.canAccessAdmin);
  const systemStatusQuery = useSystemStatus(tenantId, canViewSystemStatus);
  const [primaryOpen, setPrimaryOpen] = useState(true);
  const [secondaryOpen, setSecondaryOpen] = useState(true);
  const breadcrumbs = useMemo(() => getBreadcrumbs(pathname), [pathname]);
  const routeContext = useMemo(
    () => getShellRouteContext(pathname, permissions),
    [pathname, permissions],
  );
  const primaryItems = useMemo(
    () => (permissions ? getVisiblePrimaryNavItems(primaryShellNavItems, permissions) : []),
    [permissions],
  );
  const hasSecondarySidebar = routeContext.hasSecondarySidebar;
  const effectivePrimaryOpen = hasSecondarySidebar ? false : primaryOpen;
  const activeToggleTarget = hasSecondarySidebar ? "secondary" : "primary";
  const activeToggleOpen = hasSecondarySidebar ? secondaryOpen : primaryOpen;

  return (
    <div
      data-testid="desktop-app-shell"
      data-primary-state={effectivePrimaryOpen ? "expanded" : "collapsed"}
      data-secondary-present={hasSecondarySidebar ? "true" : "false"}
      data-secondary-state={hasSecondarySidebar ? (secondaryOpen ? "expanded" : "collapsed") : "absent"}
      className="min-h-screen bg-background text-foreground"
    >
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-foreground focus:px-3 focus:py-2 focus:text-background"
      >
        Skip to main content
      </a>
      <SidebarProvider
        open={effectivePrimaryOpen}
        onOpenChange={setPrimaryOpen}
        className="min-h-screen"
      >
        <Sidebar collapsible="icon">
          <SidebarHeader className="border-b px-2 py-3">
            <div className="flex h-8 items-center gap-2 px-2">
              <div className="flex size-7 items-center justify-center rounded-md bg-sidebar-primary text-xs font-semibold text-sidebar-primary-foreground">
                C
              </div>
              <div className="min-w-0 group-data-[collapsible=icon]:hidden">
                <div className="truncate text-sm font-semibold">Cognify</div>
                <div className="truncate text-xs text-sidebar-foreground/70">{tenantName}</div>
              </div>
            </div>
          </SidebarHeader>
          <SidebarContent className="px-2 py-2">
            <PrimaryShellNav
              items={primaryItems}
              activeArea={routeContext.primaryArea}
              pathname={pathname}
            />
          </SidebarContent>
        </Sidebar>
        {hasSecondarySidebar ? (
          <SidebarProvider
            open={secondaryOpen}
            onOpenChange={setSecondaryOpen}
            className="min-h-screen flex-1"
          >
            <Sidebar collapsible="icon">
              <SidebarHeader className="border-b px-3 py-3">
                <div className="truncate text-sm font-semibold">Procurement</div>
                <div className="truncate text-xs text-sidebar-foreground/70">Workspace navigation</div>
              </SidebarHeader>
              <SidebarContent className="px-2 py-2">
                <SecondaryShellNav groups={routeContext.secondaryGroups} pathname={pathname} />
              </SidebarContent>
            </Sidebar>
            <div className="flex min-h-screen flex-1 flex-col">
              <ShellHeader
                tenantName={tenantName}
                userName={userName}
                roleLabel={roleLabel}
                breadcrumbs={breadcrumbs}
                sidebarToggle={
                  <ShellSidebarToggle
                    open={activeToggleOpen}
                    target={activeToggleTarget}
                    onToggle={() => setSecondaryOpen((open) => !open)}
                  />
                }
                logoutPending={logoutMutation.isPending}
                onLogout={() => {
                  logoutMutation.mutate(undefined, {
                    onSuccess: () => router.replace("/login"),
                  });
                }}
              />
              <main id="main-content" className="flex-1 px-6 py-6" tabIndex={-1}>
                {children}
              </main>
              <ShellFooter
                tenantName={tenantName}
                canViewSystemStatus={canViewSystemStatus}
                readinessStatus={systemStatusQuery.data?.data.status}
              />
            </div>
          </SidebarProvider>
        ) : (
          <div className="flex min-h-screen flex-1 flex-col">
            <ShellHeader
              tenantName={tenantName}
              userName={userName}
              roleLabel={roleLabel}
              breadcrumbs={breadcrumbs}
              sidebarToggle={
                <ShellSidebarToggle
                  open={activeToggleOpen}
                  target={activeToggleTarget}
                  onToggle={() => setPrimaryOpen((open) => !open)}
                />
              }
              logoutPending={logoutMutation.isPending}
              onLogout={() => {
                logoutMutation.mutate(undefined, {
                  onSuccess: () => router.replace("/login"),
                });
              }}
            />
            <main id="main-content" className="flex-1 px-6 py-6" tabIndex={-1}>
              {children}
            </main>
            <ShellFooter
              tenantName={tenantName}
              canViewSystemStatus={canViewSystemStatus}
              readinessStatus={systemStatusQuery.data?.data.status}
            />
          </div>
        )}
        <RightPanelHost />
      </SidebarProvider>
    </div>
  );
}
```

- [ ] **Step 5: Add primary visibility helper**

In `apps/web/components/shell/shell-utils.ts`, add:

```ts
import type { ShellPrimaryNavItem } from "./shell-types";

export function getVisiblePrimaryNavItems(
  items: ShellPrimaryNavItem[],
  permissions: IdentityPermissions,
): ShellPrimaryNavItem[] {
  return items.filter((item) => (item.permission ? item.permission(permissions) : true));
}
```

Keep the existing `getVisibleNavGroups` helper for secondary groups.

- [ ] **Step 6: Run focused shell tests**

Run:

```bash
pnpm --dir apps/web exec vitest run components/shell/app-shell.test.tsx components/shell/shell-route-config.test.tsx components/shell/shell-utils.test.tsx
```

Expected: PASS after updating old assertions to use the new navigation landmark names and removing mobile drawer tests.

- [ ] **Step 7: Checkpoint commit**

Run:

```bash
git add apps/web/components/shell/app-shell.tsx apps/web/components/shell/shell-header.tsx apps/web/components/shell/shell-utils.ts apps/web/components/shell/app-shell.test.tsx
git commit -m "feat(web): add desktop two-sidebar app shell"
```

---

## Task 4: Remove Authenticated Mobile Shell Behavior

**Files:**

- Modify: `apps/web/components/shell/app-shell.test.tsx`
- Delete or leave unused: `apps/web/components/shell/mobile-shell-nav.tsx`

- [ ] **Step 1: Remove mobile drawer tests**

In `apps/web/components/shell/app-shell.test.tsx`, remove these existing tests:

```tsx
it("opens and closes mobile navigation with the keyboard", async () => {
  // remove entire test body
});

it("keeps focus and page scroll contained while mobile navigation is open", async () => {
  // remove entire test body
});
```

Also remove the assertion for the open navigation button from the landmark test:

```tsx
expect(screen.getByRole("button", { name: /open navigation/i })).toBeInTheDocument();
```

- [ ] **Step 2: Remove unused import path**

If `MobileShellNav` has no remaining imports, remove `apps/web/components/shell/mobile-shell-nav.tsx` from the repo.

Run:

```bash
rg -n "MobileShellNav|mobile-shell-nav" apps/web
```

Expected before removal: no imports after `AppShell` is updated.

If no imports remain, run:

```bash
git rm apps/web/components/shell/mobile-shell-nav.tsx
```

- [ ] **Step 3: Run shell tests**

Run:

```bash
pnpm --dir apps/web exec vitest run components/shell/app-shell.test.tsx
```

Expected: PASS.

- [ ] **Step 4: Checkpoint commit**

Run:

```bash
git add apps/web/components/shell/app-shell.test.tsx
git add -u apps/web/components/shell/mobile-shell-nav.tsx
git commit -m "refactor(web): remove authenticated mobile shell drawer"
```

---

## Task 5: Standards Documentation Alignment

**Files:**

- Modify: `docs/04-engineering/standards/shadcn-factory-ui.md`
- Modify: `docs/01-product/navigation-information-architecture.md`

- [ ] **Step 1: Add shell design-system standard**

In `docs/04-engineering/standards/shadcn-factory-ui.md`, add this section after the existing package boundary bullets:

```md
## Cognify Desktop Composition

Cognify shell, navigation, route context, page templates, and procure-to-pay layout behavior belong in `apps/web/components/shell` or another Cognify-owned app composition folder. These components may compose factory shadcn primitives, but they must not edit generated shadcn files or move product behavior into `packages/ui`.

The authenticated web shell is desktop-only. It follows the approved Cognify desktop design system spec in `docs/superpowers/specs/2026-06-08-cognify-desktop-design-system-design.md`:

- Primary-only routes render one expanded primary sidebar by default.
- Routes with secondary navigation force the primary sidebar to icon mode.
- On secondary routes, the visible collapse toggle controls only the secondary sidebar.
- Mobile web navigation is outside the authenticated Cognify web shell scope.
```

- [ ] **Step 2: Align navigation IA with the two-sidebar shell**

In `docs/01-product/navigation-information-architecture.md`, update the shell model section to state:

```md
The authenticated web shell is a desktop operational console. It uses a shallow primary product-area sidebar and may add a contextual secondary sidebar for dense module families such as Procurement, Finance, Governance, and Admin.

When a secondary sidebar is present, the primary sidebar remains an icon rail. Deep product inventory still belongs in module landing pages, saved views, command/search, record-local navigation, and contextual actions rather than the primary sidebar.
```

- [ ] **Step 3: Run documentation and shell checks**

Run:

```bash
pnpm audit:shadcn-factory-ui
pnpm --dir apps/web exec vitest run components/shell/app-shell.test.tsx components/shell/shell-route-config.test.tsx components/shell/shell-utils.test.tsx
```

Expected: PASS.

- [ ] **Step 4: Checkpoint commit**

Run:

```bash
git add docs/04-engineering/standards/shadcn-factory-ui.md docs/01-product/navigation-information-architecture.md
git commit -m "docs: align Cognify desktop design system standards"
```

---

## Final Verification

Run these checks after all tasks pass:

```bash
pnpm audit:shadcn-factory-ui
pnpm --filter @cognify/ui typecheck
pnpm --filter @cognify/web typecheck
pnpm --dir apps/web exec vitest run components/shell/app-shell.test.tsx components/shell/shell-route-config.test.tsx components/shell/shell-utils.test.tsx
```

Expected:

- Factory shadcn audit passes without new exceptions in `packages/ui`.
- UI package typecheck passes without touching shadcn primitive code.
- Web typecheck passes.
- Focused shell tests pass.

## Self-Review

Spec coverage:

- Desktop-only web scope: Task 4 removes authenticated mobile shell behavior and tests.
- Uncustomized shadcn/default theme: tasks use `@cognify/ui` primitives and do not edit `packages/ui` or `globals.css`.
- `sidebar-07`-style shell: Task 3 creates primary rail plus optional secondary sidebar with content header.
- Two-sidebar state rules: Task 3 tests forced primary icon mode and secondary-only toggle.
- Procurement secondary example: Task 1 registry and Task 2 renderer add Procurement contextual navigation.
- Dense P2P page migration: intentionally not implemented in this slice per approved spec acceptance criteria.

Placeholder scan:

- The plan intentionally contains no deferred implementation placeholders and no unspecified test commands.

Type consistency:

- `ShellRouteContext`, `ShellPrimaryNavItem`, `ShellNavGroup`, and helper names are defined before use.
- `getShellRouteContext`, `primaryShellNavItems`, `PrimaryShellNav`, `SecondaryShellNav`, and `getVisiblePrimaryNavItems` are used consistently across tasks.
