"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it } from "vitest";
import { resetCreditApplicationMockState } from "../mocks/accounts-payable-credit-application-handlers";
import { CreditMemoApplicationPanel } from "../components/credit-memo-application-panel";

function renderWithProviders() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <CreditMemoApplicationPanel creditMemoId="cm-3" lockVersion={3} />
    </QueryClientProvider>,
  );
}

describe("CreditMemoApplicationPanel", () => {
  beforeEach(() => {
    resetCreditApplicationMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("renders the apply form with labels", async () => {
    renderWithProviders();

    expect(await screen.findByLabelText(/supplier invoice id/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/applied amount/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/application date/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/notes/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /apply/i })).toBeInTheDocument();
  });

  it("shows existing applications for cm-3", async () => {
    renderWithProviders();

    await waitFor(() => {
      expect(screen.getByText(/INV-2026-000044/)).toBeInTheDocument();
    });
    expect(screen.getByText("500.00")).toBeInTheDocument();
    expect(screen.getByText("First application")).toBeInTheDocument();
  });

  it("submits a new application", async () => {
    const user = userEvent.setup();
    renderWithProviders();

    const invoiceInput = await screen.findByLabelText(/supplier invoice id/i);
    const amountInput = screen.getByLabelText(/applied amount/i);
    const dateInput = screen.getByLabelText(/application date/i);

    await user.type(invoiceInput, "inv-5");
    await user.type(amountInput, "200.00");
    await user.type(dateInput, "2026-06-21");
    await user.click(screen.getByRole("button", { name: /apply/i }));

    await waitFor(() => {
      expect(invoiceInput).toHaveValue("");
    });
    expect(amountInput).toHaveValue("");
  });
});
