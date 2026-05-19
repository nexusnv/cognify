import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { ApprovalTaskDetailPage } from "../workflows/approval-task-detail-page";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("approval delegation", () => {
  it("requires delegate, effective dates, and reason", async () => {
    const user = userEvent.setup();
    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Delegate" }));
    await user.click(screen.getByRole("button", { name: "Confirm delegation" }));

    expect(await screen.findByText("Delegate, effective dates, and reason are required.")).toBeInTheDocument();
  });

  it("delegates an active approval task", async () => {
    const user = userEvent.setup();
    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Delegate" }));
    await user.selectOptions(screen.getByLabelText("Delegate"), "3");
    await user.type(screen.getByLabelText("Delegation reason"), "Covering a meeting.");
    await user.click(screen.getByRole("button", { name: "Confirm delegation" }));

    expect(await screen.findByText("Finance approver")).toBeInTheDocument();
  });

  it("displays server validation errors for cross-tenant delegations", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/approval-delegations", () => {
        return HttpResponse.json(
          {
            error: {
              code: "validation_failed",
              message: "The selected delegate is outside this tenant.",
            },
          },
          { status: 422 },
        );
      }),
    );

    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Delegate" }));
    await user.selectOptions(screen.getByLabelText("Delegate"), "3");
    await user.type(screen.getByLabelText("Delegation reason"), "Cross-tenant check.");
    await user.click(screen.getByRole("button", { name: "Confirm delegation" }));

    expect(await screen.findByText("The selected delegate is outside this tenant.")).toBeInTheDocument();
  });

  it("displays server validation errors for expired delegations", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/approval-tasks/task-1/delegate", () => {
        return HttpResponse.json(
          {
            error: {
              code: "validation_failed",
              message: "The given data was invalid.",
              details: {
                fields: {
                  approvalDelegationId: ["The selected delegation is expired."],
                },
              },
            },
          },
          { status: 422 },
        );
      }),
    );

    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Delegate" }));
    await user.selectOptions(screen.getByLabelText("Delegate"), "3");
    await user.type(screen.getByLabelText("Delegation reason"), "Expired coverage.");
    await user.click(screen.getByRole("button", { name: "Confirm delegation" }));

    expect(await screen.findByText("The selected delegation is expired.")).toBeInTheDocument();
  });

  it("displays server validation errors for policy-denied delegations", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/approval-tasks/task-1/delegate", () => {
        return HttpResponse.json(
          {
            error: {
              code: "validation_failed",
              message: "The given data was invalid.",
              details: {
                fields: {
                  approvalDelegationId: ["The delegate cannot be the requester of the requisition."],
                },
              },
            },
          },
          { status: 422 },
        );
      }),
    );

    renderWithQuery(<ApprovalTaskDetailPage taskId="task-1" />);

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Delegate" }));
    await user.selectOptions(screen.getByLabelText("Delegate"), "3");
    await user.type(screen.getByLabelText("Delegation reason"), "Requester coverage.");
    await user.click(screen.getByRole("button", { name: "Confirm delegation" }));

    expect(await screen.findByText("The delegate cannot be the requester of the requisition.")).toBeInTheDocument();
  });
});
