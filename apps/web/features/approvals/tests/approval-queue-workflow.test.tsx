import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ApprovalQueuePage } from "../workflows/approval-queue-page";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("approval queue workflow", () => {
  it("renders approval tasks and exposes required queue filters", async () => {
    const user = userEvent.setup();
    renderWithQuery(<ApprovalQueuePage />);

    expect(await screen.findByRole("heading", { name: "Approvals" })).toBeInTheDocument();
    expect(await screen.findByRole("table", { name: "Approval tasks" })).toBeInTheDocument();
    expect(screen.getByText("Warehouse packing supplies")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Assigned to me" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Overdue" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Due soon" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Completed by me" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "All tenant approvals" })).toBeInTheDocument();
    expect(screen.getByLabelText("Status")).toBeInTheDocument();
    expect(screen.getByLabelText("Due from")).toBeInTheDocument();
    expect(screen.getByLabelText("Due to")).toBeInTheDocument();
    expect(screen.getByLabelText("Requester")).toBeInTheDocument();
    expect(screen.getByLabelText("Department")).toBeInTheDocument();
    expect(screen.getByLabelText("Cost center")).toBeInTheDocument();
    expect(screen.getByLabelText("Project")).toBeInTheDocument();
    expect(screen.getByLabelText("Amount min")).toBeInTheDocument();
    expect(screen.getByLabelText("Amount max")).toBeInTheDocument();
    expect(screen.getByLabelText("Updated from")).toBeInTheDocument();
    expect(screen.getByLabelText("Updated to")).toBeInTheDocument();
    const slaSummary = await screen.findByRole("region", { name: "Approval SLA summary" });
    expect(slaSummary).toBeInTheDocument();
    expect(within(slaSummary).getByText("Due soon")).toBeInTheDocument();
    expect(within(slaSummary).getByText("Overdue")).toBeInTheDocument();
    expect(within(slaSummary).getByText("Escalated")).toBeInTheDocument();
    expect(within(slaSummary).getByText(/Oldest pending: Warehouse packing supplies/)).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Due soon" }));
    expect(await screen.findByText("Warehouse packing supplies")).toBeInTheDocument();
  });
});
