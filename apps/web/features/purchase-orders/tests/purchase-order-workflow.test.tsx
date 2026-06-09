import { http, HttpResponse, delay } from "msw";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "@/tests/msw/server";
import { purchaseOrderFixture } from "../mocks/purchase-order-fixtures";
import { resetPurchaseOrderMockState } from "../mocks/purchase-order-handlers";
import { PurchaseOrderListPage } from "../workflows/purchase-order-list-page";
import { PurchaseOrderWorkspacePage } from "../workflows/purchase-order-workspace-page";

function renderWithProviders(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
  resetPurchaseOrderMockState();
  window.localStorage.clear();
  window.localStorage.setItem("cognify.activeTenantId", "1");
});

describe("purchase order workflow", () => {
  it("renders the purchase order list loading state", async () => {
    server.use(
      http.get("/api/purchase-orders", async () => {
        await delay(100);
        return HttpResponse.json({ data: [], meta: { currentPage: 1, perPage: 15, total: 0, lastPage: 1 } });
      }),
    );

    renderWithProviders(<PurchaseOrderListPage />);

    expect(screen.getByText("Loading purchase orders")).toBeInTheDocument();
    expect(await screen.findByText("Purchase orders created from approved handoffs will appear here.")).toBeInTheDocument();
  });

  it("renders the purchase order list error state", async () => {
    server.use(
      http.get("/api/purchase-orders", () =>
        HttpResponse.json({ error: { code: "server_error", message: "Unavailable" } }, { status: 500 }),
      ),
    );

    renderWithProviders(<PurchaseOrderListPage />);

    expect(await screen.findByText("Purchase orders could not be loaded.")).toBeInTheDocument();
  });

  it("renders the purchase order list empty state", async () => {
    server.use(
      http.get("/api/purchase-orders", () =>
        HttpResponse.json({ data: [], meta: { currentPage: 1, perPage: 15, total: 0, lastPage: 1 } }),
      ),
    );

    renderWithProviders(<PurchaseOrderListPage />);

    expect(await screen.findByText("Purchase orders created from approved handoffs will appear here.")).toBeInTheDocument();
  });

  it("renders the purchase order list", async () => {
    renderWithProviders(<PurchaseOrderListPage />);

    expect(await screen.findByRole("heading", { name: "Purchase orders" })).toBeInTheDocument();
    expect(await screen.findByText("Northwind Traders")).toBeInTheDocument();
    expect(await screen.findByRole("link", { name: /PO-2026-000001/ })).toHaveAttribute(
      "href",
      "/purchase-orders/po-1",
    );
  });

  it("renders the purchase order workspace loading state", async () => {
    server.use(
      http.get("/api/purchase-orders/:purchaseOrder", async () => {
        await delay(100);
        return HttpResponse.json({ data: purchaseOrderFixture });
      }),
    );

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    expect(screen.getByText("Loading purchase order workspace")).toBeInTheDocument();
    expect(await screen.findByRole("heading", { name: "PO-2026-000001" })).toBeInTheDocument();
  });

  it("renders the purchase order workspace error state", async () => {
    server.use(
      http.get("/api/purchase-orders/:purchaseOrder", () =>
        HttpResponse.json({ error: { code: "server_error", message: "Unavailable" } }, { status: 500 }),
      ),
    );

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    expect(await screen.findByText("Purchase order could not be loaded.")).toBeInTheDocument();
  });

  it("renders the purchase order workspace and ready action", async () => {
    const user = userEvent.setup();
    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    expect(await screen.findByRole("heading", { name: "PO-2026-000001" })).toBeInTheDocument();
    expect(screen.getByText("Northwind Traders")).toBeInTheDocument();
    expect(screen.getByRole("table", { name: "Purchase order lines" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Mark ready for review" })).toBeEnabled();

    await user.click(screen.getByRole("button", { name: "Mark ready for review" }));

    await waitFor(() => {
      expect(screen.getByRole("button", { name: "Mark ready for review" })).toBeDisabled();
    });
    await waitFor(() => {
      expect(screen.getByRole("button", { name: "Save draft" })).toBeDisabled();
    });
    expect(screen.getByRole("group", { name: "Purchase order draft fields" })).toBeDisabled();
  });

  it("renders and submits the cancel action when allowed", async () => {
    const user = userEvent.setup();
    const prompt = vi.spyOn(window, "prompt").mockReturnValue("Duplicate draft");

    try {
      renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

      expect(await screen.findByRole("button", { name: "Cancel" })).toBeEnabled();

      await user.click(screen.getByRole("button", { name: "Cancel" }));

      expect(prompt).toHaveBeenCalledWith("Cancellation reason");
      await waitFor(() => {
        expect(screen.queryByRole("button", { name: "Cancel" })).not.toBeInTheDocument();
      });
      await waitFor(() => {
        expect(screen.getAllByText("cancelled").length).toBeGreaterThan(0);
      });
    } finally {
      prompt.mockRestore();
    }
  });
});
