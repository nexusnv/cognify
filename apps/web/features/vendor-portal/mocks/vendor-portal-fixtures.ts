import type {
  AttachmentVendorPortal,
  QuotationVendorPortal,
  VendorPortalRfqInvitation,
} from "@cognify/api-client/schemas";

export const validVendorPortalToken = "vendor-portal-valid-token";
export const expiredVendorPortalToken = "vendor-portal-expired-token";
export const unavailableVendorPortalToken = "vendor-portal-unavailable-token";

export const vendorPortalRfqInvitationFixture: VendorPortalRfqInvitation = {
  invitation: {
    id: "1",
    status: "sent",
    responseDueAt: "2026-06-30T17:00:00.000000Z",
    message: "Please review the RFQ package and confirm your interest.",
    portalExpiresAt: "2026-06-30T17:00:00.000000Z",
  },
  tenant: {
    name: "Acme Procurement",
  },
  vendor: {
    id: "1",
    name: "Northwind Traders",
    contactName: "Nina Northwind",
    contactEmail: "nina@northwind.test",
  },
  rfq: {
    id: "rfq-1",
    number: "RFQ-2026-000001",
    title: "Field laptop refresh RFQ",
    scopeSummary: "Supply and deliver laptops for field teams.",
    responseDueAt: "2026-06-30T17:00:00.000000Z",
    responseInstructions: "Submit pricing, warranty, and delivery terms.",
    requiredDocuments: [
      { key: "quote_pdf", label: "Quotation PDF", required: true },
      { key: "company_profile", label: "Company profile", required: false },
    ],
    lineItems: [
      {
        name: "Developer laptop",
        description: "Developer laptop",
        quantity: 10,
        unit: "each",
        notes: "16GB RAM minimum",
        unitOfMeasure: null,
        estimatedUnitPrice: null,
        currency: null,
      },
    ],
  },
};

export const vendorPortalQuotationFixture: QuotationVendorPortal | null = null;

let vendorPortalQuotation: QuotationVendorPortal | null = structuredClone(vendorPortalQuotationFixture);
let vendorPortalQuotationAttachmentSequence = 0;

export function resetVendorPortalMockState() {
  vendorPortalQuotation = structuredClone(vendorPortalQuotationFixture);
  vendorPortalQuotationAttachmentSequence = 0;
}

export function getVendorPortalQuotationFixture() {
  return vendorPortalQuotation;
}

export function setVendorPortalQuotationFixture(nextQuotation: QuotationVendorPortal | null) {
  vendorPortalQuotation = structuredClone(nextQuotation);
  vendorPortalQuotationAttachmentSequence = nextQuotation?.attachments.length ?? 0;
}

export function appendVendorPortalQuotationAttachment(attachment: AttachmentVendorPortal) {
  if (!vendorPortalQuotation) {
    vendorPortalQuotation = buildVendorPortalQuotationFixture([attachment]);
    vendorPortalQuotationAttachmentSequence = 1;
    return vendorPortalQuotation;
  }

  vendorPortalQuotationAttachmentSequence += 1;
  vendorPortalQuotation = {
    ...vendorPortalQuotation,
    status: "received",
    submissionSource: "vendor_portal",
    submittedAt: new Date().toISOString(),
    latestReceivedAt: new Date().toISOString(),
    fileCount: vendorPortalQuotationAttachmentSequence,
    attachments: [attachment, ...vendorPortalQuotation.attachments],
  };

  return vendorPortalQuotation;
}

export function buildVendorPortalQuotationFixture(
  attachments: AttachmentVendorPortal[] = [],
): QuotationVendorPortal {
  return {
    id: "quotation-1",
    rfqId: "rfq-1",
    vendorId: "1",
    rfqInvitationId: "1",
    number: "QTN-2026-000001",
    status: "received",
    submissionSource: "vendor_portal",
    submittedAt: "2026-06-01T10:00:00.000000Z",
    latestReceivedAt: "2026-06-01T10:00:00.000000Z",
    fileCount: attachments.length,
    submittedByUser: null,
    submittedByVendorContact: null,
    attachments,
    permissions: {
      canUploadAttachment: true,
      canViewAttachments: false,
    },
  };
}
