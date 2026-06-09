import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ApprovalTaskDetailPage } from "../workflows/approval-task-detail-page";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("purchase order approval task detail", () => {
  it("shows purchase order context and links back to the PO workspace", async () => {
    renderWithQuery(<ApprovalTaskDetailPage taskId="purchase-order-task-1" />);

    expect(await screen.findByRole("heading", { name: "Purchase order for sustainability packaging" })).toBeInTheDocument();
    expect(screen.getByText("Finance review")).toBeInTheDocument();
    expect(screen.getAllByText("Vendor").length).toBeGreaterThan(0);
    expect(screen.getAllByText("Northwind Traders").length).toBeGreaterThan(0);
    expect(screen.getByText("RFQ")).toBeInTheDocument();
    expect(screen.getByText("RFQ-2026-0204")).toBeInTheDocument();
    expect(screen.getAllByText("Payment terms").length).toBeGreaterThan(0);
    expect(screen.getAllByText("Net 30").length).toBeGreaterThan(0);
    expect(screen.getByText("Delivery terms")).toBeInTheDocument();
    expect(screen.getByText("DAP Kuala Lumpur")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open purchase order" })).toHaveAttribute(
      "href",
      "/purchase-orders/po-1",
    );
    expect(screen.queryByRole("link", { name: "Open requisition" })).not.toBeInTheDocument();
  });
});
