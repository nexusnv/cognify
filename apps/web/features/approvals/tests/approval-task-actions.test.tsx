import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ApprovalTaskDetailPage } from "../workflows/approval-task-detail-page";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("approval task actions", () => {
  it("approves an assigned task", async () => {
    const user = userEvent.setup();
    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Approve" }));
    await user.click(screen.getByRole("button", { name: "Confirm approval" }));

    expect(await screen.findByText("approved")).toBeInTheDocument();
  });

  it("requires a reason before requesting changes", async () => {
    const user = userEvent.setup();
    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    await screen.findByRole("heading", { name: "Warehouse packing supplies" });
    await user.click(screen.getByRole("button", { name: "Request changes" }));
    await user.click(screen.getByRole("button", { name: "Confirm request changes" }));

    expect(await screen.findByText("Reason is required.")).toBeInTheDocument();
  });
});
