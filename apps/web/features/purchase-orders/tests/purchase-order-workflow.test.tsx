import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it } from "vitest";
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
  it("renders the purchase order list", async () => {
    renderWithProviders(<PurchaseOrderListPage />);

    expect(await screen.findByRole("heading", { name: "Purchase orders" })).toBeInTheDocument();
    expect(await screen.findByText("Northwind Traders")).toBeInTheDocument();
    expect(await screen.findByRole("link", { name: /PO-2026-000001/ })).toHaveAttribute(
      "href",
      "/purchase-orders/po-1",
    );
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
});
