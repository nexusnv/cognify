"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { resetAccountsPayablePaymentImportMockState } from "../mocks/accounts-payable-payment-import-handlers";
import { PaymentImportPage } from "../components/payment-import-upload-panel";

describe("Payment import page", () => {
  beforeEach(() => {
    resetAccountsPayablePaymentImportMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("renders upload panel", async () => {
    render(<PaymentImportPage />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Payment import" })).toBeInTheDocument();
    expect(screen.getByText("Upload payment file")).toBeInTheDocument();
    expect(screen.getByText("Drag and drop a file here, or click to browse")).toBeInTheDocument();
  });

  it("uploads file and shows preview", async () => {
    const batchResponse = {
      batchId: "import-batch-test",
      rows: [
        {
          id: "import-row-test-1",
          batchId: "import-batch-test",
          rowIndex: 1,
          handoffNumber: "APH-2026-000010",
          invoiceNumber: "INV-2026-000010",
          paymentReference: "PMT-TEST-001",
          allocatedAmount: "5000.0000",
          markFull: true,
          settlementAmount: "5000.0000",
          settlementCurrency: "USD",
          targetStatus: "paid",
          status: "pending",
          importedByUserId: "buyer-1",
          importedAt: new Date().toISOString(),
          lockVersion: 1,
        },
      ],
      summary: { total: 1, pending: 1, reconciled: 0, failed: 0, discarded: 0 },
    };

    server.use(
      http.post("/api/accounts-payable/payment-imports/upload", () => {
        return HttpResponse.json({ data: batchResponse }, { status: 201 });
      }),
      http.get("/api/accounts-payable/payment-imports/:batchId", () => {
        return HttpResponse.json({ data: batchResponse });
      }),
    );

    const { container } = render(<PaymentImportPage />, { wrapper: TestProviders });

    await screen.findByText("Upload payment file");

    const fileInput = container.querySelector<HTMLInputElement>("#file-upload");
    expect(fileInput).not.toBeNull();

    const file = new File(["handoff,invoice,amount\nAPH-001,INV-001,100.00"], "test.csv", {
      type: "text/csv",
    });

    Object.defineProperty(fileInput, "files", { value: [file], writable: false });
    fireEvent.change(fileInput!);

    expect(await screen.findByText("Review import rows")).toBeInTheDocument();
    expect(await screen.findByText("APH-2026-000010")).toBeInTheDocument();
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
