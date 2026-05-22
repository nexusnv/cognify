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

  it("renders updatedAt and lastJobError from the list API contract", async () => {
    server.use(
      http.get("/api/quotation-normalizations", () =>
        HttpResponse.json({
          data: [
            {
              id: "norm-failed-contract",
              status: "failed",
              normalizationRevision: 1,
              algorithmVersion: "rules-v1",
              updatedAt: "2026-05-22T07:05:00.000Z",
              lastJobError: "Normalizer could not parse the uploaded workbook.",
              source: {
                quotationId: "quotation-2",
                quotationVersionId: "103",
                quotationNumber: "QT-2026-099",
                versionNumber: 3,
                rfqId: "rfq-1",
                rfqNumber: "RFQ-2026-000001",
                vendorId: "vendor-2",
                vendorName: "Atlas Workplace Supply",
              },
              summary: {
                blockingIssueCount: 0,
                warningIssueCount: 0,
                infoIssueCount: 0,
              },
              permissions: {
                canEdit: false,
                canApprove: false,
                canApproveWithWarnings: false,
                canRetry: true,
                canCreateRevision: false,
              },
            },
          ],
        }),
      ),
    );

    render(<QuotationNormalizationQueuePage />, { wrapper: TestProviders });

    const failedRow = await screen.findByRole("row", { name: /Atlas Workplace Supply/i });
    expect(within(failedRow).getByText(/May 22, 2026/)).toBeInTheDocument();
    expect(within(failedRow).getByText("Normalizer could not parse the uploaded workbook.")).toBeInTheDocument();
  });

  it("lets a reviewer retry failed rows when the row permissions allow it", async () => {
    const user = userEvent.setup();
    let retriedVersionId: string | null = null;
    let retried = false;

    server.use(
      http.get("/api/quotation-normalizations", () =>
        HttpResponse.json({
          data: [
            {
              id: "norm-needs-review",
              status: "needs_review",
              normalizationRevision: 1,
              algorithmVersion: "rules-v1",
              updatedAt: "2026-05-22T09:15:00.000Z",
              lastJobError: null,
              source: {
                quotationId: "quotation-1",
                quotationVersionId: "101",
                quotationNumber: "QT-2026-041",
                versionNumber: 2,
                rfqId: "rfq-1",
                rfqNumber: "RFQ-2026-000001",
                vendorId: "vendor-1",
                vendorName: "Northwind Traders",
              },
              summary: {
                blockingIssueCount: 2,
                warningIssueCount: 1,
                infoIssueCount: 1,
              },
              permissions: {
                canEdit: true,
                canApprove: false,
                canApproveWithWarnings: false,
                canRetry: false,
                canCreateRevision: false,
              },
            },
            {
              id: "norm-failed",
              status: retried ? "processing" : "failed",
              normalizationRevision: retried ? 2 : 1,
              algorithmVersion: "rules-v1",
              updatedAt: retried ? "2026-05-22T10:10:00.000Z" : "2026-05-22T07:05:00.000Z",
              lastJobError: retried ? null : "Normalizer could not parse the uploaded workbook.",
              source: {
                quotationId: "quotation-2",
                quotationVersionId: "103",
                quotationNumber: "QT-2026-099",
                versionNumber: 3,
                rfqId: "rfq-1",
                rfqNumber: "RFQ-2026-000001",
                vendorId: "vendor-2",
                vendorName: "Atlas Workplace Supply",
              },
              summary: {
                blockingIssueCount: 0,
                warningIssueCount: 0,
                infoIssueCount: 0,
              },
              permissions: {
                canEdit: false,
                canApprove: false,
                canApproveWithWarnings: false,
                canRetry: !retried,
                canCreateRevision: false,
              },
            },
          ],
        }),
      ),
    );

    server.use(
      http.post("/api/quotation-versions/:version/normalization/retry", ({ params }) => {
        retriedVersionId = String(params.version);
        if (retriedVersionId !== "103") {
          return HttpResponse.json(
            { error: { code: "not_found", message: "Quotation version not found." } },
            { status: 404 },
          );
        }

        retried = true;

        return HttpResponse.json({
          data: {
            id: "norm-failed",
            status: "processing",
            normalizationRevision: 2,
            algorithmVersion: "rules-v1",
            updatedAt: "2026-05-22T10:10:00.000Z",
            lastJobError: null,
            source: {
              quotationId: "quotation-2",
              quotationVersionId: "103",
              quotationNumber: "QT-2026-099",
              versionNumber: 3,
              rfqId: "rfq-1",
              rfqNumber: "RFQ-2026-000001",
              vendorId: "vendor-2",
              vendorName: "Atlas Workplace Supply",
            },
            summary: {
              blockingIssueCount: 0,
              warningIssueCount: 0,
              infoIssueCount: 0,
            },
            permissions: {
              canEdit: false,
              canApprove: false,
              canApproveWithWarnings: false,
              canRetry: false,
              canCreateRevision: false,
            },
          },
        });
      }),
    );

    render(<QuotationNormalizationQueuePage />, { wrapper: TestProviders });

    const failedRow = await screen.findByRole("row", { name: /Atlas Workplace Supply/i });
    await user.click(within(failedRow).getByRole("button", { name: "Retry normalization" }));

    await waitFor(() => {
      expect(retriedVersionId).toBe("103");
      expect(screen.getAllByText("processing").length).toBeGreaterThan(0);
    });
  });

  it("re-enables a row and surfaces an error when retry fails", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/quotation-normalizations", () =>
        HttpResponse.json({
          data: [
            {
              id: "norm-failed",
              status: "failed",
              normalizationRevision: 1,
              algorithmVersion: "rules-v1",
              updatedAt: "2026-05-22T07:05:00.000Z",
              lastJobError: "Normalizer could not parse the uploaded workbook.",
              source: {
                quotationId: "quotation-2",
                quotationVersionId: "103",
                quotationNumber: "QT-2026-099",
                versionNumber: 3,
                rfqId: "rfq-1",
                rfqNumber: "RFQ-2026-000001",
                vendorId: "vendor-2",
                vendorName: "Atlas Workplace Supply",
              },
              summary: {
                blockingIssueCount: 0,
                warningIssueCount: 0,
                infoIssueCount: 0,
              },
              permissions: {
                canEdit: false,
                canApprove: false,
                canApproveWithWarnings: false,
                canRetry: true,
                canCreateRevision: false,
              },
            },
          ],
        }),
      ),
      http.post("/api/quotation-versions/:version/normalization/retry", () =>
        HttpResponse.json(
          { error: { code: "conflict", message: "Retry job already queued." } },
          { status: 409 },
        ),
      ),
    );

    render(<QuotationNormalizationQueuePage />, { wrapper: TestProviders });

    const failedRow = await screen.findByRole("row", { name: /Atlas Workplace Supply/i });
    const retryButton = within(failedRow).getByRole("button", { name: "Retry normalization" });
    await user.click(retryButton);

    expect(await screen.findByRole("alert")).toHaveTextContent("Retry failed: Retry job already queued.");
    await waitFor(() => {
      expect(within(failedRow).getByRole("button", { name: "Retry normalization" })).not.toBeDisabled();
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
