import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { http, HttpResponse } from "msw";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it } from "vitest";
import { purchaseOrderKeys } from "../hooks/use-purchase-order";
import {
  buildPurchaseOrderFixture,
  issuedPurchaseOrderFixture,
} from "../mocks/purchase-order-fixtures";
import {
  resetPurchaseOrderMockState,
  setPurchaseOrderMockState,
} from "../mocks/purchase-order-handlers";
import {
  buildSupplierInvoiceAttachmentFixture,
  buildSupplierInvoiceFixture,
  resetSupplierInvoiceFixtureState,
} from "../mocks/purchase-order-supplier-invoice-fixtures";
import {
  resetSupplierInvoiceMockState,
  setSupplierInvoiceAttachmentsMockState,
  setSupplierInvoiceMockState,
} from "../mocks/purchase-order-supplier-invoice-handlers";
import { fetchPurchaseOrderSupplierInvoices } from "../api/purchase-order-supplier-invoice-api";
import { PurchaseOrderWorkspacePage } from "../workflows/purchase-order-workspace-page";
import { server } from "@/tests/msw/server";

function renderWithProviders(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return {
    queryClient,
    ...render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>),
  };
}

beforeEach(() => {
  resetPurchaseOrderMockState();
  resetSupplierInvoiceFixtureState();
  resetSupplierInvoiceMockState();
  window.localStorage.clear();
  window.localStorage.setItem("cognify.activeTenantId", "1");
});

describe("purchase order supplier invoice workflow", () => {
  it("requires tenant context for supplier invoice requests", async () => {
    await expect(fetchPurchaseOrderSupplierInvoices("po-1", null)).rejects.toMatchObject({
      error: {
        code: "ambiguous_tenant",
        message: "Tenant context is required.",
      },
    });
  });

  it("renders the supplier invoice panel empty state and summary", async () => {
    setPurchaseOrderMockState([
      buildPurchaseOrderFixture({
        ...issuedPurchaseOrderFixture,
        permissions: {
          ...issuedPurchaseOrderFixture.permissions,
          canCaptureInvoice: true,
        },
        invoiceSummary: {
          totalInvoiceCount: 0,
          latestInvoiceDate: null,
          totalInvoicedAmount: "0.00",
          currency: issuedPurchaseOrderFixture.currency,
        },
      }),
    ]);
    setSupplierInvoiceMockState("po-1", []);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    expect(await within(panel).findByText("0 invoice(s) captured")).toBeInTheDocument();
    expect(await within(panel).findByText("No supplier invoices have been captured for this purchase order yet.")).toBeInTheDocument();
    expect(within(panel).getByRole("button", { name: "Capture invoice" })).toBeInTheDocument();
  });

  it("shows an error when supplier invoices cannot be loaded", async () => {
    setPurchaseOrderMockState([
      buildPurchaseOrderFixture({
        ...issuedPurchaseOrderFixture,
        permissions: {
          ...issuedPurchaseOrderFixture.permissions,
          canCaptureInvoice: true,
        },
      }),
    ]);
    server.use(
      http.get("/api/purchase-orders/:purchaseOrder/supplier-invoices", () =>
        HttpResponse.json(
          { error: { code: "server_error", message: "Supplier invoices unavailable." } },
          { status: 500 },
        ),
      ),
    );

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    expect(await within(panel).findByRole("alert")).toHaveTextContent("Supplier invoices unavailable.");
    expect(within(panel).queryByText("No supplier invoices have been captured for this purchase order yet.")).not.toBeInTheDocument();
    expect(within(panel).queryByRole("button", { name: "Capture invoice" })).not.toBeInTheDocument();
  });

  it("hides the capture action when the purchase order cannot capture invoices", async () => {
    setPurchaseOrderMockState([
      buildPurchaseOrderFixture({
        ...issuedPurchaseOrderFixture,
        permissions: {
          ...issuedPurchaseOrderFixture.permissions,
          canCaptureInvoice: false,
        },
      }),
    ]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    expect(within(panel).queryByRole("button", { name: "Capture invoice" })).not.toBeInTheDocument();
  });

  it("captures a supplier invoice successfully", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([
      buildPurchaseOrderFixture({
        ...issuedPurchaseOrderFixture,
        permissions: {
          ...issuedPurchaseOrderFixture.permissions,
          canCaptureInvoice: true,
        },
        invoiceSummary: {
          totalInvoiceCount: 0,
          latestInvoiceDate: null,
          totalInvoicedAmount: "0.00",
          currency: issuedPurchaseOrderFixture.currency,
        },
      }),
    ]);
    setSupplierInvoiceMockState("po-1", []);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    await user.click(within(panel).getByRole("button", { name: "Capture invoice" }));
    await user.type(within(panel).getByLabelText("Invoice number"), "INV-10001");
    await user.clear(within(panel).getByLabelText("Invoice date"));
    await user.type(within(panel).getByLabelText("Invoice date"), "2026-06-11");
    await user.clear(within(panel).getByLabelText("Due date"));
    await user.type(within(panel).getByLabelText("Due date"), "2026-07-11");
    await user.type(within(panel).getByLabelText("Tax amount"), "7200.00");
    await user.type(within(panel).getByLabelText("Freight amount"), "3900.00");
    await user.type(within(panel).getByLabelText("Invoice notes"), "Supplier invoice received by AP.");
    await user.clear(within(panel).getByLabelText("Pallet rack bay quantity invoiced"));
    await user.type(within(panel).getByLabelText("Pallet rack bay quantity invoiced"), "10.0000");
    await user.clear(within(panel).getByLabelText("Pallet rack bay unit price"));
    await user.type(within(panel).getByLabelText("Pallet rack bay unit price"), "12000.0000");
    await user.type(within(panel).getByLabelText("Pallet rack bay line notes"), "Matches PO line.");
    await user.click(within(panel).getByRole("button", { name: "Save invoice" }));

    expect(await within(panel).findByText("INV-10001")).toBeInTheDocument();
    expect(within(panel).getByText("1 invoice(s) captured")).toBeInTheDocument();
    expect(within(panel).getByText("Latest invoice date: 2026-06-11")).toBeInTheDocument();
    expect(within(panel).getByText(/Captured by user-1/)).toBeInTheDocument();
  });

  it("keeps entered values on duplicate conflict", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([
      buildPurchaseOrderFixture({
        ...issuedPurchaseOrderFixture,
        permissions: {
          ...issuedPurchaseOrderFixture.permissions,
          canCaptureInvoice: true,
        },
      }),
    ]);
    setSupplierInvoiceMockState("po-1", [
      buildSupplierInvoiceFixture({
        id: "supplier-invoice-1",
        invoiceNumber: "INV-10001",
        number: "SI-2026-000001",
      }),
    ]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    await user.click(within(panel).getByRole("button", { name: "Capture invoice" }));
    await user.type(within(panel).getByLabelText("Invoice number"), "INV-10001");
    await user.clear(within(panel).getByLabelText("Invoice date"));
    await user.type(within(panel).getByLabelText("Invoice date"), "2026-06-12");
    await user.clear(within(panel).getByLabelText("Due date"));
    await user.type(within(panel).getByLabelText("Due date"), "2026-07-12");
    await user.clear(within(panel).getByLabelText("Pallet rack bay quantity invoiced"));
    await user.type(within(panel).getByLabelText("Pallet rack bay quantity invoiced"), "10.0000");
    await user.clear(within(panel).getByLabelText("Pallet rack bay unit price"));
    await user.type(within(panel).getByLabelText("Pallet rack bay unit price"), "12000.0000");
    await user.type(within(panel).getByLabelText("Pallet rack bay line notes"), "Keep these values.");
    await user.click(within(panel).getByRole("button", { name: "Save invoice" }));

    expect(await within(panel).findByRole("alert")).toHaveTextContent("already exists");
    expect(within(panel).getByLabelText("Invoice number")).toHaveValue("INV-10001");
    expect(within(panel).getByLabelText("Pallet rack bay quantity invoiced")).toHaveValue("10.0000");
    expect(within(panel).getByLabelText("Pallet rack bay unit price")).toHaveValue("12000.0000");
    expect(within(panel).getByLabelText("Pallet rack bay line notes")).toHaveValue("Keep these values.");
  });

  it("defaults a repeat invoice to the remaining uninvoiced line quantity", async () => {
    const user = userEvent.setup();
    setPurchaseOrderMockState([
      buildPurchaseOrderFixture({
        ...issuedPurchaseOrderFixture,
        permissions: {
          ...issuedPurchaseOrderFixture.permissions,
          canCaptureInvoice: true,
        },
      }),
    ]);
    setSupplierInvoiceMockState("po-1", [
      buildSupplierInvoiceFixture({
        id: "supplier-invoice-1",
        invoiceNumber: "INV-10001",
        number: "SI-2026-000001",
        lines: [
          {
            id: "supplier-invoice-line-1",
            purchaseOrderLineId: "po-line-1",
            lineNumber: 1,
            descriptionSnapshot: "Pallet rack bay",
            quantityOrdered: "10.0000",
            quantityInvoiced: "6.0000",
            unitPrice: "12000.0000",
            lineSubtotal: "72000.00",
            notes: null,
          },
        ],
      }),
    ]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    await user.click(within(panel).getByRole("button", { name: "Capture invoice" }));

    expect(within(panel).getByLabelText("Pallet rack bay quantity invoiced")).toHaveValue("4.0000");
  });

  it("keeps supplier invoice line values mapped by purchase-order line id after a line reorder", async () => {
    const user = userEvent.setup();
    const multiLinePurchaseOrder = buildPurchaseOrderFixture({
      ...issuedPurchaseOrderFixture,
      permissions: {
        ...issuedPurchaseOrderFixture.permissions,
        canCaptureInvoice: true,
      },
      lines: [
        {
          ...issuedPurchaseOrderFixture.lines[0],
          id: "po-line-1",
          lineNumber: 1,
          description: "Pallet rack bay",
          quantity: "10.0000",
          unitPrice: "12000.0000",
        },
        {
          ...issuedPurchaseOrderFixture.lines[0],
          id: "po-line-2",
          lineNumber: 2,
          description: "Forklift battery",
          quantity: "4.0000",
          unitPrice: "3500.0000",
          subtotalAmount: "14000.00",
          totalAmount: "14000.00",
        },
      ],
    });

    setPurchaseOrderMockState([multiLinePurchaseOrder]);
    setSupplierInvoiceMockState("po-1", []);

    const { queryClient } = renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    await user.click(within(panel).getByRole("button", { name: "Capture invoice" }));
    await user.type(within(panel).getByLabelText("Invoice number"), "INV-20002");
    await user.clear(within(panel).getByLabelText("Invoice date"));
    await user.type(within(panel).getByLabelText("Invoice date"), "2026-06-12");

    await user.clear(within(panel).getByLabelText("Pallet rack bay quantity invoiced"));
    await user.type(within(panel).getByLabelText("Pallet rack bay quantity invoiced"), "3.5000");
    await user.clear(within(panel).getByLabelText("Pallet rack bay unit price"));
    await user.type(within(panel).getByLabelText("Pallet rack bay unit price"), "123.4500");
    await user.type(within(panel).getByLabelText("Pallet rack bay line notes"), "Rack note");

    await user.clear(within(panel).getByLabelText("Forklift battery quantity invoiced"));
    await user.type(within(panel).getByLabelText("Forklift battery quantity invoiced"), "1.2500");
    await user.clear(within(panel).getByLabelText("Forklift battery unit price"));
    await user.type(within(panel).getByLabelText("Forklift battery unit price"), "987.6500");
    await user.type(within(panel).getByLabelText("Forklift battery line notes"), "Battery note");

    setPurchaseOrderMockState([
      {
        ...multiLinePurchaseOrder,
        lines: [...multiLinePurchaseOrder.lines].reverse(),
      },
    ]);
    await queryClient.invalidateQueries({
      queryKey: purchaseOrderKeys.detail("1", "po-1"),
    });

    await waitFor(() => {
      expect(within(panel).getByLabelText("Forklift battery quantity invoiced")).toHaveValue("1.2500");
      expect(within(panel).getByLabelText("Pallet rack bay quantity invoiced")).toHaveValue("3.5000");
    });

    await user.click(within(panel).getByRole("button", { name: "Save invoice" }));

    const forkliftLine = (await within(panel).findByText("Line 2: Forklift battery")).closest("div");
    expect(forkliftLine).not.toBeNull();
    expect(within(forkliftLine as HTMLElement).getByText("Quantity invoiced 1.2500 at 987.6500")).toBeInTheDocument();
    expect(within(forkliftLine as HTMLElement).getByText("Battery note")).toBeInTheDocument();

    const palletLine = within(panel).getByText("Line 1: Pallet rack bay").closest("div");
    expect(palletLine).not.toBeNull();
    expect(within(palletLine as HTMLElement).getByText("Quantity invoiced 3.5000 at 123.4500")).toBeInTheDocument();
    expect(within(palletLine as HTMLElement).getByText("Rack note")).toBeInTheDocument();
  });

  it("omits zero-quantity lines from a partial supplier invoice", async () => {
    const user = userEvent.setup();
    const multiLinePurchaseOrder = buildPurchaseOrderFixture({
      ...issuedPurchaseOrderFixture,
      permissions: {
        ...issuedPurchaseOrderFixture.permissions,
        canCaptureInvoice: true,
      },
      lines: [
        {
          ...issuedPurchaseOrderFixture.lines[0],
          id: "po-line-1",
          lineNumber: 1,
          description: "Pallet rack bay",
          quantity: "10.0000",
          unitPrice: "12000.0000",
        },
        {
          ...issuedPurchaseOrderFixture.lines[0],
          id: "po-line-2",
          lineNumber: 2,
          description: "Forklift battery",
          quantity: "4.0000",
          unitPrice: "3500.0000",
          subtotalAmount: "14000.00",
          totalAmount: "14000.00",
        },
      ],
    });

    setPurchaseOrderMockState([multiLinePurchaseOrder]);
    setSupplierInvoiceMockState("po-1", []);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    await user.click(within(panel).getByRole("button", { name: "Capture invoice" }));
    await user.type(within(panel).getByLabelText("Invoice number"), "INV-20003");
    await user.clear(within(panel).getByLabelText("Invoice date"));
    await user.type(within(panel).getByLabelText("Invoice date"), "2026-06-13");
    await user.clear(within(panel).getByLabelText("Pallet rack bay quantity invoiced"));
    await user.type(within(panel).getByLabelText("Pallet rack bay quantity invoiced"), "2.0000");
    await user.clear(within(panel).getByLabelText("Forklift battery quantity invoiced"));
    await user.type(within(panel).getByLabelText("Forklift battery quantity invoiced"), "0.0000");
    await user.click(within(panel).getByRole("button", { name: "Save invoice" }));

    expect(await within(panel).findByText("Line 1: Pallet rack bay")).toBeInTheDocument();
    expect(within(panel).queryByText("Line 2: Forklift battery")).not.toBeInTheDocument();
    expect(within(panel).queryByRole("alert")).not.toBeInTheDocument();
  });

  it("lists and uploads invoice attachments", async () => {
    const user = userEvent.setup();
    const invoice = buildSupplierInvoiceFixture({
      id: "supplier-invoice-1",
      invoiceNumber: "INV-20001",
      number: "SI-2026-000001",
    });

    setPurchaseOrderMockState([
      buildPurchaseOrderFixture({
        ...issuedPurchaseOrderFixture,
        permissions: {
          ...issuedPurchaseOrderFixture.permissions,
          canCaptureInvoice: true,
        },
      }),
    ]);
    setSupplierInvoiceMockState("po-1", [invoice]);
    setSupplierInvoiceAttachmentsMockState(invoice.id, [
      buildSupplierInvoiceAttachmentFixture({
        id: "attachment-1",
        parentId: invoice.id,
        filename: "invoice-20001.pdf",
      }),
    ]);

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    expect(await within(panel).findByText("invoice-20001.pdf")).toBeInTheDocument();

    const file = new File(["invoice attachment"], "packing-slip.pdf", { type: "application/pdf" });
    const uploadInput = within(panel).getByLabelText("Upload invoice attachment");
    await user.upload(uploadInput, file);
    await user.click(within(panel).getByRole("button", { name: "Upload attachment" }));

    await waitFor(() => {
      expect(within(panel).getByText("packing-slip.pdf")).toBeInTheDocument();
    });
  });

  it("shows attachment load errors without rendering an empty attachment state", async () => {
    const invoice = buildSupplierInvoiceFixture({
      id: "supplier-invoice-1",
      invoiceNumber: "INV-20001",
      number: "SI-2026-000001",
    });

    setPurchaseOrderMockState([
      buildPurchaseOrderFixture({
        ...issuedPurchaseOrderFixture,
        permissions: {
          ...issuedPurchaseOrderFixture.permissions,
          canCaptureInvoice: true,
        },
      }),
    ]);
    setSupplierInvoiceMockState("po-1", [invoice]);
    server.use(
      http.get("/api/supplier-invoices/:supplierInvoice/attachments", () =>
        HttpResponse.json(
          { error: { code: "server_error", message: "Attachments unavailable." } },
          { status: 500 },
        ),
      ),
    );

    renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

    const panel = await screen.findByRole("region", { name: "Supplier invoices" });
    expect(await within(panel).findByRole("alert")).toHaveTextContent("Could not load invoice attachments.");
    expect(within(panel).queryByText("No invoice attachments uploaded yet.")).not.toBeInTheDocument();
  });
});
