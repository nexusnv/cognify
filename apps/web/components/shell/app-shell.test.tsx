import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "../../tests/msw/server";
import { requesterIdentity } from "../../features/identity/mocks/identity-fixtures";
import type { CurrentUserContext } from "../../features/identity/types/identity-view-model";
import { AppShell } from "./app-shell";
import DashboardLayout from "../../app/(dashboard)/layout";
import { getBreadcrumbs, shellNavGroups } from "./shell-route-config";
import { formatTenantRole, getVisibleNavGroups, isActivePath } from "./shell-utils";
import { ShellFooter } from "./shell-footer";

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

async function expectIdentityLoaded() {
  expect((await screen.findAllByText("Acme Procurement")).length).toBeGreaterThan(0);
}

describe("app shell", () => {
  beforeEach(() => {
    mockPathname = "/dashboard";
  });

  afterEach(() => {
    document.body.style.overflow = "";
  });

  it("renders tenant, user, role, landmarks, breadcrumbs, and shell hosts", async () => {
    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    expect(screen.getByText("Test User")).toBeInTheDocument();
    expect(screen.getByText("Requester")).toBeInTheDocument();
    expect(screen.getByRole("navigation", { name: "Primary" })).toBeInTheDocument();
    expect(screen.getByRole("navigation", { name: "Breadcrumb" })).toHaveTextContent("Dashboard");
    expect(screen.getByRole("button", { name: "Open command palette" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Open notifications" })).toBeDisabled();
    expect(screen.getByRole("main")).toHaveAttribute("id", "main-content");
    expect(screen.getByRole("contentinfo")).toHaveTextContent("Cognify");
    expect(document.getElementById("right-panel-host")).not.toHaveAttribute("aria-hidden");
  });

  it("protects dashboard routes with the operational shell", async () => {
    renderWithQuery(
      <DashboardLayout>
        <h1>Dashboard content</h1>
      </DashboardLayout>,
    );

    await expectIdentityLoaded();
    expect(screen.getByRole("heading", { name: "Dashboard content" })).toBeInTheDocument();
  });

  it("marks the active navigation item without relying on color alone", async () => {
    mockPathname = "/requisitions/req-1";

    renderWithQuery(
      <AppShell>
        <h1>Requisition workspace</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    const primaryNav = screen.getByRole("navigation", { name: "Primary" });
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

    const auditNavItem = await screen.findByRole("link", { name: "Audit" });
    expect(auditNavItem).toHaveAttribute("aria-disabled", "true");
    expect(auditNavItem).toHaveAttribute("tabindex", "-1");
  });

  it("opens and closes mobile navigation with the keyboard", async () => {
    const user = userEvent.setup();

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    const openButton = screen.getByRole("button", { name: "Open navigation" });
    openButton.focus();

    await user.click(openButton);
    const dialog = screen.getByRole("dialog", { name: "Navigation" });
    expect(dialog).toHaveAttribute("aria-modal", "true");
    expect(within(dialog).getByRole("button", { name: "Close navigation" })).toHaveFocus();

    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog", { name: "Navigation" })).not.toBeInTheDocument();
    expect(openButton).toHaveFocus();
  });

  it("keeps focus and page scroll contained while mobile navigation is open", async () => {
    const user = userEvent.setup();
    const previousOverflow = document.body.style.overflow;

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    await user.click(screen.getByRole("button", { name: "Open navigation" }));

    const dialog = screen.getByRole("dialog", { name: "Navigation" });
    expect(document.body.style.overflow).toBe("hidden");

    const closeButton = within(dialog).getByRole("button", { name: "Close navigation" });
    const links = within(dialog).getAllByRole("link");
    links[links.length - 1].focus();

    await user.tab();
    expect(closeButton).toHaveFocus();

    await user.keyboard("{Escape}");
    expect(document.body.style.overflow).toBe(previousOverflow);
  });
});

describe("shell footer", () => {
  it("renders a meaningful workspace fallback for empty tenant labels", () => {
    render(<ShellFooter tenantName="" />);

    expect(screen.getByRole("contentinfo")).toHaveTextContent("Workspace: Operational workspace");
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

  it("normalizes trailing slashes before resolving breadcrumbs", () => {
    expect(getBreadcrumbs("/requisitions/")).toEqual([{ label: "Requisitions" }]);
    expect(getBreadcrumbs("/dashboard/")).toEqual([{ label: "Dashboard" }]);
  });

  it("computes active paths for nested operational routes", () => {
    expect(isActivePath("/requisitions", "/requisitions/req-1")).toBe(true);
    expect(isActivePath("/dashboard", "/dashboard")).toBe(true);
    expect(isActivePath("/dashboard", "/dashboarding")).toBe(false);
  });

  it("formats role labels consistently", () => {
    // Covers unexpected legacy role casing defensively; generated role types are lowercase today.
    expect(formatTenantRole("TENANT_ADMIN" as unknown as Parameters<typeof formatTenantRole>[0])).toBe(
      "Tenant Admin",
    );
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
