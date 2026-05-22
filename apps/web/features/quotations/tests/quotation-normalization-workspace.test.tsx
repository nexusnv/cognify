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
      http.get("/api/quotation-normalizations/:normalizationId", ({ params }) => {
        return HttpResponse.json({
          data: {
            id: String(params.normalizationId),
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
            fields: [
              {
                id: "field-currency",
                fieldPath: "manualEntry.currency",
                rawValue: "usd$",
                normalizedValue: null,
                dataType: "string",
                currency: null,
                confidence: "0.42",
                source: "manual_entry",
                provenance: {
                  sourceQuotationVersionId: "101",
                  sourceLabel: "Quotation currency",
                },
              },
            ],
            lineGroups: [],
            attachments: [],
            issues: [],
            permissions: {
              canEdit: true,
              canApprove: false,
              canApproveWithWarnings: false,
              canRetry: false,
              canCreateRevision: false,
            },
          },
        });
      }),
      http.get("/api/quotations/:quotationId/versions/:versionId", ({ params }) => {
        requestedVersionId = String(params.versionId);

        if (requestedVersionId !== "2") {
          return HttpResponse.json(
            { error: { code: "not_found", message: "Quotation version not found." } },
            { status: 404 },
          );
        }

        return new Promise((resolve) => {
          setTimeout(() => {
            resolve(
              HttpResponse.json({
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
              }),
            );
          }, 25);
        });
      }),
    );

    render(<QuotationNormalizationWorkspace normalizationId="norm-needs-review" />, {
      wrapper: TestProviders,
    });

    const lineMappings = await screen.findByTestId("normalization-line-mappings");
    expect(requestedVersionId).toBe("2");
    expect(screen.queryByRole("option", { name: "Mock detail line that should be ignored" })).not.toBeInTheDocument();
    expect(await screen.findByRole("option", { name: "Fetched line from quotation version route" })).toBeInTheDocument();
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
      expect(requestedVersionId).toBe("2");
      expect(screen.getByText("Bundle confirmed against vendor submission.")).toBeInTheDocument();
    });
  });

  it("saves line mapping values from the selected quotation line and current form fields", async () => {
    const user = userEvent.setup();
    let submittedPayload: unknown = null;

    server.use(
      http.get("/api/quotation-normalizations/:normalizationId", ({ params }) => {
        return HttpResponse.json({
          data: {
            id: String(params.normalizationId),
            status: "needs_review",
            normalizationRevision: 1,
            algorithmVersion: "rules-v1",
            updatedAt: "2026-05-22T09:15:00.000Z",
            lastJobError: null,
            source: {
              quotationId: "quotation-9",
              quotationVersionId: "909",
              quotationNumber: "QT-2026-900",
              versionNumber: 9,
              rfqId: "rfq-9",
              rfqNumber: "RFQ-2026-000009",
              vendorId: "vendor-9",
              vendorName: "Contoso Supplies",
            },
            summary: {
              blockingIssueCount: 1,
              warningIssueCount: 0,
              infoIssueCount: 0,
            },
            fields: [],
            lineGroups: [
              {
                id: "line-group-3",
                groupNumber: 3,
                pricingMode: "bundle",
                description: "Industrial fasteners",
                currency: "EUR",
                bundleTotalAmount: "247.50",
                notes: "Pre-existing buyer mapping.",
                mappings: [
                  {
                    id: "mapping-3-1",
                    rfqLineItemId: "rfq-line-9",
                    quotationVersionLineItemId: "quote-line-9",
                    mappingType: "partial",
                    quantity: "3.5000",
                    unit: "box",
                    unitPrice: null,
                    lineTotal: "247.50",
                    buyerNote: "Existing mapping note.",
                  },
                ],
              },
            ],
            attachments: [],
            issues: [
              {
                id: "issue-line-mapping",
                severity: "blocking",
                fieldPath: "lineItems.bundle",
                issueCode: "line_mapping_required",
                message: "Map the quotation bundle to an RFQ line before approval.",
                rawValue: "Industrial fasteners",
                suggestedValue: null,
                status: "open",
                resolvedByUserId: null,
                resolvedAt: null,
                resolutionNote: null,
              },
            ],
            permissions: {
              canEdit: true,
              canApprove: false,
              canApproveWithWarnings: false,
              canRetry: false,
              canCreateRevision: false,
            },
          },
        });
      }),
      http.get("/api/quotations/:quotationId/versions/:versionId", ({ params }) => {
        if (String(params.versionId) !== "9") {
          return HttpResponse.json(
            { error: { code: "not_found", message: "Quotation version not found." } },
            { status: 404 },
          );
        }

        return HttpResponse.json({
          data: {
            id: "909",
            quotationId: String(params.quotationId),
            versionNumber: 9,
            status: "received",
            source: "buyer_upload",
            submittedAt: "2026-05-22T09:15:00.000Z",
            submittedByUser: {
              id: "buyer-9",
              name: "Priya Buyer",
            },
            submittedByVendorContact: null,
            isCurrent: true,
            supersededAt: null,
            previousVersionId: null,
            manualEntry: {
              quotationReference: "QT-2026-900",
              quotedAt: "2026-05-22",
              validUntil: "2026-06-30",
              currency: "EUR",
              subtotalAmount: "247.50",
              taxAmount: "0.00",
              freightAmount: "0.00",
              discountAmount: "0.00",
              totalAmount: "247.50",
              paymentTerms: null,
              deliveryTerms: "DAP",
              leadTimeDays: 21,
              warrantyTerms: "12 months",
              exclusions: null,
              complianceNotes: null,
              buyerNotes: null,
              vendorNotes: null,
            },
            lineItems: [
              {
                id: "quote-line-9",
                rfqLineItemId: "rfq-line-9",
                description: "Industrial fasteners",
                quantity: "3.5000",
                unit: "box",
                unitPrice: "70.7143",
                subtotalAmount: "247.50",
                taxAmount: "0.00",
                totalAmount: "247.50",
                leadTimeDays: 21,
                manufacturer: "FabCo",
                modelNumber: "FX-7",
                alternateOffered: false,
                complianceStatus: "compliant",
                notes: "Packed in boxes of 3.5 units.",
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
      http.post("/api/quotation-normalizations/:normalizationId/line-mappings", async ({ request }) => {
        submittedPayload = await request.json();
        return HttpResponse.json({
          data: {
            id: "norm-9",
            status: "needs_review",
            normalizationRevision: 1,
            algorithmVersion: "rules-v1",
            updatedAt: "2026-05-22T10:10:00.000Z",
            lastJobError: null,
            source: {
              quotationId: "quotation-9",
              quotationVersionId: "909",
              quotationNumber: "QT-2026-900",
              versionNumber: 9,
              rfqId: "rfq-9",
              rfqNumber: "RFQ-2026-000009",
              vendorId: "vendor-9",
              vendorName: "Contoso Supplies",
            },
            summary: {
              blockingIssueCount: 1,
              warningIssueCount: 0,
              infoIssueCount: 0,
            },
            fields: [],
            lineGroups: [],
            attachments: [],
            issues: [],
            permissions: {
              canEdit: true,
              canApprove: false,
              canApproveWithWarnings: false,
              canRetry: false,
              canCreateRevision: false,
            },
          },
        });
      }),
    );

    render(<QuotationNormalizationWorkspace normalizationId="norm-9" />, {
      wrapper: TestProviders,
    });

    const lineMappings = await screen.findByTestId("normalization-line-mappings");
    await screen.findByRole("option", { name: "Industrial fasteners" });
    await user.selectOptions(within(lineMappings).getByLabelText("Quotation version line"), "quote-line-9");
    await user.selectOptions(within(lineMappings).getByLabelText("RFQ line"), "rfq-line-9");
    await user.selectOptions(within(lineMappings).getByLabelText("Pricing mode"), "bundle");
    await user.clear(within(lineMappings).getByLabelText("Currency"));
    await user.type(within(lineMappings).getByLabelText("Currency"), "EUR");
    await user.clear(within(lineMappings).getByLabelText("Quantity"));
    await user.type(within(lineMappings).getByLabelText("Quantity"), "4.2500");
    await user.clear(within(lineMappings).getByLabelText("Unit"));
    await user.type(within(lineMappings).getByLabelText("Unit"), "carton");
    await user.clear(within(lineMappings).getByLabelText("Bundle total"));
    await user.type(within(lineMappings).getByLabelText("Bundle total"), "298.75");
    await user.click(within(lineMappings).getByRole("button", { name: "Save line mapping" }));

    await waitFor(() => {
      expect(submittedPayload).not.toBeNull();
    });

    expect(submittedPayload).toEqual({
      lineGroups: [
        {
          groupNumber: 3,
          pricingMode: "bundle",
          description: "Industrial fasteners",
          currency: "EUR",
          bundleTotalAmount: "298.75",
          notes: "Existing mapping note.",
          mappings: [
            {
              rfqLineItemId: "rfq-line-9",
              quotationVersionLineItemId: "quote-line-9",
              mappingType: "partial",
              quantity: "4.2500",
              unit: "carton",
              lineTotal: "298.75",
              buyerNote: "Existing mapping note.",
            },
          ],
        },
      ],
    });
  });

  it("disables line mapping save when no RFQ line can be selected", async () => {
    server.use(
      http.get("/api/quotation-normalizations/:normalizationId", ({ params }) => {
        return HttpResponse.json({
          data: {
            id: String(params.normalizationId),
            status: "needs_review",
            normalizationRevision: 1,
            algorithmVersion: "rules-v1",
            updatedAt: "2026-05-22T09:15:00.000Z",
            lastJobError: null,
            source: {
              quotationId: "quotation-10",
              quotationVersionId: "1001",
              quotationNumber: "QT-2026-901",
              versionNumber: 10,
              rfqId: "rfq-10",
              rfqNumber: "RFQ-2026-000010",
              vendorId: "vendor-10",
              vendorName: "Fabrikam",
            },
            summary: {
              blockingIssueCount: 1,
              warningIssueCount: 0,
              infoIssueCount: 0,
            },
            fields: [],
            lineGroups: [],
            attachments: [],
            issues: [
              {
                id: "issue-line-mapping",
                severity: "blocking",
                fieldPath: "lineItems.bundle",
                issueCode: "line_mapping_required",
                message: "Map the quotation bundle to an RFQ line before approval.",
                rawValue: "Office chairs",
                suggestedValue: null,
                status: "open",
                resolvedByUserId: null,
                resolvedAt: null,
                resolutionNote: null,
              },
            ],
            permissions: {
              canEdit: true,
              canApprove: false,
              canApproveWithWarnings: false,
              canRetry: false,
              canCreateRevision: false,
            },
          },
        });
      }),
      http.get("/api/quotations/:quotationId/versions/:versionId", ({ params }) => {
        return HttpResponse.json({
          data: {
            id: String(params.versionId),
            quotationId: String(params.quotationId),
            versionNumber: 10,
            status: "received",
            source: "buyer_upload",
            submittedAt: "2026-05-22T09:15:00.000Z",
            submittedByUser: {
              id: "buyer-10",
              name: "Priya Buyer",
            },
            submittedByVendorContact: null,
            isCurrent: true,
            supersededAt: null,
            previousVersionId: null,
            manualEntry: {
              quotationReference: "QT-2026-901",
              quotedAt: "2026-05-22",
              validUntil: "2026-06-30",
              currency: "USD",
              subtotalAmount: "188.00",
              taxAmount: "0.00",
              freightAmount: "0.00",
              discountAmount: "0.00",
              totalAmount: "188.00",
              paymentTerms: null,
              deliveryTerms: "DAP",
              leadTimeDays: 14,
              warrantyTerms: "12 months",
              exclusions: null,
              complianceNotes: null,
              buyerNotes: null,
              vendorNotes: null,
            },
            lineItems: [
              {
                id: "quote-line-10",
                rfqLineItemId: null,
                description: "Office chairs",
                quantity: "2.0000",
                unit: "each",
                unitPrice: "94.00",
                subtotalAmount: "188.00",
                taxAmount: "0.00",
                totalAmount: "188.00",
                leadTimeDays: 14,
                manufacturer: "Steelcase",
                modelNumber: "K-1",
                alternateOffered: false,
                complianceStatus: "compliant",
                notes: null,
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

    render(<QuotationNormalizationWorkspace normalizationId="norm-10" />, {
      wrapper: TestProviders,
    });

    const lineMappings = await screen.findByTestId("normalization-line-mappings");
    expect(within(lineMappings).getByText("No RFQ line items are available to map this quotation version.")).toBeInTheDocument();
    expect(within(lineMappings).getByRole("button", { name: "Save line mapping" })).toBeDisabled();
    expect(within(lineMappings).getByLabelText("RFQ line")).toBeDisabled();
  });

  it("disables approval while blocking issues remain and allows approval after they are resolved", async () => {
    const user = userEvent.setup();
    let requestedVersionId: string | null = null;

    server.use(
      http.get("/api/quotations/:quotationId/versions/:versionId", ({ params }) => {
        requestedVersionId = String(params.versionId);

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
                description: "Developer laptop bundle",
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

    const approveButton = await screen.findByRole("button", { name: "Approve normalization" });
    expect(approveButton).toBeDisabled();

    const currencyCard = screen.getByTestId("normalization-field-manualEntry.currency");
    await user.clear(within(currencyCard).getByLabelText("Corrected value"));
    await user.type(within(currencyCard).getByLabelText("Corrected value"), "USD");
    await user.type(within(currencyCard).getByLabelText("Correction note"), "Currency confirmed from quote.");
    await user.click(within(currencyCard).getByRole("button", { name: "Save correction" }));

    const lineMappings = screen.getByTestId("normalization-line-mappings");
    await waitFor(() => {
      expect(within(lineMappings).getByLabelText("Bundle description")).toHaveValue("Developer laptop bundle");
    });
    expect(requestedVersionId).toBe("2");
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
