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

describe("award approval task detail", () => {
  it("shows award recommendation context and links back to the award workspace", async () => {
    renderWithQuery(<ApprovalTaskDetailPage taskId="award-task-1" />);

    expect(await screen.findByRole("heading", { name: "Award recommendation for Enterprise laptop refresh" })).toBeInTheDocument();
    expect(screen.getByText("Commercial approval")).toBeInTheDocument();
    expect(screen.getByText("Recommended vendor")).toBeInTheDocument();
    expect(screen.getByText("Northwind Traders")).toBeInTheDocument();
    expect(screen.getByText("RFQ")).toBeInTheDocument();
    expect(screen.getAllByText("RFQ-2026-0101").length).toBeGreaterThan(0);
    expect(screen.getByText("Weighted score")).toBeInTheDocument();
    expect(screen.getByText("86.5")).toBeInTheDocument();
    expect(screen.getByText("Best value overall with strong delivery confidence.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open award recommendation" })).toHaveAttribute(
      "href",
      "/quotations/awards/rfq-pending-recommendation",
    );
    expect(screen.queryByRole("link", { name: "Open requisition" })).not.toBeInTheDocument();
  });
});
