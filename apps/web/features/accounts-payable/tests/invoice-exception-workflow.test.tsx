"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { resetInvoiceExceptionMockState } from "../mocks/invoice-exception-handlers";
import { AccountsPayableInvoiceQueuePage } from "../workflows/accounts-payable-invoice-queue-page";

describe("Invoice exception workflow", () => {
  beforeEach(() => {
    resetInvoiceExceptionMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("shows exception panel when invoice has mismatches", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    await user.click(screen.getByRole("tab", { name: "All" }));

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));

    expect(await screen.findAllByText("unit_price")).toHaveLength(2);
    expect(await screen.findByText("line_total")).toBeInTheDocument();
  });

  it("allows resolving an exception with explanation", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    await user.click(screen.getByRole("tab", { name: "All" }));

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));

    const resolveButtons = await screen.findAllByRole("button", { name: "Resolve" });
    await user.click(resolveButtons[0]);

    const explanationRadio = await screen.findByLabelText("Explanation (waive variance)");
    await user.click(explanationRadio);
    await user.type(await screen.findByLabelText("Explanation notes"), "Price variance accepted per policy.");

    await user.click(await screen.findByRole("button", { name: "Submit resolution" }));

    await waitFor(() => {
      expect(screen.getByText("Resolved")).toBeInTheDocument();
    });
  });

  it("allows escalating an exception", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    await user.click(screen.getByRole("tab", { name: "All" }));

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));

    const escalateButtons = await screen.findAllByRole("button", { name: "Escalate" });
    await user.click(escalateButtons[0]);
    await user.type(await screen.findByLabelText("Escalation note"), "Requires manager review.");

    await user.click(await screen.findByRole("button", { name: "Confirm escalation" }));

    await waitFor(() => {
      expect(screen.getByText("Escalated")).toBeInTheDocument();
    });
  });

  it("shows conflict error when exception lock version is stale", async () => {
    server.use(
      http.post("/api/supplier-invoices/:supplierInvoice/exceptions/:exception/resolve", () =>
        HttpResponse.json(
          { error: { code: "conflict", message: "Exception was updated by another user." } },
          { status: 409 },
        ),
      ),
    );

    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    await user.click(screen.getByRole("tab", { name: "All" }));

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));
    const resolveButtons = await screen.findAllByRole("button", { name: "Resolve" });
    await user.click(resolveButtons[0]);
    const explanationRadio = await screen.findByLabelText("Explanation (waive variance)");
    await user.click(explanationRadio);
    await user.click(await screen.findByRole("button", { name: "Submit resolution" }));

    await user.keyboard("{Escape}");

    expect(await screen.findByRole("alert")).toHaveTextContent("Exception was updated by another user");
  });
});

function TestProviders({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
