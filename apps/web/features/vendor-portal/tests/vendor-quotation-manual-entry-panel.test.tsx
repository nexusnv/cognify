import { render, screen } from "@testing-library/react";
import type { QuotationVendorPortal } from "@cognify/api-client/schemas";
import { describe, expect, it, vi } from "vitest";
import { VendorQuotationManualEntryPanel } from "../components/vendor-quotation-manual-entry-panel";

vi.mock("../hooks/use-vendor-quotation", () => ({
  useVendorQuotationManualEntry: () => ({
    isError: false,
    isPending: false,
    mutateAsync: vi.fn(),
  }),
}));

describe("VendorQuotationManualEntryPanel", () => {
  it("rehydrates form values when the quotation changes", () => {
    const { rerender } = render(
      <VendorQuotationManualEntryPanel
        token="vendor-token"
        quotation={quotationWithReference("VQ-001", "USD")}
      />,
    );

    expect(screen.getByLabelText("Quotation reference")).toHaveValue("VQ-001");
    expect(screen.getByLabelText("Currency")).toHaveValue("USD");

    rerender(
      <VendorQuotationManualEntryPanel
        token="vendor-token"
        quotation={quotationWithReference("VQ-002", "MYR")}
      />,
    );

    expect(screen.getByLabelText("Quotation reference")).toHaveValue("VQ-002");
    expect(screen.getByLabelText("Currency")).toHaveValue("MYR");
  });
});

function quotationWithReference(quotationReference: string, currency: string): QuotationVendorPortal {
  return {
    id: `quotation-${quotationReference}`,
    rfqId: "rfq-1",
    vendorId: "vendor-1",
    rfqInvitationId: "invitation-1",
    number: quotationReference,
    status: "received",
    submissionSource: "vendor_portal",
    submittedAt: null,
    latestReceivedAt: null,
    fileCount: 0,
    submittedByUser: null,
    submittedByVendorContact: {
      name: "Nina Northwind",
      email: "nina@northwind.test",
    },
    attachments: [],
    manualEntry: {
      quotationReference,
      quotedAt: null,
      validUntil: null,
      currency,
      subtotalAmount: null,
      taxAmount: null,
      freightAmount: null,
      discountAmount: null,
      totalAmount: null,
      paymentTerms: null,
      deliveryTerms: null,
      leadTimeDays: null,
      warrantyTerms: null,
      exclusions: null,
      complianceNotes: null,
      vendorNotes: null,
    },
    lineItems: [],
    completeness: {
      isComplete: false,
      missingFields: [],
      lineItemCount: 0,
    },
    permissions: {
      canUploadAttachment: true,
      canViewAttachments: true,
      canEditManualEntry: true,
    },
  };
}
