"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { InvoiceMatchingStatusBadge } from "../components/invoice-matching-status-badge";
import { InvoiceMatchResultsPanel } from "../components/invoice-match-results-panel";
import { resetAccountsPayableInvoiceMockState } from "../mocks/accounts-payable-invoice-handlers";

function TestProviders({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe("InvoiceMatchingStatusBadge", () => {
  it("renders Pending for null status", () => {
    render(<InvoiceMatchingStatusBadge matchingStatus={null} />);
    expect(screen.getByText("Pending")).toBeInTheDocument();
  });

  it("renders Pending for undefined status", () => {
    render(<InvoiceMatchingStatusBadge matchingStatus={undefined} />);
    expect(screen.getByText("Pending")).toBeInTheDocument();
  });

  it("renders Pending for pending status", () => {
    render(<InvoiceMatchingStatusBadge matchingStatus="pending" />);
    expect(screen.getByText("Pending")).toBeInTheDocument();
  });

  it("renders Matched for matched status", () => {
    render(<InvoiceMatchingStatusBadge matchingStatus="matched" />);
    expect(screen.getByText("Matched")).toBeInTheDocument();
  });

  it("renders Mismatch for mismatch status", () => {
    render(<InvoiceMatchingStatusBadge matchingStatus="mismatch" />);
    expect(screen.getByText("Mismatch")).toBeInTheDocument();
  });
});

describe("InvoiceMatchResultsPanel", () => {
  beforeEach(() => {
    resetAccountsPayableInvoiceMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("shows Run Matching button for reviewed invoices", () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );
    expect(screen.getByRole("button", { name: "Run Matching" })).toBeInTheDocument();
  });

  it("hides Run Matching button for non-reviewed invoices", () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-1"
        lockVersion={1}
        invoiceStatus="captured"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );
    expect(screen.queryByRole("button", { name: "Run Matching" })).not.toBeInTheDocument();
  });

  it("hides Run Matching button when already matched", () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus="matched"
      />,
      { wrapper: TestProviders },
    );
    expect(screen.queryByRole("button", { name: "Run Matching" })).not.toBeInTheDocument();
  });

  it("shows Pending badge when no matching status", () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );
    expect(screen.getByText("Pending")).toBeInTheDocument();
  });

  it("shows Mismatch badge when matching status is mismatch", () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus="mismatch"
      />,
      { wrapper: TestProviders },
    );
    expect(screen.getByText("Mismatch")).toBeInTheDocument();
  });

  it("shows Match Results header", () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );
    expect(screen.getByText("Matching Results")).toBeInTheDocument();
  });

  it("renders match results when API returns data", async () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );

    const summary = await screen.findByText(/lines matched/);
    expect(summary).toBeInTheDocument();
  });

  it("renders match summary line count", async () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );

    const summary = await screen.findByText(/lines matched/);
    expect(summary).toHaveTextContent(/1 of 1 lines matched/);
  });

  it("shows match result table rows", async () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );

    await waitFor(() => {
      expect(screen.getByRole("table")).toBeInTheDocument();
    });
  });

  it("shows empty summary when no results available", async () => {
    server.use(
      http.get("/api/supplier-invoices/:supplierInvoice/match-results", () =>
        HttpResponse.json({ data: [] }),
      ),
    );

    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );

    await waitFor(() => {
      expect(screen.getByText(/0 of 0 lines matched/)).toBeInTheDocument();
    });
  });

  it("shows error state when match results fail to load", async () => {
    server.use(
      http.get("/api/supplier-invoices/:supplierInvoice/match-results", () =>
        HttpResponse.json(
          { error: { code: "server_error", message: "Internal server error" } },
          { status: 500 },
        ),
      ),
    );

    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );

    await waitFor(() => {
      expect(screen.getByText("Failed to load match results.")).toBeInTheDocument();
    });
  });

  it("calls run matching API on button click", async () => {
    const user = userEvent.setup();
    let called = false;

    server.use(
      http.post("/api/supplier-invoices/:supplierInvoice/run-matching", async ({ params, request }) => {
        called = true;
        const body = await request.json() as { lockVersion: number };
        expect(body.lockVersion).toBe(7);
        return HttpResponse.json({
          data: {
            id: params.supplierInvoice,
            matchingStatus: "mismatch",
            lockVersion: 8,
          },
        });
      }),
    );

    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );

    await user.click(screen.getByRole("button", { name: "Run Matching" }));
    await waitFor(() => expect(called).toBe(true));
  });

  it("shows conflict error when matching returns 409", async () => {
    server.use(
      http.post("/api/supplier-invoices/:supplierInvoice/run-matching", () =>
        HttpResponse.json(
          {
            error: {
              code: "conflict",
              message: "Matching can only be run on reviewed invoices.",
            },
          },
          { status: 409 },
        ),
      ),
    );

    const user = userEvent.setup();

    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
      { wrapper: TestProviders },
    );

    await user.click(screen.getByRole("button", { name: "Run Matching" }));

    await waitFor(() => {
      expect(
        screen.getByText("Matching can only be run on reviewed invoices."),
      ).toBeInTheDocument();
    });
  });

  it("shows Run Matching button when matchingStatus is mismatch (re-run)", async () => {
    render(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-4"
        lockVersion={7}
        invoiceStatus="reviewed"
        matchingStatus="mismatch"
      />,
      { wrapper: TestProviders },
    );

    expect(screen.getByRole("button", { name: "Run Matching" })).toBeInTheDocument();
  });
});
