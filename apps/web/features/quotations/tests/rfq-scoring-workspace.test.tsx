import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { quotationScoringHandlers, resetQuotationScoringMockState } from "../mocks/quotation-scoring-handlers";
import { RfqScoringWorkspace } from "../workflows/rfq-scoring-workspace";

describe("RFQ scoring workspace", () => {
  beforeEach(() => {
    resetQuotationScoringMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
    server.use(...quotationScoringHandlers);
  });

  it("shows template picker when an RFQ has no scorecard", async () => {
    render(<RfqScoringWorkspace rfqId="rfq-no-scorecard" />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Apply scoring template" })).toBeInTheDocument();
    expect(screen.getByText("Balanced RFQ Evaluation")).toBeInTheDocument();
  });

  it("creates a scorecard from the selected active template", async () => {
    const user = userEvent.setup();
    render(<RfqScoringWorkspace rfqId="rfq-no-scorecard" />, { wrapper: TestProviders });

    const card = (await screen.findByText("Balanced RFQ Evaluation")).closest("article");
    expect(card).not.toBeNull();
    await user.click(within(card as HTMLElement).getByRole("button", { name: "Apply" }));

    await waitFor(() => {
      expect(screen.getByRole("heading", { name: "Laptop refresh program" })).toBeInTheDocument();
    });
  });

  it("renders vendor summaries and score matrix", async () => {
    render(<RfqScoringWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Laptop refresh program" })).toBeInTheDocument();
    expect(screen.getByLabelText("Vendor summaries")).toHaveTextContent("Northwind Traders");
    expect(screen.getByLabelText("Score matrix")).toHaveTextContent("Total evaluated cost");
  });

  it("updates scores and criterion notes", async () => {
    const user = userEvent.setup();
    render(<RfqScoringWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });

    await screen.findByRole("heading", { name: "Laptop refresh program" });
    const matrix = screen.getByLabelText("Score matrix");
    const scoreInputs = within(matrix).getAllByLabelText("Score");
    const noteInputs = within(matrix).getAllByLabelText("Note");
    fireEvent.change(scoreInputs[0], { target: { value: "9" } });
    fireEvent.change(noteInputs[0], { target: { value: "Updated cost evidence." } });
    await user.click(within(matrix).getByRole("button", { name: "Save scores" }));

    await waitFor(() => {
      expect(within(matrix).getAllByLabelText("Score")[0]).toHaveValue("9.00");
    });
  });

  it("shows missing required score indicators", async () => {
    render(<RfqScoringWorkspace rfqId="rfq-incomplete" />, { wrapper: TestProviders });

    expect(await screen.findByText("4 missing required scores remain.")).toBeInTheDocument();
    expect(screen.getAllByText("Missing required score").length).toBeGreaterThan(0);
  });

  it("completes a scorecard only when required scores are present", async () => {
    render(<RfqScoringWorkspace rfqId="rfq-incomplete" />, { wrapper: TestProviders });

    expect(await screen.findByRole("button", { name: "Complete scoring" })).toBeDisabled();
  });

  it("renders completed scorecards as read only until reopened", async () => {
    const user = userEvent.setup();
    render(<RfqScoringWorkspace rfqId="rfq-completed" />, { wrapper: TestProviders });

    expect(await screen.findByRole("button", { name: "Reopen scoring" })).toBeInTheDocument();
    expect(screen.getAllByLabelText("Score")[0]).toBeDisabled();
    await user.click(screen.getByRole("button", { name: "Reopen scoring" }));

    await waitFor(() => {
      expect(screen.getByRole("button", { name: "Complete scoring" })).toBeInTheDocument();
    });
  });

  it("links back to the quotation comparison workspace", async () => {
    render(<RfqScoringWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Laptop refresh program" })).toBeInTheDocument();
    expect(screen.getAllByRole("link", { name: "Back to comparison" })[0]).toHaveAttribute(
      "href",
      "/quotations/comparisons/rfq-ready",
    );
  });
});

function TestProviders({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
