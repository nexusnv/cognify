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
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await userEvent.click(within(row).getByRole("button", { name: "View exceptions" }));

    expect(await screen.findByText("Unit price mismatch")).toBeInTheDocument();
  });

  it("allows resolving an exception with explanation", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));

    await user.click(screen.getByRole("button", { name: "Resolve" }));

    await user.click(screen.getByLabelText("Explanation"));
    await user.type(screen.getByLabelText("Explanation notes"), "Price variance accepted per policy.");

    await user.click(screen.getByRole("button", { name: "Submit resolution" }));

    await waitFor(() => {
      expect(screen.getByText("resolved")).toBeInTheDocument();
    });
  });

  it("allows escalating an exception", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));

    await user.click(screen.getByRole("button", { name: "Escalate" }));
    await user.type(screen.getByLabelText("Escalation note"), "Requires manager review.");

    await user.click(screen.getByRole("button", { name: "Confirm escalation" }));

    await waitFor(() => {
      expect(screen.getByText("escalated")).toBeInTheDocument();
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

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));
    await user.click(screen.getByRole("button", { name: "Resolve" }));
    await user.click(screen.getByLabelText("Explanation"));
    await user.click(screen.getByRole("button", { name: "Submit resolution" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Exception was updated by another user");
  });
});

function TestProviders({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
