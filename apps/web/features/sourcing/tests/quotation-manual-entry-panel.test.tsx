import { render, screen } from "@testing-library/react";
import type { Quotation } from "@cognify/api-client/schemas";
import { describe, expect, it, vi } from "vitest";
import { QuotationManualEntryPanel } from "../components/quotation-manual-entry-panel";

vi.mock("../hooks/use-quotation-manual-entry", () => ({
  useSaveQuotationManualEntry: () => ({
    isError: false,
    isPending: false,
    mutateAsync: vi.fn(),
  }),
}));

describe("QuotationManualEntryPanel", () => {
  it("rehydrates form values when the quotation changes", () => {
    const { rerender } = render(
      <QuotationManualEntryPanel
        invitationId="invitation-1"
        invitationStatus="sent"
        quotation={quotationWithReference("Q-001", "USD")}
      />,
    );

    expect(screen.getByLabelText("Quotation reference")).toHaveValue("Q-001");
    expect(screen.getByLabelText("Currency")).toHaveValue("USD");

    rerender(
      <QuotationManualEntryPanel
        invitationId="invitation-1"
        invitationStatus="sent"
        quotation={quotationWithReference("Q-002", "MYR")}
      />,
    );

    expect(screen.getByLabelText("Quotation reference")).toHaveValue("Q-002");
    expect(screen.getByLabelText("Currency")).toHaveValue("MYR");
  });
});

function quotationWithReference(quotationReference: string, currency: string): Quotation {
  return {
    id: `quotation-${quotationReference}`,
    rfqId: "rfq-1",
    vendorId: "vendor-1",
    rfqInvitationId: "invitation-1",
    number: quotationReference,
    status: "received",
    submissionSource: "buyer_upload",
    submittedAt: null,
    latestReceivedAt: null,
    fileCount: 0,
    submittedByUser: null,
    submittedByVendorContact: null,
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
      buyerNotes: null,
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
      canCreateRevision: true,
    },
    versionCount: 0,
    currentVersion: null,
  };
}
