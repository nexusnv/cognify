import type { VendorPortalRfqInvitation } from "@cognify/api-client/schemas";

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
