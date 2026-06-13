import { http, HttpResponse, delay } from "msw";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { cleanup, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "@/tests/msw/server";
import {
  buildPurchaseOrderFixture,
  acknowledgedPurchaseOrderFixture,
  approvedPurchaseOrderFixture,
  changesRequestedPurchaseOrderFixture,
  appliedChangeOrderFixture,
  inReviewPurchaseOrderFixture,
  issuedPurchaseOrderFixture,
  issuedPurchaseOrderWithAppliedChangeOrderFixture,
  issuedPurchaseOrderWithPendingChangeOrderFixture,
  pendingPurchaseOrderChangeOrderFixture,
  purchaseOrderFixture,
  readyPurchaseOrderFixture,
  rejectedPurchaseOrderFixture,
} from "../mocks/purchase-order-fixtures";
import {
  resetPurchaseOrderMockState,
  setPurchaseOrderChangeOrdersMockState,
  setPurchaseOrderMockState,
} from "../mocks/purchase-order-handlers";
import { resetFulfillmentMockState } from "../mocks/purchase-order-fulfillment-handlers";
import { recordGoodsReceipt } from "../api/purchase-order-goods-receipt-api";
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
  resetFulfillmentMockState();
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
    expect(screen.queryByRole("region", { name: "Purchase order change orders" })).not.toBeInTheDocument();
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

  it("creates a purchase order change order and shows it in the workspace", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([issuedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    await screen.findByRole("button", { name: "Create change order" });
    const changeOrderRegion = screen.getByRole("region", { name: "Purchase order change orders" });
    await user.selectOptions(within(changeOrderRegion).getByLabelText("Change type"), "amendment");
    await user.clear(within(changeOrderRegion).getByLabelText("Reason"));
    await user.type(within(changeOrderRegion).getByLabelText("Reason"), "Adjust payment terms for the supplier.");
    await user.clear(within(changeOrderRegion).getByLabelText("Payment terms"));
    await user.type(within(changeOrderRegion).getByLabelText("Payment terms"), "Net 45");
    await user.clear(within(changeOrderRegion).getByLabelText("Pallet rack bay quantity"));
    await user.type(within(changeOrderRegion).getByLabelText("Pallet rack bay quantity"), "8.0000");
    await user.clear(within(changeOrderRegion).getByLabelText("Pallet rack bay unit price"));
    await user.type(within(changeOrderRegion).getByLabelText("Pallet rack bay unit price"), "12500.0000");
    await user.click(within(changeOrderRegion).getByRole("button", { name: "Create change order" }));

    await waitFor(
      () => {
        expect(screen.getByText("CO-PO-2026-000001-001", { selector: "td" })).toBeInTheDocument();
      },
      { timeout: 3000 },
    );
    expect(screen.getByText("Adjust payment terms for the supplier.", { selector: "td" })).toBeInTheDocument();
    expect(screen.getByLabelText("Pallet rack bay quantity")).toHaveValue("8.0000");
    expect(screen.getByLabelText("Pallet rack bay unit price")).toHaveValue("12500.0000");
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

  it("renders fulfillment tracking for issued purchase orders and records a shipment", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([issuedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const fulfillmentRegion = await screen.findByRole("region", { name: "Fulfillment tracking" });
    expect(within(fulfillmentRegion).getByText("pending shipment")).toBeInTheDocument();
    expect(await within(fulfillmentRegion).findByText("0 shipment(s) recorded")).toBeInTheDocument();

    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Record shipment" }));
    await user.type(within(fulfillmentRegion).getByLabelText("Carrier"), "DHL Supply Chain");
    await user.type(within(fulfillmentRegion).getByLabelText("Tracking reference"), "TRACK-001");
    await user.clear(within(fulfillmentRegion).getByLabelText("Line 1 quantity shipped"));
    await user.type(within(fulfillmentRegion).getByLabelText("Line 1 quantity shipped"), "4.0000");
    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Save shipment" }));

    await waitFor(() => {
      expect(within(fulfillmentRegion).getByText("SH-2026-000001")).toBeInTheDocument();
    });
    expect(within(fulfillmentRegion).getByText("awaiting delivery")).toBeInTheDocument();
    expect(within(fulfillmentRegion).getByText("1 shipment(s) recorded")).toBeInTheDocument();
  });

  it("shows line-by-line shipment inputs for every purchase order line", async () => {
    const twoLineIssuedPurchaseOrderFixture = buildPurchaseOrderFixture({
      status: "issued",
      lockVersion: 2,
      permissions: {
        canUpdate: false,
        canMarkReadyForReview: false,
        canCancel: false,
        canSubmitForApproval: false,
        canIssueToSupplier: false,
        canExportSupplierVersion: false,
        canAcknowledgeSupplier: false,
        canCreateChangeOrder: true,
        canUpdateChangeOrder: false,
        canSubmitChangeOrder: false,
        canCancelChangeOrder: false,
        canCreateShipment: true,
        canRecordGoodsReceipt: true,
        canConfirmGoodsReceipt: true,
      },
      lines: [
        {
          id: "po-line-1",
          lineNumber: 1,
          status: "open",
          currentVersionNumber: 1,
          cancelledByChangeOrderId: null,
          cancelledAt: null,
          cancelledReason: null,
          description: "Pallet rack bay",
          unit: "each",
          quantity: "10.0000",
          unitPrice: "12000.00",
          subtotalAmount: "120000.00",
          totalAmount: "120000.00",
          currency: "MYR",
          source: {},
          cumulativeQuantityReceived: "0.0000",
          cumulativeQuantityAccepted: "0.0000",
          overReceiptTolerancePercent: "0.00",
          lastReceiptAt: null,
        },
        {
          id: "po-line-2",
          lineNumber: 2,
          status: "open",
          currentVersionNumber: 1,
          cancelledByChangeOrderId: null,
          cancelledAt: null,
          cancelledReason: null,
          description: "Racking accessories",
          unit: "set",
          quantity: "4.0000",
          unitPrice: "3500.00",
          subtotalAmount: "14000.00",
          totalAmount: "14000.00",
          currency: "MYR",
          source: {},
          cumulativeQuantityReceived: "0.0000",
          cumulativeQuantityAccepted: "0.0000",
          overReceiptTolerancePercent: "0.00",
          lastReceiptAt: null,
        },
      ],
    });
    setPurchaseOrderMockState([twoLineIssuedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const fulfillmentRegion = await screen.findByRole("region", { name: "Fulfillment tracking" });
    await userEvent.setup().click(within(fulfillmentRegion).getByRole("button", { name: "Record shipment" }));

    expect(within(fulfillmentRegion).getByLabelText("Line 1 quantity shipped")).toBeInTheDocument();
    expect(within(fulfillmentRegion).getByLabelText("Line 1 backorder quantity")).toBeInTheDocument();
    expect(within(fulfillmentRegion).getByLabelText("Line 2 quantity shipped")).toBeInTheDocument();
    expect(within(fulfillmentRegion).getByLabelText("Line 2 backorder quantity")).toBeInTheDocument();
  });

  it("returns sequential goods receipt line numbers from the mock API", async () => {
    const receipt = await recordGoodsReceipt("po-1", {
      lockVersion: 1,
      receiptDate: "2026-06-13",
      lines: [
        {
          purchaseOrderLineId: "po-line-1",
          quantityReceived: "1.0000",
        },
        {
          purchaseOrderLineId: "po-line-2",
          quantityReceived: "2.0000",
        },
      ],
    });

    expect(receipt.lines.map((line) => line.lineNumber)).toEqual([1, 2]);
  });

  it("adds a tracking event to an existing shipment from the fulfillment panel", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([issuedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const fulfillmentRegion = await screen.findByRole("region", { name: "Fulfillment tracking" });

    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Record shipment" }));
    await user.type(within(fulfillmentRegion).getByLabelText("Carrier"), "DHL Supply Chain");
    await user.type(within(fulfillmentRegion).getByLabelText("Tracking reference"), "TRACK-001");
    await user.clear(within(fulfillmentRegion).getByLabelText("Line 1 quantity shipped"));
    await user.type(within(fulfillmentRegion).getByLabelText("Line 1 quantity shipped"), "4.0000");
    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Save shipment" }));

    await waitFor(() => {
      expect(within(fulfillmentRegion).getByText("SH-2026-000001")).toBeInTheDocument();
    });

    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Add tracking event" }));
    await user.selectOptions(within(fulfillmentRegion).getByLabelText("Tracking status"), "in_transit");
    await user.type(within(fulfillmentRegion).getByLabelText("Tracking location"), "Port Klang");
    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Save tracking event" }));

    expect(within(fulfillmentRegion).getByText("in transit", { selector: "span.rounded-full" })).toBeInTheDocument();
    expect(within(fulfillmentRegion).getByText("Port Klang")).toBeInTheDocument();
  });

  it("updates backorder details for a shipment line from the fulfillment panel", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([issuedPurchaseOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const fulfillmentRegion = await screen.findByRole("region", { name: "Fulfillment tracking" });

    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Record shipment" }));
    await user.type(within(fulfillmentRegion).getByLabelText("Carrier"), "DHL Supply Chain");
    await user.type(within(fulfillmentRegion).getByLabelText("Tracking reference"), "TRACK-001");
    await user.clear(within(fulfillmentRegion).getByLabelText("Line 1 quantity shipped"));
    await user.type(within(fulfillmentRegion).getByLabelText("Line 1 quantity shipped"), "4.0000");
    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Save shipment" }));

    await waitFor(() => {
      expect(within(fulfillmentRegion).getByText("SH-2026-000001")).toBeInTheDocument();
    });

    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Update backorder" }));
    await user.clear(within(fulfillmentRegion).getByLabelText("Line 1 backorder quantity"));
    await user.type(within(fulfillmentRegion).getByLabelText("Line 1 backorder quantity"), "2.0000");
    await user.type(within(fulfillmentRegion).getByLabelText("Line 1 backorder expected at"), "2026-07-20");
    await user.click(within(fulfillmentRegion).getByRole("button", { name: "Save backorder" }));

    expect(await within(fulfillmentRegion).findByText(/Backorder:\s*2/)).toBeInTheDocument();
    expect(within(fulfillmentRegion).getByText("Expected backorder delivery: 2026-07-20")).toBeInTheDocument();
  });

  it("edits and cancels an existing shipment", async () => {
    const user = userEvent.setup();
    const confirm = vi.spyOn(window, "confirm").mockReturnValue(true);
    setPurchaseOrderMockState([issuedPurchaseOrderFixture]);

    try {
      renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

      const fulfillmentRegion = await screen.findByRole("region", { name: "Fulfillment tracking" });

      await user.click(within(fulfillmentRegion).getByRole("button", { name: "Record shipment" }));
      await user.type(within(fulfillmentRegion).getByLabelText("Line 1 quantity shipped"), "4.0000");
      await user.click(within(fulfillmentRegion).getByRole("button", { name: "Save shipment" }));

      await waitFor(() => {
        expect(within(fulfillmentRegion).getByText("SH-2026-000001")).toBeInTheDocument();
      });

      await user.click(within(fulfillmentRegion).getByRole("button", { name: "Edit" }));
      await user.clear(within(fulfillmentRegion).getByLabelText("Carrier"));
      await user.type(within(fulfillmentRegion).getByLabelText("Carrier"), "Harbor Logistics");
      await user.click(within(fulfillmentRegion).getByRole("button", { name: "Save shipment" }));

      expect(await within(fulfillmentRegion).findByText("Harbor Logistics")).toBeInTheDocument();

      await user.click(within(fulfillmentRegion).getByRole("button", { name: "Cancel shipment" }));

      expect(confirm).toHaveBeenCalledWith("Cancel this shipment?");
      expect(await within(fulfillmentRegion).findByText("cancelled")).toBeInTheDocument();
    } finally {
      confirm.mockRestore();
    }
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

  it("shows change order history with approved and pending change orders", async () => {
    setPurchaseOrderMockState([issuedPurchaseOrderWithAppliedChangeOrderFixture]);
    setPurchaseOrderChangeOrdersMockState("po-applied", [appliedChangeOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-applied" />);

    expect(await screen.findByText("approved")).toBeInTheDocument();
    expect(await screen.findByText("amendment")).toBeInTheDocument();

    cleanup();
    setPurchaseOrderMockState([issuedPurchaseOrderWithPendingChangeOrderFixture]);
    setPurchaseOrderChangeOrdersMockState("po-pending", [pendingPurchaseOrderChangeOrderFixture]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-pending" />);

    expect(await screen.findByText("pending approval")).toBeInTheDocument();
  });
});
