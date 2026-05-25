import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import {
  quotationComparisonHandlers,
  resetQuotationComparisonMockState,
} from "../mocks/quotation-comparison-handlers";
import { getQuotationComparisonFixture } from "../mocks/quotation-comparison-fixtures";
import { QuotationComparisonWorkspace } from "../workflows/quotation-comparison-workspace";

describe("Quotation comparison workspace", () => {
  beforeEach(() => {
    resetQuotationComparisonMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
    server.use(...quotationComparisonHandlers);
  });

  it("renders vendor summaries and line comparison for ready RFQ", async () => {
    render(<QuotationComparisonWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Laptop refresh program" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open scoring" })).toHaveAttribute("href", "/quotations/scoring/rfq-ready");
    expect(screen.getByText("Risk scoring not configured")).toBeInTheDocument();
    expect(screen.getByText("Comparison notes are annotations only. They do not score vendors, recommend awards, or change RFQ status.")).toBeInTheDocument();
    expect(screen.getAllByText("Northwind Traders").length).toBeGreaterThan(0);
    expect(screen.getByText("Developer laptops")).toBeInTheDocument();
    expect(screen.getAllByText(/Bundle total/).length).toBeGreaterThan(0);
    expect(screen.getByText("Payment terms")).toBeInTheDocument();
  });

  it("marks vendors that require approved normalization", async () => {
    render(<QuotationComparisonWorkspace rfqId="rfq-mixed-readiness" />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Laptop refresh with partial readiness" })).toBeInTheDocument();
    expect(screen.getAllByText("Normalization required").length).toBeGreaterThan(0);
    expect(screen.getByText("2 pending normalization")).toBeInTheDocument();
  });

  it("shows mixed currency warning without converting values", async () => {
    render(<QuotationComparisonWorkspace rfqId="rfq-mixed-currency" />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Laptop refresh with mixed currency offers" })).toBeInTheDocument();
    expect(screen.getByText("Mixed currencies")).toBeInTheDocument();
    expect(screen.getAllByText("USD 12470.00").length).toBeGreaterThan(0);
    expect(screen.getAllByText("EUR 11900.00").length).toBeGreaterThan(0);
  });

  it("renders zero-day lead time as an available value", async () => {
    server.use(
      http.get("/api/rfqs/rfq-zero-lead-time/comparison", () => {
        const comparison = getQuotationComparisonFixture("rfq-ready");
        return HttpResponse.json({
          data: {
            ...comparison,
            rfq: { ...comparison?.rfq, id: "rfq-zero-lead-time" },
            vendors: comparison?.vendors.map((vendor, index) => (
              index === 0 ? { ...vendor, leadTimeDays: "0" } : vendor
            )),
          },
        });
      }),
    );

    render(<QuotationComparisonWorkspace rfqId="rfq-zero-lead-time" />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Laptop refresh program" })).toBeInTheDocument();
    expect(screen.getByText("0 days")).toBeInTheDocument();
  });

  it("creates edits and deletes non-decision comparison notes", async () => {
    const user = userEvent.setup();
    render(<QuotationComparisonWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });

    await screen.findByRole("heading", { name: "Laptop refresh program" });
    fireEvent.change(screen.getByLabelText("Note section"), { target: { value: "risk" } });
    fireEvent.change(screen.getByLabelText("Comparison note"), {
      target: { value: "Confirm delivery assumptions before final evaluation." },
    });
    await user.click(screen.getByRole("button", { name: "Add note" }));

    expect(await screen.findByText("Confirm delivery assumptions before final evaluation.")).toBeInTheDocument();

    const note = screen.getByText("Confirm delivery assumptions before final evaluation.").closest("[data-testid='comparison-note']");
    expect(note).not.toBeNull();
    await user.click(within(note as HTMLElement).getByRole("button", { name: "Edit note" }));
    fireEvent.change(screen.getByLabelText("Comparison note"), {
      target: { value: "Updated delivery context." },
    });
    await user.click(screen.getByRole("button", { name: "Save note" }));

    expect(await screen.findByText("Updated delivery context.")).toBeInTheDocument();

    const updatedNote = screen.getByText("Updated delivery context.").closest("[data-testid='comparison-note']");
    expect(updatedNote).not.toBeNull();
    await user.click(within(updatedNote as HTMLElement).getByRole("button", { name: "Delete note" }));

    await waitFor(() => {
      expect(screen.queryByText("Updated delivery context.")).not.toBeInTheDocument();
    });
  });

  it("keeps note form state and surfaces an error when note creation fails", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/rfqs/rfq-ready/comparison/notes", () =>
        HttpResponse.json(
          { error: { code: "validation_failed", message: "A comparison note is required." } },
          { status: 422 },
        ),
      ),
    );
    render(<QuotationComparisonWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });

    await screen.findByRole("heading", { name: "Laptop refresh program" });
    fireEvent.change(screen.getByLabelText("Comparison note"), {
      target: { value: "Keep this text on failed submit." },
    });
    await user.click(screen.getByRole("button", { name: "Add note" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("A comparison note is required.");
    expect(screen.getByLabelText("Comparison note")).toHaveValue("Keep this text on failed submit.");
  });

  it("hides note controls when canManageQuotationComparisonNotes is false", async () => {
    server.use(
      http.get("/api/rfqs/rfq-read-only/comparison", () => {
        const comparison = getQuotationComparisonFixture("rfq-ready");
        return HttpResponse.json({
          data: {
            ...comparison,
            rfq: { ...comparison?.rfq, id: "rfq-read-only" },
            permissions: {
              canViewComparison: true,
              canManageQuotationComparisonNotes: false,
            },
          },
        });
      }),
    );

    render(<QuotationComparisonWorkspace rfqId="rfq-read-only" />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Laptop refresh program" })).toBeInTheDocument();
    expect(screen.getByText("Note controls are unavailable for this RFQ.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Add note" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Edit note" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Delete note" })).not.toBeInTheDocument();
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
