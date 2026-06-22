"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { resetCreditMemoMockState } from "../mocks/accounts-payable-credit-memo-handlers";
import { CreditMemoQueuePage } from "../workflows/credit-memo-queue-page";

describe("CreditMemoQueuePage", () => {
  beforeEach(() => {
    resetCreditMemoMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("renders status tabs and seeded credit memos", async () => {
    render(<CreditMemoQueuePage />, { wrapper: TestProviders });

    expect(await screen.findByText("CM-2026-000001")).toBeInTheDocument();
    expect(screen.getByText("CM-2026-000002")).toBeInTheDocument();
    expect(screen.getByText("CM-2026-000003")).toBeInTheDocument();

    for (const label of ["All", "Draft", "Pending approval", "Open", "Partially applied", "Fully applied", "Closed", "Voided"]) {
      expect(screen.getByRole("button", { name: label })).toBeInTheDocument();
    }
  });

  it("filters by status tab", async () => {
    const user = userEvent.setup();
    render(<CreditMemoQueuePage />, { wrapper: TestProviders });

    await screen.findByText("CM-2026-000001");

    await user.click(screen.getByRole("button", { name: "Open" }));

    await waitFor(() => {
      expect(screen.getByText("CM-2026-000002")).toBeInTheDocument();
    });
    expect(screen.queryByText("CM-2026-000001")).not.toBeInTheDocument();
    expect(screen.queryByText("CM-2026-000003")).not.toBeInTheDocument();
  });

  it("renders status badges for each memo", async () => {
    render(<CreditMemoQueuePage />, { wrapper: TestProviders });

    await screen.findByText("CM-2026-000001");

    // Each status label appears twice: once as a tab button, once as a badge
    expect(screen.getAllByText("Draft").length).toBeGreaterThanOrEqual(2);
    expect(screen.getAllByText("Open").length).toBeGreaterThanOrEqual(2);
    expect(screen.getAllByText("Partially applied").length).toBeGreaterThanOrEqual(2);
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
