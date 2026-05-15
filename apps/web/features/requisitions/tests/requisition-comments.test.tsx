import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { RequisitionComments } from "../components/requisition-comments";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("requisition comments", () => {
  it("lists and creates comments", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionComments requisitionId="req-1" canComment canMention />);

    expect(await screen.findByText("Can you confirm delivery timing?")).toBeInTheDocument();

    await user.type(screen.getByLabelText("Comment"), "Confirmed for next week.");
    await user.click(screen.getByRole("button", { name: "Post comment" }));

    await waitFor(() => {
      expect(screen.getByText("Confirmed for next week.")).toBeInTheDocument();
    });
  });
});
