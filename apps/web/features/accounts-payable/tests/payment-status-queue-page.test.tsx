"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { resetAccountsPayablePaymentMockState } from "../mocks/accounts-payable-payment-handlers";
import { PaymentStatusQueuePage } from "../components/payment-status-queue-page";

describe("Payment status queue page", () => {
  beforeEach(() => {
    resetAccountsPayablePaymentMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("renders handoffs and status filters", async () => {
    render(<PaymentStatusQueuePage />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Payment status" })).toBeInTheDocument();
    expect(await screen.findByText("Handoff queue")).toBeInTheDocument();

    for (const label of ["Draft", "Ready", "Exported", "Scheduled", "Paid", "Failed", "Voided", "Cancelled"]) {
      expect(screen.getByRole("button", { name: label })).toBeInTheDocument();
    }

    expect((await screen.findAllByText("APH-2026-000001")).length).toBeGreaterThan(0);
    expect((await screen.findAllByText("APH-2026-000003")).length).toBeGreaterThan(0);
    expect((await screen.findAllByText("APH-2026-000004")).length).toBeGreaterThan(0);
  });

  it("filters by status", async () => {
    const user = userEvent.setup();
    render(<PaymentStatusQueuePage />, { wrapper: TestProviders });

    await screen.findAllByText("APH-2026-000001");

    await user.click(screen.getByRole("button", { name: "Scheduled" }));

    expect((await screen.findAllByText("APH-2026-000010")).length).toBeGreaterThan(0);
    expect(screen.queryByText("APH-2026-000001")).not.toBeInTheDocument();
  });

  it("shows post-export handoff details", async () => {
    const user = userEvent.setup();
    render(<PaymentStatusQueuePage />, { wrapper: TestProviders });

    await screen.findAllByText("APH-2026-000001");

    await user.click(screen.getByRole("button", { name: "Paid" }));

    const paidRow = await screen.findByRole("row", { name: /APH-2026-000011/ });
    await user.click(within(paidRow).getByRole("button", { name: "View details" }));

    const sidePanel = await screen.findByLabelText("Handoff details");
    expect(within(sidePanel).getByText("Paid")).toBeInTheDocument();
    expect(within(sidePanel).getAllByText("WIRE-2026-001").length).toBeGreaterThan(0);
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
