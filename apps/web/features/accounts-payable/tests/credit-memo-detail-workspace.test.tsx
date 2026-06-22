"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { resetCreditMemoMockState } from "../mocks/accounts-payable-credit-memo-handlers";
import { CreditMemoDetailWorkspace } from "../workflows/credit-memo-detail-workspace";

vi.mock("next/navigation", () => ({
  useParams: () => ({ id: "cm-1" }),
  useRouter: () => ({ push: vi.fn() }),
}));

describe("CreditMemoDetailWorkspace", () => {
  beforeEach(() => {
    resetCreditMemoMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("renders header with number, vendor, and total", async () => {
    render(<CreditMemoDetailWorkspace />, { wrapper: TestProviders });

    expect(await screen.findByText("CM-2026-000001")).toBeInTheDocument();
    expect(screen.getByText(/Acme Supplies/)).toBeInTheDocument();
    expect(screen.getAllByText(/1080\.00/).length).toBeGreaterThanOrEqual(1);
  });

  it("shows submit button for draft credit memo", async () => {
    render(<CreditMemoDetailWorkspace />, { wrapper: TestProviders });

    await screen.findByText("CM-2026-000001");

    expect(screen.getByRole("button", { name: /submit for approval/i })).toBeInTheDocument();
  });

  it("renders math preview with line subtotal", async () => {
    render(<CreditMemoDetailWorkspace />, { wrapper: TestProviders });

    await waitFor(() => {
      expect(screen.getByText("Math preview")).toBeInTheDocument();
    });
    expect(screen.getByText("Lines subtotal")).toBeInTheDocument();
    expect(screen.getByText("Lines tax")).toBeInTheDocument();
    expect(screen.getByText("Lines total")).toBeInTheDocument();
  });

  it("renders activity and attachment placeholders", async () => {
    render(<CreditMemoDetailWorkspace />, { wrapper: TestProviders });

    await screen.findByText("CM-2026-000001");

    expect(screen.getByText("Activity")).toBeInTheDocument();
    expect(screen.getByText("Activity timeline coming in P1-50.")).toBeInTheDocument();
    expect(screen.getByText("Attachments")).toBeInTheDocument();
    expect(screen.getByText("Attachments coming in P1-50.")).toBeInTheDocument();
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
