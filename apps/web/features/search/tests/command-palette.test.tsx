import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { CommandPaletteHost } from "../../../components/shell/command-palette-host";
import { server } from "../../../tests/msw/server";
import { rememberRecentRecord } from "../hooks/use-recent-records";

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

const router = vi.hoisted(() => ({
  push: vi.fn(),
  replace: vi.fn(),
  refresh: vi.fn(),
  back: vi.fn(),
  forward: vi.fn(),
  prefetch: vi.fn(),
}));

vi.mock("next/navigation", () => ({
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

describe("command palette", () => {
  beforeEach(() => {
    router.push.mockReset();
    window.localStorage.clear();
    window.sessionStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "tenant-1");
  });

  it("opens from the shell button and shows local commands immediately", async () => {
    const user = userEvent.setup();

    renderWithQuery(<CommandPaletteHost />);

    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    expect(await screen.findByRole("dialog", { name: "Command menu" })).toBeInTheDocument();
    expect(screen.getByPlaceholderText("Search or jump to...")).toHaveFocus();
    expect(screen.getByRole("option", { name: /Open requisitions/ })).toBeInTheDocument();
  });

  it("opens with Control+K", async () => {
    renderWithQuery(<CommandPaletteHost />);

    fireEvent.keyDown(window, { key: "k", ctrlKey: true });

    expect(await screen.findByRole("dialog", { name: "Command menu" })).toBeInTheDocument();
  });

  it("opens with Command+K", async () => {
    renderWithQuery(<CommandPaletteHost />);

    fireEvent.keyDown(window, { key: "k", metaKey: true });

    expect(await screen.findByRole("dialog", { name: "Command menu" })).toBeInTheDocument();
  });

  it("closes when Escape is pressed", async () => {
    const user = userEvent.setup();

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    expect(await screen.findByRole("dialog", { name: "Command menu" })).toBeInTheDocument();

    await user.keyboard("{Escape}");

    await waitFor(() => {
      expect(screen.queryByRole("dialog", { name: "Command menu" })).not.toBeInTheDocument();
    });
  });

  it("navigates to create requisition from the command list", async () => {
    const user = userEvent.setup();

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    const createRequisition = await screen.findByRole("option", { name: /Create requisition/ });
    await user.click(createRequisition);

    expect(router.push).toHaveBeenCalledWith("/requisitions/new");
    await waitFor(() => {
      expect(screen.queryByRole("dialog", { name: "Command menu" })).not.toBeInTheDocument();
    });
  });

  it("renders remote requisition search results after a debounced query", async () => {
    const user = userEvent.setup();

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    const input = screen.getByPlaceholderText("Search or jump to...");
    await user.type(input, "fit-out");

    expect(await screen.findByRole("option", { name: /Office fit-out procurement/ })).toBeInTheDocument();
    expect(screen.getByText("REQ-2026-000001")).toBeInTheDocument();
  });

  it("renders roadmap preview search results", async () => {
    const user = userEvent.setup();

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    const input = screen.getByPlaceholderText("Search or jump to...");
    await user.type(input, "Atlas");

    const previewResult = await screen.findByRole("option", { name: /Atlas Office Supplies/ });
    expect(previewResult).toBeInTheDocument();
    expect(screen.getByText("Office supplies")).toBeInTheDocument();

    await user.click(previewResult);
    expect(router.push).toHaveBeenCalledWith("/system");
  });

  it("shows an explicit loading state while remote search is pending", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/search", async () => {
        await new Promise((resolve) => window.setTimeout(resolve, 500));

        return HttpResponse.json({
          data: [],
          meta: {
            query: "fit-out",
            limit: 10,
            returned: 0,
          },
        });
      }),
    );

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    await user.type(screen.getByPlaceholderText("Search or jump to..."), "office");

    expect(await screen.findByRole("status", { name: "Searching requisitions" })).toHaveTextContent(
      "Searching requisitions...",
    );
  });

  it("navigates to a remote requisition result with keyboard selection", async () => {
    const user = userEvent.setup();

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    const input = screen.getByPlaceholderText("Search or jump to...");
    await user.type(input, "fit-out");
    expect(await screen.findByRole("option", { name: /Office fit-out procurement/ })).toBeInTheDocument();

    await user.keyboard("{ArrowDown}{Enter}");

    expect(router.push).toHaveBeenCalledWith("/requisitions/req-1");
    await waitFor(() => {
      expect(screen.queryByRole("dialog", { name: "Command menu" })).not.toBeInTheDocument();
    });
  });

  it("shows an empty state when a remote search has no matching records", async () => {
    const user = userEvent.setup();

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    await user.type(screen.getByPlaceholderText("Search or jump to..."), "zzzz");

    expect(await screen.findByText("No matching commands or requisitions.")).toBeInTheDocument();
    expect(screen.queryByRole("option")).not.toBeInTheDocument();
  });

  it("shows an error state without leaving stale search results visible", async () => {
    const user = userEvent.setup();

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    const input = screen.getByPlaceholderText("Search or jump to...");
    await user.type(input, "office");
    expect(await screen.findByText("REQ-2026-000001")).toBeInTheDocument();

    server.use(
      http.get("/api/search", () => {
        return HttpResponse.json(
          {
            error: {
              code: "server_error",
              message: "Search failed.",
              details: {},
              requestId: null,
            },
          },
          { status: 500 },
        );
      }),
    );

    await user.clear(input);
    await user.type(input, "error");

    expect(await screen.findByRole("alert")).toHaveTextContent("Search failed.");
    expect(screen.queryByText("REQ-2026-000001")).not.toBeInTheDocument();
  });

  it("shows recent records from session storage", async () => {
    const user = userEvent.setup();

    rememberRecentRecord({
      type: "requisition",
      id: "req-2",
      title: "Warehouse packing supplies",
      subtitle: "REQ-2026-000002",
      status: "submitted",
      href: "/requisitions/req-2",
      updatedAt: "2026-05-09T06:45:00.000Z",
    });

    renderWithQuery(<CommandPaletteHost />);
    await user.click(screen.getByRole("button", { name: "Open command palette" }));

    expect(await screen.findByRole("option", { name: /Warehouse packing supplies/ })).toBeInTheDocument();
  });
});
