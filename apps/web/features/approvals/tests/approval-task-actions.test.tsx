import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { approvalTaskFixtures } from "../mocks/approval-fixtures";
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
    await user.click(screen.getByRole("tab", { name: "Decision" }));
    await user.click(screen.getByRole("button", { name: "Approve" }));
    await user.click(screen.getByRole("button", { name: "Confirm approval" }));

    expect(await screen.findAllByText("approved")).not.toHaveLength(0);
  });

  it("requires a reason before requesting changes", async () => {
    const user = userEvent.setup();
    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    await screen.findByRole("heading", { name: "Warehouse packing supplies" });
    await user.click(screen.getByRole("tab", { name: "Decision" }));
    await user.click(screen.getByRole("button", { name: "Request changes" }));
    await user.click(screen.getByRole("button", { name: "Confirm request changes" }));

    expect(await screen.findByText("Reason is required.")).toBeInTheDocument();
  });

  it("shows backend action conflicts inline", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/approval-tasks/task-1/approve", () => {
        return HttpResponse.json(
          { error: { code: "conflict", message: "Approval task has changed. Refresh before trying again." } },
          { status: 409 },
        );
      }),
    );

    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    await screen.findByRole("heading", { name: "Warehouse packing supplies" });
    await user.click(screen.getByRole("tab", { name: "Decision" }));
    await user.click(screen.getByRole("button", { name: "Approve" }));
    await user.click(screen.getByRole("button", { name: "Confirm approval" }));

    expect(
      await screen.findByText("Approval task has changed. Refresh before trying again."),
    ).toBeInTheDocument();
  });

  it("hides decision actions when backend permissions deny them on an active task", async () => {
    const user = userEvent.setup();
    const viewOnlyTask = {
      ...structuredClone(approvalTaskFixtures[0]!),
      id: "view-only-task",
      permissions: {
        canView: true,
        canApprove: false,
        canReject: false,
        canRequestChanges: false,
      },
    };
    server.use(
      http.get("/api/approval-tasks/view-only-task", () => {
        return HttpResponse.json({ data: viewOnlyTask });
      }),
    );

    renderWithQuery(<ApprovalTaskDetailPage taskId="view-only-task" />);

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("tab", { name: "Decision" }));
    expect(screen.queryByRole("button", { name: "Approve" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Reject" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Request changes" })).not.toBeInTheDocument();
  });
});
