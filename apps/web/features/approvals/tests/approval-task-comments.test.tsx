import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ApprovalTaskComments } from "../components/approval-task-comments";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("approval task comments", () => {
  it("lists and creates comments", async () => {
    const user = userEvent.setup();

    renderWithQuery(<ApprovalTaskComments taskId="task-1" />);

    expect(await screen.findByText("Can you confirm budget owner alignment?")).toBeInTheDocument();

    await user.type(screen.getByLabelText("Comment"), "Budget owner confirmed.");
    await user.click(screen.getByRole("button", { name: "Post comment" }));

    await waitFor(() => {
      expect(screen.getByText("Budget owner confirmed.")).toBeInTheDocument();
    });
  });

  it("shows the empty state before the first comment", async () => {
    renderWithQuery(<ApprovalTaskComments taskId="award-task-1" />);

    expect(await screen.findByText("No comments yet.")).toBeInTheDocument();
  });
});
