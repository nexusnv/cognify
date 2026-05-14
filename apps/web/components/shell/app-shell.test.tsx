import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
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
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
    refresh: vi.fn(),
    back: vi.fn(),
    forward: vi.fn(),
    prefetch: vi.fn(),
  }),
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
    expect(screen.getByRole("button", { name: "Open notifications, 2 unread" })).toBeEnabled();
    expect(screen.getByRole("main")).toHaveAttribute("id", "main-content");
    expect(screen.getByRole("contentinfo")).toHaveTextContent("Cognify");
    expect(document.getElementById("right-panel-host")).not.toHaveAttribute("aria-hidden");
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
    expect(
      await screen.findByRole("link", { name: "System" }),
    ).toHaveAttribute("aria-current", "page");
    expect(screen.getByRole("contentinfo")).toHaveTextContent("Local demo");
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
