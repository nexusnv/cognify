import type {
  AttachmentVendorPortal,
  QuotationVendorPortal,
  SaveQuotationManualEntryRequestForVendor,
  VendorCreateQuotationRevisionRequest,
  VendorPortalRfqInvitation,
  VendorQuotationVersion,
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
let vendorPortalQuotationVersions: VendorQuotationVersion[] = [];

export function resetVendorPortalMockState() {
  vendorPortalQuotation = structuredClone(vendorPortalQuotationFixture);
  vendorPortalQuotationAttachmentSequence = 0;
  vendorPortalQuotationVersions = [];
}

export function getVendorPortalQuotationFixture() {
  return vendorPortalQuotation;
}

export function setVendorPortalQuotationFixture(nextQuotation: QuotationVendorPortal | null) {
  vendorPortalQuotation = structuredClone(nextQuotation);
  vendorPortalQuotationAttachmentSequence = nextQuotation?.attachments.length ?? 0;
}

export function getVendorPortalQuotationVersionsFixture() {
  return vendorPortalQuotationVersions;
}

export function appendVendorPortalQuotationAttachment(attachment: AttachmentVendorPortal) {
  if (!vendorPortalQuotation) {
    vendorPortalQuotation = buildVendorPortalQuotationFixture([attachment]);
    vendorPortalQuotationAttachmentSequence = 1;
    appendVendorPortalQuotationVersionSnapshot(vendorPortalQuotation);
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

  appendVendorPortalQuotationVersionSnapshot(vendorPortalQuotation);
  return vendorPortalQuotation;
}

export function updateVendorPortalQuotationManualEntry(payload: SaveQuotationManualEntryRequestForVendor) {
  const quotation = applyVendorPortalQuotationManualEntry(payload);
  appendVendorPortalQuotationVersionSnapshot(quotation);

  return quotation;
}

function applyVendorPortalQuotationManualEntry(payload: SaveQuotationManualEntryRequestForVendor) {
  if (!vendorPortalQuotation) {
    vendorPortalQuotation = buildVendorPortalQuotationFixture();
  }

  const lineItems = (payload.lineItems ?? []).map((lineItem, index) => ({
    id: `quotation-line-${index + 1}`,
    rfqLineItemId: lineItem.rfqLineItemId ?? null,
    description: lineItem.description,
    quantity: lineItem.quantity,
    unit: lineItem.unit ?? null,
    unitPrice: lineItem.unitPrice ?? null,
    subtotalAmount: lineItem.subtotalAmount ?? null,
    taxAmount: lineItem.taxAmount ?? null,
    totalAmount: lineItem.totalAmount ?? null,
    leadTimeDays: lineItem.leadTimeDays ?? null,
    manufacturer: lineItem.manufacturer ?? null,
    modelNumber: lineItem.modelNumber ?? null,
    alternateOffered: lineItem.alternateOffered ?? false,
    complianceStatus: lineItem.complianceStatus ?? null,
    notes: lineItem.notes ?? null,
  }));
  const missingFields = [
    payload.currency?.trim() ? null : "currency",
    payload.totalAmount?.trim() ? null : "totalAmount",
    lineItems.length > 0 ? null : "lineItems",
  ].filter((field): field is string => Boolean(field));

  vendorPortalQuotation = {
    ...vendorPortalQuotation,
    status: "received",
    submissionSource: "vendor_portal",
    submittedAt: vendorPortalQuotation.submittedAt ?? new Date().toISOString(),
    latestReceivedAt: new Date().toISOString(),
    manualEntry: {
      quotationReference: payload.quotationReference ?? null,
      quotedAt: payload.quotedAt ?? null,
      validUntil: payload.validUntil ?? null,
      currency: payload.currency ?? null,
      subtotalAmount: payload.subtotalAmount ?? null,
      taxAmount: payload.taxAmount ?? null,
      freightAmount: payload.freightAmount ?? null,
      discountAmount: payload.discountAmount ?? null,
      totalAmount: payload.totalAmount ?? null,
      paymentTerms: payload.paymentTerms ?? null,
      deliveryTerms: payload.deliveryTerms ?? null,
      leadTimeDays: payload.leadTimeDays ?? null,
      warrantyTerms: payload.warrantyTerms ?? null,
      exclusions: payload.exclusions ?? null,
      complianceNotes: payload.complianceNotes ?? null,
      vendorNotes: payload.vendorNotes ?? null,
    },
    lineItems,
    completeness: {
      isComplete: missingFields.length === 0,
      missingFields,
      lineItemCount: lineItems.length,
    },
    permissions: {
      ...vendorPortalQuotation.permissions,
      canUploadAttachment: true,
      canViewAttachments: true,
      canEditManualEntry: true,
    },
  };

  return vendorPortalQuotation;
}

export function appendVendorPortalQuotationVersion(payload: VendorCreateQuotationRevisionRequest) {
  const vendorPayload = { ...payload };
  delete vendorPayload.attachmentIds;
  const quotation = applyVendorPortalQuotationManualEntry(vendorPayload);
  return appendVendorPortalQuotationVersionSnapshot(quotation);
}

function appendVendorPortalQuotationVersionSnapshot(quotation: QuotationVendorPortal) {
  const previousVersion = vendorPortalQuotationVersions[0] ?? null;
  const now = new Date().toISOString();
  const attachments = quotation.attachments.map((attachment) => ({
    id: attachment.id,
    filename: attachment.filename,
    mimeType: attachment.mimeType,
    extension: attachment.extension,
    sizeBytes: attachment.sizeBytes,
    checksumSha256: null,
    previewable: attachment.previewable,
    createdAt: attachment.createdAt,
    available: true,
  }));
  const manualEntry = quotation.manualEntry;

  if (
    previousVersion &&
    sameVendorPortalVersionSnapshot(previousVersion, manualEntry, quotation.lineItems, attachments)
  ) {
    return previousVersion;
  }

  vendorPortalQuotationVersions = [
    {
      id: `vendor-quotation-version-${vendorPortalQuotationVersions.length + 1}`,
      quotationId: quotation.id,
      versionNumber: vendorPortalQuotationVersions.length + 1,
      status: "received",
      source: "vendor_portal",
      submittedAt: now,
      submittedByVendorContact: quotation.submittedByVendorContact,
      isCurrent: true,
      supersededAt: null,
      previousVersionId: previousVersion?.id ?? null,
      manualEntry,
      lineItems: quotation.lineItems,
      attachments,
      attachmentCount: quotation.attachments.length,
      completeness: quotation.completeness,
      permissions: {
        canEdit: false,
        canCreateRevision: true,
      },
    },
    ...vendorPortalQuotationVersions.map((version) => ({
      ...version,
      isCurrent: false,
      supersededAt: now,
    })),
  ];

  return vendorPortalQuotationVersions[0];
}

function sameVendorPortalVersionSnapshot(
  version: VendorQuotationVersion,
  manualEntry: VendorQuotationVersion["manualEntry"],
  lineItems: VendorQuotationVersion["lineItems"],
  attachments: VendorQuotationVersion["attachments"],
) {
  return (
    JSON.stringify(version.manualEntry) === JSON.stringify(manualEntry) &&
    JSON.stringify(version.lineItems) === JSON.stringify(lineItems) &&
    JSON.stringify(version.attachments) === JSON.stringify(attachments)
  );
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
    submittedByVendorContact: {
      name: "Nina Northwind",
      email: "nina@northwind.test",
    },
    attachments,
    manualEntry: {
      quotationReference: null,
      quotedAt: null,
      validUntil: null,
      currency: null,
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
      missingFields: ["currency", "totalAmount", "lineItems"],
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
