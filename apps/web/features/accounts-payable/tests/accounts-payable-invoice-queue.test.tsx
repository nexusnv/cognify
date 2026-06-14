"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { resetAccountsPayableInvoiceMockState } from "../mocks/accounts-payable-invoice-handlers";
import { AccountsPayableInvoiceQueuePage } from "../workflows/accounts-payable-invoice-queue-page";

describe("Accounts payable invoice queue", () => {
  beforeEach(() => {
    resetAccountsPayableInvoiceMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("renders AP invoice review queue rows", async () => {
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Invoice review" })).toBeInTheDocument();
    const row = await screen.findByRole("row", { name: /INV-10001/i });
    expect(within(row).getByText("Northwind Traders")).toBeInTheDocument();
    expect(within(row).getByText("PO-2026-AP-001")).toBeInTheDocument();
    expect(within(row).getByText("captured")).toBeInTheDocument();
  });

  it("filters to needs information invoices", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    await user.click(await screen.findByRole("tab", { name: "Needs information" }));

    const inv10001Matches = screen.queryAllByText("INV-10001");
    expect(inv10001Matches).toHaveLength(0);
    expect(await screen.findAllByText("Missing attachment")).toHaveLength(2);
  });

  it("starts and completes review", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-10001/i });
    await user.click(within(row).getByRole("button", { name: "Review invoice" }));
    await user.click(screen.getByRole("button", { name: "Start review" }));

    await waitFor(() => expect(screen.getByText("in_review")).toBeInTheDocument());

    for (const label of ["Completeness", "Coding", "Attachment", "Vendor identity", "PO linkage"]) {
      await user.click(screen.getByLabelText(`${label} passed`));
    }

    await user.click(screen.getByRole("button", { name: "Complete review" }));

    await waitFor(() => expect(screen.getByText("reviewed")).toBeInTheDocument());
  });

  it("marks invoice as needs information", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-10001/i });
    await user.click(within(row).getByRole("button", { name: "Review invoice" }));
    await user.click(screen.getByRole("button", { name: "Start review" }));

    await waitFor(() => expect(screen.getByText("in_review")).toBeInTheDocument());

    await user.click(screen.getByLabelText("Completeness failed"));
    await user.click(screen.getByLabelText("Coding failed"));

    await user.click(screen.getByRole("button", { name: "Needs information" }));

    await waitFor(() => expect(screen.getByText("needs_information")).toBeInTheDocument());
  });

  it("surfaces stale review conflicts", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/supplier-invoices/:supplierInvoice/start-review", () =>
        HttpResponse.json(
          { error: { code: "conflict", message: "Supplier invoice was updated by another user." } },
          { status: 409 },
        ),
      ),
    );

    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });
    const row = await screen.findByRole("row", { name: /INV-10001/i });
    await user.click(within(row).getByRole("button", { name: "Review invoice" }));
    await user.click(screen.getByRole("button", { name: "Start review" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Supplier invoice was updated by another user.");
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
