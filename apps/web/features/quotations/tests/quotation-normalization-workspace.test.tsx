import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { resetQuotationNormalizationMockState } from "../mocks/quotation-normalization-handlers";
import { server } from "@/tests/msw/server";
import { QuotationNormalizationWorkspace } from "../workflows/quotation-normalization-workspace";

describe("Quotation normalization workspace", () => {
  beforeEach(() => {
    resetQuotationNormalizationMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("renders source and normalized values side by side with issue badges", async () => {
    render(<QuotationNormalizationWorkspace normalizationId="norm-needs-review" />, {
      wrapper: TestProviders,
    });

    expect(await screen.findByRole("heading", { name: "QT-2026-041" })).toBeInTheDocument();
    const currencyCard = screen.getByTestId("normalization-field-manualEntry.currency");
    expect(within(currencyCard).getByText("manualEntry.currency")).toBeInTheDocument();
    expect(within(currencyCard).getByText("usd$")).toBeInTheDocument();
    expect(within(currencyCard).getByText("No normalized value")).toBeInTheDocument();
    expect(screen.getAllByText("blocking").length).toBeGreaterThan(0);
    expect(screen.getAllByText("warning").length).toBeGreaterThan(0);
    expect(screen.getAllByText("info").length).toBeGreaterThan(0);
  });

  it("lets a buyer correct manual entry currency", async () => {
    const user = userEvent.setup();

    render(<QuotationNormalizationWorkspace normalizationId="norm-needs-review" />, {
      wrapper: TestProviders,
    });

    const currencyCard = await screen.findByTestId("normalization-field-manualEntry.currency");
    await user.clear(within(currencyCard).getByLabelText("Corrected value"));
    await user.type(within(currencyCard).getByLabelText("Corrected value"), "USD");
    await user.type(within(currencyCard).getByLabelText("Correction note"), "Currency confirmed from quote.");
    await user.click(within(currencyCard).getByRole("button", { name: "Save correction" }));

    await waitFor(() => {
      expect(within(currencyCard).getByText("USD")).toBeInTheDocument();
      expect(screen.getByRole("button", { name: "Approve normalization" })).toBeDisabled();
    });
  });

  it("lets a buyer save a bundled line mapping from one quotation line to one RFQ line", async () => {
    const user = userEvent.setup();
    let requestedVersionId: string | null = null;

    server.use(
      http.get("/api/quotations/:quotationId/versions/:versionId", ({ params }) => {
        requestedVersionId = String(params.versionId);

        if (requestedVersionId !== "101") {
          return HttpResponse.json(
            { error: { code: "not_found", message: "Quotation version not found." } },
            { status: 404 },
          );
        }

        return HttpResponse.json({
          data: {
            id: "101",
            quotationId: String(params.quotationId),
            versionNumber: 2,
            status: "received",
            source: "buyer_upload",
            submittedAt: "2026-05-22T09:15:00.000Z",
            submittedByUser: {
              id: "buyer-1",
              name: "Priya Buyer",
            },
            submittedByVendorContact: null,
            isCurrent: true,
            supersededAt: null,
            previousVersionId: null,
            manualEntry: {
              quotationReference: "QT-2026-041",
              quotedAt: "2026-05-22",
              validUntil: "2026-06-30",
              currency: "USD",
              subtotalAmount: "12470.00",
              taxAmount: "0.00",
              freightAmount: "0.00",
              discountAmount: "0.00",
              totalAmount: "12470.00",
              paymentTerms: null,
              deliveryTerms: "DDP",
              leadTimeDays: 14,
              warrantyTerms: "3 years",
              exclusions: null,
              complianceNotes: null,
              buyerNotes: null,
              vendorNotes: null,
            },
            lineItems: [
              {
                id: "quote-line-1",
                rfqLineItemId: "rfq-line-1",
                description: "Fetched line from quotation version route",
                quantity: "10.0000",
                unit: "each",
                unitPrice: "1247.00",
                subtotalAmount: "12470.00",
                taxAmount: "0.00",
                totalAmount: "12470.00",
                leadTimeDays: 14,
                manufacturer: "Lenovo",
                modelNumber: "T16",
                alternateOffered: false,
                complianceStatus: "compliant",
                notes: "Bundle includes freight and setup kits.",
              },
            ],
            attachments: [],
            attachmentCount: 0,
            completeness: {
              isComplete: true,
              missingFields: [],
              lineItemCount: 1,
            },
            permissions: {
              canEdit: false,
              canCreateRevision: true,
            },
          },
        });
      }),
    );

    render(<QuotationNormalizationWorkspace normalizationId="norm-needs-review" />, {
      wrapper: TestProviders,
    });

    const lineMappings = await screen.findByTestId("normalization-line-mappings");
    expect(requestedVersionId).toBe("101");
    await user.selectOptions(within(lineMappings).getByLabelText("Quotation version line"), "quote-line-1");
    await user.selectOptions(within(lineMappings).getByLabelText("RFQ line"), "rfq-line-1");
    await user.selectOptions(within(lineMappings).getByLabelText("Pricing mode"), "bundle");
    await user.clear(within(lineMappings).getByLabelText("Bundle description"));
    await user.type(within(lineMappings).getByLabelText("Bundle description"), "Developer laptop bundle");
    await user.clear(within(lineMappings).getByLabelText("Bundle total"));
    await user.type(within(lineMappings).getByLabelText("Bundle total"), "12470.00");
    await user.type(within(lineMappings).getByLabelText("Buyer note"), "Bundle confirmed against vendor submission.");
    await user.click(within(lineMappings).getByRole("button", { name: "Save line mapping" }));

    await waitFor(() => {
      expect(requestedVersionId).toBe("101");
      expect(screen.getByText("Bundle confirmed against vendor submission.")).toBeInTheDocument();
    });
  });

  it("disables approval while blocking issues remain and allows approval after they are resolved", async () => {
    const user = userEvent.setup();

    render(<QuotationNormalizationWorkspace normalizationId="norm-needs-review" />, {
      wrapper: TestProviders,
    });

    const approveButton = await screen.findByRole("button", { name: "Approve normalization" });
    expect(approveButton).toBeDisabled();

    const currencyCard = screen.getByTestId("normalization-field-manualEntry.currency");
    await user.clear(within(currencyCard).getByLabelText("Corrected value"));
    await user.type(within(currencyCard).getByLabelText("Corrected value"), "USD");
    await user.type(within(currencyCard).getByLabelText("Correction note"), "Currency confirmed from quote.");
    await user.click(within(currencyCard).getByRole("button", { name: "Save correction" }));

    const lineMappings = screen.getByTestId("normalization-line-mappings");
    await user.selectOptions(within(lineMappings).getByLabelText("Quotation version line"), "quote-line-1");
    await user.selectOptions(within(lineMappings).getByLabelText("RFQ line"), "rfq-line-1");
    await user.selectOptions(within(lineMappings).getByLabelText("Pricing mode"), "bundle");
    await user.clear(within(lineMappings).getByLabelText("Bundle description"));
    await user.type(within(lineMappings).getByLabelText("Bundle description"), "Developer laptop bundle");
    await user.clear(within(lineMappings).getByLabelText("Bundle total"));
    await user.type(within(lineMappings).getByLabelText("Bundle total"), "12470.00");
    await user.click(within(lineMappings).getByRole("button", { name: "Save line mapping" }));

    await waitFor(() => {
      expect(screen.getByRole("button", { name: "Approve normalization" })).toBeEnabled();
    });

    await user.type(screen.getByLabelText("Approval note"), "Normalization reviewed for comparison.");
    await user.click(screen.getByRole("button", { name: "Approve normalization" }));

    await waitFor(() => {
      expect(screen.getByText("approved")).toBeInTheDocument();
      expect(screen.getByText("Read-only approved record")).toBeInTheDocument();
    });
  });

  it("requires an acknowledgement note before approving with warnings", async () => {
    const user = userEvent.setup();

    render(<QuotationNormalizationWorkspace normalizationId="norm-ready-for-approval" />, {
      wrapper: TestProviders,
    });

    await user.click(await screen.findByRole("button", { name: "Approve with warnings" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Add an acknowledgement note before approving with warnings.");

    await user.type(screen.getByLabelText("Approval note"), "Commercial risk accepted pending payment terms.");
    await user.click(screen.getByRole("button", { name: "Approve with warnings" }));

    await waitFor(() => {
      expect(screen.getByText("approved with warnings")).toBeInTheDocument();
    });
  });

  it("renders approved records as read-only", async () => {
    render(<QuotationNormalizationWorkspace normalizationId="norm-approved-with-warnings" />, {
      wrapper: TestProviders,
    });

    expect(await screen.findByText("Read-only approved record")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Save correction" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Approve normalization" })).not.toBeInTheDocument();
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
