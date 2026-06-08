import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "../../tests/msw/server";
import { requesterIdentity } from "../../features/identity/mocks/identity-fixtures";
import { healthySystemStatus } from "../../features/system-readiness/mocks/system-readiness-fixtures";
import type { CurrentUserContext } from "../../features/identity/types/identity-view-model";
import { AppShell } from "./app-shell";
import DashboardLayout from "../../app/(dashboard)/layout";

let mockPathname = "/dashboard";
const router = {
  push: vi.fn(),
  replace: vi.fn(),
  refresh: vi.fn(),
  back: vi.fn(),
  forward: vi.fn(),
  prefetch: vi.fn(),
};

if (typeof window !== "undefined" && !window.ResizeObserver) {
  class ResizeObserver {
    observe() {}
    unobserve() {}
    disconnect() {}
  }

  window.ResizeObserver = ResizeObserver as unknown as typeof window.ResizeObserver;
  globalThis.ResizeObserver = ResizeObserver as unknown as typeof globalThis.ResizeObserver;
}

if (typeof window !== "undefined" && !Element.prototype.scrollIntoView) {
  Element.prototype.scrollIntoView = vi.fn();
}

vi.mock("next/navigation", () => ({
  usePathname: () => mockPathname,
  useRouter: () => router,
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
    router.replace.mockReset();
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
    expect(screen.getByRole("link", { name: "Skip to main content" })).toBeInTheDocument();
    expect(screen.getByRole("navigation", { name: "Primary product areas" })).toBeInTheDocument();
    expect(screen.getByRole("navigation", { name: "Breadcrumb" })).toHaveTextContent("Dashboard");
    expect(screen.getByRole("button", { name: /switch to .* mode/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Collapse primary sidebar" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Open command palette" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Open notifications, 2 unread" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Account menu" })).toBeEnabled();
    expect(screen.getAllByRole("main")).toHaveLength(1);
    expect(screen.getByRole("main")).toHaveAttribute("id", "main-content");
    expect(screen.getByRole("contentinfo")).toHaveTextContent("Cognify");
    expect(document.getElementById("right-panel-host")).not.toHaveAttribute("aria-hidden");
  });

  it("signs out from the shell header and returns to login", async () => {
    const user = userEvent.setup();

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    await user.click(screen.getByRole("button", { name: "Account menu" }));
    const menu = await screen.findByRole("menu");
    expect(menu).toHaveClass("bg-popover", "shadow-md");
    await user.click(within(menu).getByRole("menuitem", { name: "Sign out" }));

    expect(router.replace).toHaveBeenCalledWith("/login");
  });

  it("opens the command palette from the shell header", async () => {
    const user = userEvent.setup();

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    expect(await screen.findByRole("dialog", { name: "Command menu" })).toBeInTheDocument();
    expect(screen.getByPlaceholderText("Search or jump to...")).toHaveFocus();
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
    const primaryNav = screen.getByRole("navigation", { name: "Primary product areas" });
    expect(within(primaryNav).getByRole("link", { name: "Procurement" })).toHaveAttribute(
      "aria-current",
      "page",
    );
  });

  it("renders the future primary-only dashboard navigation layout", async () => {
    mockPathname = "/dashboard";

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    const primaryNav = screen.getByRole("navigation", { name: "Primary product areas" });
    expect(screen.queryByRole("navigation", { name: "Secondary workspace navigation" })).toBeNull();
    expect(within(primaryNav).getByRole("link", { name: "Home" })).toHaveAttribute(
      "aria-current",
      "page",
    );
    expect(screen.getByRole("button", { name: "Collapse primary sidebar" })).toBeInTheDocument();
  });

  it("renders the future procurement route with primary and secondary sidebars", async () => {
    mockPathname = "/requisitions";

    renderWithQuery(
      <AppShell>
        <h1>Requisitions workspace</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    expect(screen.getByRole("navigation", { name: "Primary product areas" })).toBeInTheDocument();
    const secondaryNav = screen.getByRole("navigation", {
      name: "Secondary workspace navigation",
    });
    expect(within(secondaryNav).getByRole("link", { name: "Requisitions" })).toHaveAttribute(
      "aria-current",
      "page",
    );
    expect(within(secondaryNav).getByText("Fulfillment")).toBeInTheDocument();
    expect(within(secondaryNav).getByRole("link", { name: "Purchase orders" })).toHaveAttribute(
      "href",
      "/purchase-orders",
    );
    expect(
      within(secondaryNav).getByRole("link", { name: "Purchase orders" }),
    ).toHaveAttribute("aria-disabled", "true");
    expect(screen.getByRole("button", { name: "Collapse secondary sidebar" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Collapse primary sidebar" })).toBeNull();
  });

  it("hides admin-only audit navigation from requester identities", async () => {
    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    expect(
      await screen.findByRole("navigation", { name: "Primary product areas" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("Audit")).not.toBeInTheDocument();
  });

  it("shows admin-only governance navigation when the identity can access admin areas", async () => {
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

    await expectIdentityLoaded();
    const primaryNav = await screen.findByRole("navigation", { name: "Primary product areas" });
    expect(within(primaryNav).getByRole("link", { name: "Governance" })).toBeInTheDocument();
    expect(within(primaryNav).getByRole("link", { name: "Admin" })).toBeInTheDocument();
  });

  it("shows system readiness and navigation in the shell for admin identities", async () => {
    mockIdentity({
      ...requesterIdentity,
      activeRole: "admin",
      tenants: [{ id: "1", name: "Acme Procurement", role: "admin" }],
      activeTenant: { id: "1", name: "Acme Procurement" },
      permissions: {
        ...requesterIdentity.permissions,
        canAccessAdmin: true,
      },
    });

    server.use(
      http.get("/api/system/status", () => {
        return HttpResponse.json(healthySystemStatus);
      }),
    );

    mockPathname = "/system";

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    expect(screen.getByRole("navigation", { name: "Breadcrumb" })).toHaveTextContent("System");
    const primaryNav = screen.getByRole("navigation", { name: "Primary product areas" });
    expect(within(primaryNav).getByRole("link", { name: "Admin" })).toHaveAttribute(
      "aria-current",
      "page",
    );
    expect(await screen.findByText(/Local demo/)).toBeInTheDocument();
  });

  it("does not fetch system status for requester identities", async () => {
    let systemStatusRequested = false;

    server.use(
      http.get("/api/system/status", () => {
        systemStatusRequested = true;
        return HttpResponse.json(healthySystemStatus);
      }),
    );

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();

    expect(systemStatusRequested).toBe(false);
    expect(screen.getByRole("contentinfo")).toHaveTextContent("Cognify");
  });

  it("falls back to a stable workspace label when the tenant name is blank", async () => {
    mockIdentity({
      ...requesterIdentity,
      activeTenant: { id: "1", name: "   " },
      tenants: [{ id: "1", name: "   ", role: "requester" }],
    });

    renderWithQuery(
      <AppShell>
        <h1>Dashboard content</h1>
      </AppShell>,
    );

    expect((await screen.findAllByText("Operational workspace")).length).toBeGreaterThan(0);
  });

  it("tracks primary sidebar state on dashboard routes", async () => {
    const user = userEvent.setup();

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

  it("tracks secondary sidebar state on procurement routes", async () => {
    const user = userEvent.setup();

    mockPathname = "/requisitions";

    renderWithQuery(
      <AppShell>
        <h1>Requisitions workspace</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    const shell = screen.getByTestId("desktop-app-shell");
    expect(shell).toHaveAttribute("data-primary-state", "collapsed");
    expect(shell).toHaveAttribute("data-secondary-present", "true");
    expect(shell).toHaveAttribute("data-secondary-state", "expanded");
    expect(screen.queryByRole("button", { name: "Collapse primary sidebar" })).toBeNull();

    await user.click(screen.getByRole("button", { name: "Collapse secondary sidebar" }));

    expect(shell).toHaveAttribute("data-primary-state", "collapsed");
    expect(shell).toHaveAttribute("data-secondary-state", "collapsed");
    expect(screen.getByRole("button", { name: "Expand secondary sidebar" })).toBeInTheDocument();
  });

  it("keeps dashboard primary state stable when the shortcut is used on secondary routes", async () => {
    mockPathname = "/requisitions";

    const view = renderWithQuery(
      <AppShell>
        <h1>Requisitions workspace</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    let shell = screen.getByTestId("desktop-app-shell");
    expect(shell).toHaveAttribute("data-primary-state", "collapsed");
    expect(shell).toHaveAttribute("data-secondary-state", "expanded");

    fireEvent.keyDown(window, { key: "b", ctrlKey: true });

    expect(shell).toHaveAttribute("data-primary-state", "collapsed");
    expect(shell).toHaveAttribute("data-secondary-state", "collapsed");

    mockPathname = "/dashboard";
    view.rerender(
      <QueryClientProvider
        client={
          new QueryClient({
            defaultOptions: {
              queries: { retry: false },
              mutations: { retry: false },
            },
          })
        }
      >
        <AppShell>
          <h1>Dashboard content</h1>
        </AppShell>
      </QueryClientProvider>,
    );

    await expect(screen.findByRole("navigation", { name: "Primary product areas" })).resolves.toBeTruthy();
    shell = screen.getByTestId("desktop-app-shell");
    expect(shell).toHaveAttribute("data-primary-state", "expanded");
    expect(shell).toHaveAttribute("data-secondary-present", "false");
  });

  it("shows procurement primary navigation for sourcing-only identities", async () => {
    mockIdentity({
      ...requesterIdentity,
      permissions: {
        ...requesterIdentity.permissions,
        canCreateRequisition: false,
        canUpdateOwnDraftRequisition: false,
        canSubmitOwnDraftRequisition: false,
        canManageSourcingIntake: true,
      },
    });
    mockPathname = "/sourcing/intake";

    renderWithQuery(
      <AppShell>
        <h1>Sourcing intake workspace</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    const primaryNav = screen.getByRole("navigation", { name: "Primary product areas" });
    expect(within(primaryNav).getByRole("link", { name: "Procurement" })).toHaveAttribute(
      "aria-current",
      "page",
    );
  });

  it("shows procurement primary navigation for quotation-only identities", async () => {
    mockIdentity({
      ...requesterIdentity,
      permissions: {
        ...requesterIdentity.permissions,
        canCreateRequisition: false,
        canUpdateOwnDraftRequisition: false,
        canSubmitOwnDraftRequisition: false,
        canReviewQuotationNormalization: true,
      },
    });
    mockPathname = "/quotations/normalizations";

    renderWithQuery(
      <AppShell>
        <h1>Quotations workspace</h1>
      </AppShell>,
    );

    await expectIdentityLoaded();
    const primaryNav = screen.getByRole("navigation", { name: "Primary product areas" });
    expect(within(primaryNav).getByRole("link", { name: "Procurement" })).toHaveAttribute(
      "aria-current",
      "page",
    );
  });
});
