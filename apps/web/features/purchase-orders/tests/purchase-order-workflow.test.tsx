import { http, HttpResponse, delay } from "msw";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { cleanup, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "@/tests/msw/server";
import {
  acknowledgedPurchaseOrderFixture,
  approvedPurchaseOrderFixture,
  changesRequestedPurchaseOrderFixture,
  inReviewPurchaseOrderFixture,
  issuedPurchaseOrderFixture,
  purchaseOrderFixture,
  readyPurchaseOrderFixture,
  rejectedPurchaseOrderFixture,
} from "../mocks/purchase-order-fixtures";
import { resetPurchaseOrderMockState, setPurchaseOrderMockState } from "../mocks/purchase-order-handlers";
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
      expect(screen.queryByRole("button", { name: "Mark ready for review" })).not.toBeInTheDocument();
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

  it("submits a ready purchase order for approval", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([readyPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    expect(await screen.findByRole("region", { name: "Purchase order approval" })).toBeInTheDocument();
    expect(screen.getByText("Ready for review")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Submit for approval" })).toBeEnabled();

    await user.click(screen.getByRole("button", { name: "Submit for approval" }));

    await waitFor(() => {
      expect(screen.getByText("In review")).toBeInTheDocument();
    });
    expect(screen.getByRole("group", { name: "Purchase order draft fields" })).toBeDisabled();
  });

  it("shows submit approval conflicts inline", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([readyPurchaseOrderFixture]);
    server.use(
      http.post("/api/purchase-orders/:purchaseOrder/submit-approval", () =>
        HttpResponse.json(
          { error: { code: "invalid_state", message: "The purchase order has changed. Reload and try again." } },
          { status: 409 },
        ),
      ),
    );

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    await user.click(await screen.findByRole("button", { name: "Submit for approval" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("The purchase order has changed. Reload and try again.");
  });

  it("locks draft fields while purchase order is in review", async () => {
    setPurchaseOrderMockState([inReviewPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    expect(await screen.findByText("In review")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Submit for approval" })).not.toBeInTheDocument();
    expect(screen.getByRole("group", { name: "Purchase order draft fields" })).toBeDisabled();
  });

  it("shows changes requested reason and keeps fields editable", async () => {
    setPurchaseOrderMockState([changesRequestedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const approvalRegion = await screen.findByRole("region", { name: "Purchase order approval" });
    expect(within(approvalRegion).getAllByText("Changes requested").length).toBeGreaterThan(0);
    expect(screen.getByText("Payment terms and tax amount require correction.")).toBeInTheDocument();
    expect(screen.getByText("Fields: taxAmount, paymentTerms")).toBeInTheDocument();
    expect(screen.getByRole("group", { name: "Purchase order draft fields" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Submit for approval" })).toBeEnabled();
  });

  it("shows approved and rejected review outcomes", async () => {
    setPurchaseOrderMockState([approvedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    expect(await screen.findByText("Approved")).toBeInTheDocument();
    expect(screen.getByText("This purchase order is approved for supplier issue.")).toBeInTheDocument();

    cleanup();
    setPurchaseOrderMockState([rejectedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const rejectedApprovalRegion = await screen.findByRole("region", { name: "Purchase order approval" });
    expect(within(rejectedApprovalRegion).getAllByText("Rejected").length).toBeGreaterThan(0);
    expect(screen.getByText("Tax coding does not match the approved quotation.")).toBeInTheDocument();
  });

  it("issues an approved purchase order to a supplier", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([approvedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const supplierRegion = await screen.findByRole("region", { name: "Supplier issue" });
    expect(within(supplierRegion).getByRole("button", { name: "Issue to supplier" })).toBeEnabled();

    await user.clear(within(supplierRegion).getByLabelText("Supplier contact"));
    await user.type(within(supplierRegion).getByLabelText("Supplier contact"), "Priya Supplier");
    await user.clear(within(supplierRegion).getByLabelText("Supplier email"));
    await user.type(within(supplierRegion).getByLabelText("Supplier email"), "priya.supplier@example.com");
    await user.click(within(supplierRegion).getByRole("button", { name: "Issue to supplier" }));

    expect(await screen.findByText("Issued to supplier")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Record acknowledgement" })).toBeEnabled();
  });

  it("shows export and acknowledgement controls for issued purchase orders", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([issuedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const approvalRegion = await screen.findByRole("region", { name: "Purchase order approval" });
    expect(within(approvalRegion).getByText("Approved")).toBeInTheDocument();
    expect(within(approvalRegion).queryByText("Draft")).not.toBeInTheDocument();

    const supplierRegion = await screen.findByRole("region", { name: "Supplier issue" });
    expect(within(supplierRegion).getByText("Issued to supplier")).toBeInTheDocument();
    expect(within(supplierRegion).getByRole("button", { name: "Preview JSON" })).toBeEnabled();
    expect(within(supplierRegion).getByRole("button", { name: "Record JSON export" })).toBeEnabled();
    expect(within(supplierRegion).getByRole("button", { name: "Record acknowledgement" })).toBeEnabled();

    await user.click(within(supplierRegion).getByRole("button", { name: "Preview JSON" }));

    expect(await screen.findByText("Prepared supplier export for PO-2026-000001.")).toBeInTheDocument();
  });

  it("records supplier acknowledgement", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([issuedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const supplierRegion = await screen.findByRole("region", { name: "Supplier issue" });
    await user.type(within(supplierRegion).getByLabelText("Acknowledgement reference"), "ACK-PO-100");
    await user.type(within(supplierRegion).getByLabelText("Acknowledgement note"), "Supplier confirmed delivery in week 29.");
    await user.click(within(supplierRegion).getByRole("button", { name: "Record acknowledgement" }));

    expect(await screen.findByText("Supplier acknowledged")).toBeInTheDocument();
    expect(screen.getByText(/ACK-PO-100/)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Record acknowledgement" })).not.toBeInTheDocument();
  });

  it("rejects supplier acknowledgement without evidence", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([issuedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const supplierRegion = await screen.findByRole("region", { name: "Supplier issue" });
    await user.clear(within(supplierRegion).getByLabelText("Acknowledged contact"));
    await user.clear(within(supplierRegion).getByLabelText("Acknowledgement reference"));
    await user.clear(within(supplierRegion).getByLabelText("Acknowledgement note"));
    await user.click(within(supplierRegion).getByRole("button", { name: "Record acknowledgement" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/evidence.*required/i);
    expect(within(supplierRegion).getByRole("button", { name: "Record acknowledgement" })).toBeInTheDocument();
  });

  it("shows acknowledged supplier issue facts", async () => {
    setPurchaseOrderMockState([acknowledgedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const approvalRegion = await screen.findByRole("region", { name: "Purchase order approval" });
    expect(within(approvalRegion).getByText("Approved")).toBeInTheDocument();
    expect(within(approvalRegion).queryByText("Draft")).not.toBeInTheDocument();

    const supplierRegion = await screen.findByRole("region", { name: "Supplier issue" });
    expect(within(supplierRegion).getByText("Supplier acknowledged")).toBeInTheDocument();
    expect(within(supplierRegion).getByText(/ACK-PO-100/)).toBeInTheDocument();
    expect(within(supplierRegion).queryByRole("button", { name: "Record acknowledgement" })).not.toBeInTheDocument();
  });

  it("blocks supplier issue before approval", async () => {
    setPurchaseOrderMockState([readyPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const supplierRegion = await screen.findByRole("region", { name: "Supplier issue" });
    expect(within(supplierRegion).getByText("Supplier issue unlocks after approval.")).toBeInTheDocument();
    expect(within(supplierRegion).queryByRole("button", { name: "Issue to supplier" })).not.toBeInTheDocument();
  });
});
