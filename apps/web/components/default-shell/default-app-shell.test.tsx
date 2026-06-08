import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import DefaultLayout from "../../app/(workspace)/layout";
import { requesterIdentity } from "../../features/identity/mocks/identity-fixtures";
import type { CurrentUserContext } from "../../features/identity/types/identity-view-model";
import { server } from "../../tests/msw/server";
import { DefaultAppShell } from "./default-app-shell";

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

describe("default app shell", () => {
  beforeEach(() => {
    mockPathname = "/dashboard";
    router.replace.mockReset();
  });

  afterEach(() => {
    document.body.style.overflow = "";
  });

  it("shows a loading state before user context is available", () => {
    server.use(
      http.get("/api/me", async () => {
        await new Promise((resolve) => window.setTimeout(resolve, 100));

        return HttpResponse.json({ data: requesterIdentity });
      }),
    );

    renderWithQuery(
      <DefaultAppShell>
        <h1>Dashboard content</h1>
      </DefaultAppShell>,
    );

    expect(screen.getByRole("status")).toHaveTextContent("Loading workspace...");
    expect(screen.queryByTestId("default-app-sidebar")).not.toBeInTheDocument();
  });

  it("renders the shadcn sidebar-07 shell with an inset collapsible sidebar", async () => {
    renderWithQuery(
      <DefaultAppShell>
        <h1>Dashboard content</h1>
      </DefaultAppShell>,
    );

    await expectIdentityLoaded();
    expect(screen.getByTestId("default-app-sidebar")).toBeInTheDocument();
    const sidebar = document.querySelector('[data-slot="sidebar"][data-variant="inset"]');
    expect(sidebar).toBeInTheDocument();
    expect(sidebar).toHaveAttribute("data-collapsible", "");
    expect(screen.getAllByRole("button", { name: "Toggle Sidebar" }).length).toBeGreaterThan(0);
    expect(screen.getByRole("main")).toHaveAttribute("id", "main-content");
  });

  it("uses workspace switcher naming and keeps the active workspace in the sidebar header", async () => {
    renderWithQuery(
      <DefaultAppShell>
        <h1>Dashboard content</h1>
      </DefaultAppShell>,
    );

    await expectIdentityLoaded();
    await userEvent.click(screen.getByRole("button", { name: /Acme Procurement/ }));

    const menu = await screen.findByRole("menu");
    expect(within(menu).getByText("Workspaces")).toBeInTheDocument();
    expect(screen.queryByText("Teams")).not.toBeInTheDocument();
    expect(screen.queryByText("Add team")).not.toBeInTheDocument();
  });

  it("renders final Cognify primary navigation without disabled roadmap links", async () => {
    renderWithQuery(
      <DefaultAppShell>
        <h1>Dashboard content</h1>
      </DefaultAppShell>,
    );

    await expectIdentityLoaded();
    const primaryNav = screen.getByRole("navigation", { name: "Primary" });
    for (const label of [
      "Home",
      "My Work",
      "Procurement",
      "Vendors",
      "Finance",
      "Evidence",
      "Analytics",
      "Governance",
      "AI Assistant",
      "Admin",
      "Integrations",
    ]) {
      expect(within(primaryNav).getByText(label)).toBeInTheDocument();
    }

    expect(within(primaryNav).queryAllByRole("link", { name: "Vendors" })).toHaveLength(0);
    expect(within(primaryNav).queryAllByRole("link", { name: "Finance" })).toHaveLength(0);
  });

  it("marks the active implemented module link", async () => {
    mockPathname = "/requisitions/req-1";

    renderWithQuery(
      <DefaultAppShell>
        <h1>Requisition workspace</h1>
      </DefaultAppShell>,
    );

    await expectIdentityLoaded();
    const primaryNav = screen.getByRole("navigation", { name: "Primary" });
    expect(within(primaryNav).getByRole("link", { name: "Procurement" })).toHaveAttribute(
      "aria-current",
      "page",
    );
  });

  it("retains the sidebar-07 user navigation pattern and logs out through it", async () => {
    const user = userEvent.setup();

    renderWithQuery(
      <DefaultAppShell>
        <h1>Dashboard content</h1>
      </DefaultAppShell>,
    );

    await expectIdentityLoaded();
    await user.click(screen.getByRole("button", { name: /Test User/ }));
    const menu = await screen.findByRole("menu");
    await user.click(within(menu).getByRole("menuitem", { name: "Log out" }));

    expect(router.replace).toHaveBeenCalledWith("/login");
  });

  it("protects all default app routes with the same shell layout", async () => {
    renderWithQuery(
      <DefaultLayout>
        <h1>Protected app content</h1>
      </DefaultLayout>,
    );

    await expectIdentityLoaded();
    expect(screen.getByRole("heading", { name: "Protected app content" })).toBeInTheDocument();
    expect(screen.getByTestId("default-app-sidebar")).toBeInTheDocument();
  });

  it("falls back to a stable workspace label when the active tenant is blank", async () => {
    mockIdentity({
      ...requesterIdentity,
      activeTenant: { id: "1", name: "   " },
      tenants: [{ id: "1", name: "   ", role: "requester" }],
    });

    renderWithQuery(
      <DefaultAppShell>
        <h1>Dashboard content</h1>
      </DefaultAppShell>,
    );

    expect((await screen.findAllByText("Operational workspace")).length).toBeGreaterThan(0);
  });
});
