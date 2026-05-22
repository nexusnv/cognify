import type {
  QuotationLineItem,
  QuotationNormalization,
  QuotationNormalizationSummary,
  QuotationVersion,
} from "@cognify/api-client/schemas";

export type ActiveNormalizationSummary = {
  id: string;
  status: QuotationNormalization["status"];
  normalizationRevision: number;
};

export type QuotationNormalizationFixture = QuotationNormalization & {
  updatedAt: string;
  lastJobError?: string | null;
  currentVersionLines: QuotationLineItem[];
  rfqLineItemIds: string[];
};

export type QuotationNormalizationSummaryFixture = QuotationNormalizationSummary & {
  updatedAt: string;
  lastJobError?: string | null;
};

const versionLines: QuotationLineItem[] = [
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
];

function baseNormalization(
  overrides: Partial<QuotationNormalizationFixture>,
): QuotationNormalizationFixture {
  return {
    id: "norm-base",
    status: "needs_review",
    normalizationRevision: 1,
    algorithmVersion: "rules-v1",
    updatedAt: "2026-05-22T09:15:00.000Z",
    source: {
      quotationId: "quotation-1",
      quotationVersionId: "quotation-version-1",
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
          sourceQuotationVersionId: "quotation-version-1",
          sourceLabel: "Quotation currency",
        },
      },
      {
        id: "field-total",
        fieldPath: "manualEntry.totalAmount",
        rawValue: "12,470.00",
        normalizedValue: "12470.00",
        dataType: "money",
        currency: "USD",
        confidence: "0.99",
        source: "manual_entry",
        provenance: {
          sourceQuotationVersionId: "quotation-version-1",
          sourceLabel: "Quoted total",
        },
      },
    ],
    lineGroups: [],
    attachments: [
      {
        id: "attachment-1",
        quotationVersionAttachmentId: "quotation-version-attachment-1",
        filename: "northwind-quote.pdf",
        mimeType: "application/pdf",
        extension: "pdf",
        sizeBytes: 24576,
        checksumSha256: "abc123",
        available: true,
        source: "quotation_version_attachment",
        uploadedAt: "2026-05-22T08:45:00.000Z",
        evidenceRole: "pricing_evidence",
        issueSummary: "Currency format requires buyer confirmation.",
      },
    ],
    issues: [
      {
        id: "issue-currency",
        severity: "blocking",
        fieldPath: "manualEntry.currency",
        issueCode: "currency_missing",
        message: "Confirm the quotation currency before approval.",
        rawValue: "usd$",
        suggestedValue: "USD",
        status: "open",
        resolvedByUserId: null,
        resolvedAt: null,
        resolutionNote: null,
      },
      {
        id: "issue-line-mapping",
        severity: "blocking",
        fieldPath: "lineItems.bundle",
        issueCode: "line_mapping_required",
        message: "Map the quotation bundle to an RFQ line before approval.",
        rawValue: "Developer laptop bundle",
        suggestedValue: null,
        status: "open",
        resolvedByUserId: null,
        resolvedAt: null,
        resolutionNote: null,
      },
      {
        id: "issue-payment-terms",
        severity: "warning",
        fieldPath: "manualEntry.paymentTerms",
        issueCode: "payment_terms_missing",
        message: "Payment terms were not captured in the submitted quotation.",
        rawValue: null,
        suggestedValue: null,
        status: "open",
        resolvedByUserId: null,
        resolvedAt: null,
        resolutionNote: null,
      },
      {
        id: "issue-attachment",
        severity: "info",
        fieldPath: "attachments.0",
        issueCode: "attachment_present",
        message: "Pricing evidence is attached.",
        rawValue: "northwind-quote.pdf",
        suggestedValue: null,
        status: "acknowledged",
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
    currentVersionLines: structuredClone(versionLines),
    rfqLineItemIds: ["rfq-line-1"],
    ...overrides,
  };
}

export const quotationNormalizationFixtures: QuotationNormalizationFixture[] = [
  baseNormalization({
    id: "norm-needs-review",
    updatedAt: "2026-05-22T09:15:00.000Z",
  }),
  baseNormalization({
    id: "norm-ready-for-approval",
    status: "ready_for_approval",
    updatedAt: "2026-05-22T08:10:00.000Z",
    summary: {
      blockingIssueCount: 0,
      warningIssueCount: 1,
      infoIssueCount: 0,
    },
    fields: [
      {
        id: "field-currency-ready",
        fieldPath: "manualEntry.currency",
        rawValue: "USD",
        normalizedValue: "USD",
        dataType: "string",
        currency: null,
        confidence: "1.00",
        source: "manual_entry",
        provenance: {
          sourceQuotationVersionId: "quotation-version-2",
          sourceLabel: "Quotation currency",
        },
      },
    ],
    issues: [
      {
        id: "issue-warning-ready",
        severity: "warning",
        fieldPath: "manualEntry.paymentTerms",
        issueCode: "payment_terms_missing",
        message: "Payment terms were not captured in the submitted quotation.",
        rawValue: null,
        suggestedValue: null,
        status: "open",
        resolvedByUserId: null,
        resolvedAt: null,
        resolutionNote: null,
      },
    ],
    lineGroups: [
      {
        id: "line-group-ready",
        groupNumber: 1,
        pricingMode: "bundle",
        description: "Developer laptop bundle",
        currency: "USD",
        bundleTotalAmount: "12470.00",
        notes: "Mapped from buyer review.",
        mappings: [
          {
            id: "mapping-ready",
            rfqLineItemId: "rfq-line-1",
            quotationVersionLineItemId: "quote-line-1",
            mappingType: "bundled",
            quantity: "10.0000",
            unit: "each",
            unitPrice: null,
            lineTotal: "12470.00",
            buyerNote: "Bundle mapping confirmed.",
          },
        ],
      },
    ],
    permissions: {
      canEdit: true,
      canApprove: true,
      canApproveWithWarnings: true,
      canRetry: false,
      canCreateRevision: false,
    },
  }),
  baseNormalization({
    id: "norm-failed",
    status: "failed",
    updatedAt: "2026-05-22T07:05:00.000Z",
    source: {
      quotationId: "quotation-2",
      quotationVersionId: "quotation-version-3",
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
    fields: [],
    lineGroups: [],
    attachments: [],
    issues: [],
    permissions: {
      canEdit: false,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: true,
      canCreateRevision: false,
    },
    lastJobError: "Normalizer could not parse the uploaded workbook.",
    currentVersionLines: [],
    rfqLineItemIds: [],
  }),
  baseNormalization({
    id: "norm-approved-with-warnings",
    status: "approved_with_warnings",
    updatedAt: "2026-05-21T15:40:00.000Z",
    summary: {
      blockingIssueCount: 0,
      warningIssueCount: 1,
      infoIssueCount: 0,
    },
    issues: [
      {
        id: "issue-warning-approved",
        severity: "warning",
        fieldPath: "manualEntry.paymentTerms",
        issueCode: "payment_terms_missing",
        message: "Payment terms were not captured in the submitted quotation.",
        rawValue: null,
        suggestedValue: null,
        status: "acknowledged",
        resolvedByUserId: "buyer-1",
        resolvedAt: "2026-05-21T15:40:00.000Z",
        resolutionNote: "Approved with commercial risk noted.",
      },
    ],
    lineGroups: [
      {
        id: "line-group-approved",
        groupNumber: 1,
        pricingMode: "bundle",
        description: "Developer laptop bundle",
        currency: "USD",
        bundleTotalAmount: "12470.00",
        notes: "Read-only approved bundle.",
        mappings: [
          {
            id: "mapping-approved",
            rfqLineItemId: "rfq-line-1",
            quotationVersionLineItemId: "quote-line-1",
            mappingType: "bundled",
            quantity: "10.0000",
            unit: "each",
            unitPrice: null,
            lineTotal: "12470.00",
            buyerNote: "Approved bundle mapping.",
          },
        ],
      },
    ],
    permissions: {
      canEdit: false,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: false,
      canCreateRevision: true,
    },
  }),
];

export function buildQuotationNormalizationSummaries(
  fixtures: QuotationNormalizationFixture[],
): QuotationNormalizationSummaryFixture[] {
  return fixtures.map((fixture) => ({
    id: fixture.id,
    status: fixture.status,
    normalizationRevision: fixture.normalizationRevision,
    algorithmVersion: fixture.algorithmVersion,
    source: fixture.source,
    summary: fixture.summary,
    permissions: fixture.permissions,
    updatedAt: fixture.updatedAt,
    lastJobError: fixture.lastJobError ?? null,
  }));
}

export function createQuotationNormalizationFixtureState() {
  return structuredClone(quotationNormalizationFixtures);
}

export function buildActiveNormalizationSummary(
  normalizationId = "norm-ready-for-approval",
): ActiveNormalizationSummary {
  const normalization = quotationNormalizationFixtures.find((fixture) => fixture.id === normalizationId);
  if (!normalization) {
    throw new Error(`Missing quotation normalization fixture: ${normalizationId}`);
  }

  return {
    id: normalization.id,
    status: normalization.status,
    normalizationRevision: normalization.normalizationRevision,
  };
}

export function buildQuotationVersionWithActiveNormalization(
  activeNormalization = buildActiveNormalizationSummary(),
): QuotationVersion {
  return {
    id: "quotation-version-2",
    quotationId: "quotation-1",
    versionNumber: 2,
    status: "received",
    source: "buyer_upload",
    submittedAt: "2026-05-22T08:30:00.000Z",
    submittedByUser: { id: "buyer-1", name: "Priya Buyer" },
    submittedByVendorContact: null,
    isCurrent: true,
    supersededAt: null,
    previousVersionId: "quotation-version-1",
    manualEntry: {
      quotationReference: "NW-Q-2026-041-R2",
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
      complianceNotes: "Within scope",
      buyerNotes: "Buyer-reviewed quote.",
      vendorNotes: null,
    },
    lineItems: structuredClone(versionLines),
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
    activeNormalization,
  } as QuotationVersion;
}
