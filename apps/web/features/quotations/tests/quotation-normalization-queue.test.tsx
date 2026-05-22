import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { delay, http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { resetQuotationNormalizationMockState } from "../mocks/quotation-normalization-handlers";
import { server } from "@/tests/msw/server";
import { QuotationNormalizationQueuePage } from "../workflows/quotation-normalization-queue-page";

describe("Quotation normalization queue", () => {
  beforeEach(() => {
    resetQuotationNormalizationMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("shows a loading state while the queue is loading", async () => {
    server.use(
      http.get("/api/quotation-normalizations", async () => {
        await delay(150);
        return HttpResponse.json({ data: [] });
      }),
    );

    render(<QuotationNormalizationQueuePage />, { wrapper: TestProviders });

    expect(screen.getByLabelText("Loading quotation normalization queue")).toBeInTheDocument();
    await screen.findByText("No quotation normalizations need review right now.");
  });

  it("renders queue rows with status, vendor, RFQ, version, issue counts, updated time, and workspace links", async () => {
    render(<QuotationNormalizationQueuePage />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Quotation normalizations" })).toBeInTheDocument();

    const reviewRow = screen.getByRole("row", { name: /needs review/i });
    expect(within(reviewRow).getByText("Northwind Traders")).toBeInTheDocument();
    expect(within(reviewRow).getByText("RFQ-2026-000001")).toBeInTheDocument();
    expect(within(reviewRow).getByText("Version 2")).toBeInTheDocument();
    expect(within(reviewRow).getByText("2 blocking")).toBeInTheDocument();
    expect(within(reviewRow).getByText("1 warning")).toBeInTheDocument();
    expect(within(reviewRow).getByText(/May 22, 2026/)).toBeInTheDocument();

    const link = within(reviewRow).getByRole("link", { name: /open normalization workspace/i });
    expect(link).toHaveAttribute("href", "/quotations/normalizations/norm-needs-review");
  });

  it("lets a reviewer retry failed rows when the row permissions allow it", async () => {
    const user = userEvent.setup();

    render(<QuotationNormalizationQueuePage />, { wrapper: TestProviders });

    const failedRow = await screen.findByRole("row", { name: /Atlas Workplace Supply/i });
    await user.click(within(failedRow).getByRole("button", { name: "Retry normalization" }));

    await waitFor(() => {
      expect(screen.getAllByText("processing").length).toBeGreaterThan(0);
    });
  });

  it("shows a permission error state instead of queue actions when the list request is forbidden", async () => {
    server.use(
      http.get("/api/quotation-normalizations", () =>
        HttpResponse.json(
          { error: { code: "forbidden", message: "You do not have access to quotation normalizations." } },
          { status: 403 },
        ),
      ),
    );

    render(<QuotationNormalizationQueuePage />, { wrapper: TestProviders });

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "You do not have access to quotation normalizations.",
    );
    expect(screen.queryByRole("button", { name: "Retry normalization" })).not.toBeInTheDocument();
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
