import type {
  QuotationComparison,
  QuotationComparisonCommercialTerm,
  QuotationComparisonLineRow,
  QuotationComparisonNote,
  QuotationComparisonNoteGroup,
  QuotationComparisonPermissions,
  QuotationComparisonVendor,
} from "@cognify/api-client/schemas";
import {
  QuotationComparisonCommercialTermValueReadiness,
  QuotationComparisonVendorCellReadiness,
  QuotationComparisonVendorReadiness,
} from "@cognify/api-client/schemas";

type ComparisonFixtureState = {
  comparison: QuotationComparison;
  nextNoteSequence: number;
};

const noteTime = "2026-05-22T11:00:00.000Z";

function basePermissions(): QuotationComparisonPermissions {
  return {
    canViewComparison: true,
    canManageQuotationComparisonNotes: true,
  };
}

function baseNote(overrides: Partial<QuotationComparisonNote>): QuotationComparisonNote {
  return {
    id: "comparison-note-1",
    rfqId: "rfq-ready",
    section: "overall",
    note: "Executive summary is available.",
    createdByUserId: "buyer-1",
    createdAt: noteTime,
    updatedAt: noteTime,
    ...overrides,
  };
}

function groupNotes(notes: QuotationComparisonNote[]): QuotationComparisonNoteGroup[] {
  const groups = new Map<string, QuotationComparisonNoteGroup>();

  for (const note of notes) {
    const key = [note.section, note.quotationId ?? "", note.vendorId ?? "", note.rfqLineItemId ?? ""].join("|");
    const existing = groups.get(key);
    if (existing) {
      existing.notes.push(note);
      continue;
    }

    groups.set(key, {
      section: note.section,
      quotationId: note.quotationId,
      vendorId: note.vendorId,
      rfqLineItemId: note.rfqLineItemId,
      notes: [note],
    });
  }

  return Array.from(groups.values());
}

function refreshVendorNoteCounts(comparison: QuotationComparison) {
  for (const vendor of comparison.vendors) {
    vendor.noteCount = comparison.notes.filter((note) => {
      if (note.vendorId || note.quotationId) {
        return note.vendorId === vendor.vendorId || note.quotationId === vendor.quotationId;
      }

      return Boolean(note.rfqLineItemId);
    }).length;
  }
}

function refreshNoteGroups(comparison: QuotationComparison) {
  comparison.noteGroups = groupNotes(comparison.notes);
}

function refreshComparison(comparison: QuotationComparison) {
  refreshVendorNoteCounts(comparison);
  refreshNoteGroups(comparison);
}

function buildVendor(
  overrides: Partial<QuotationComparisonVendor>,
): QuotationComparisonVendor {
  return {
    vendorId: "vendor-1",
    vendorName: "Northwind Traders",
    quotationId: "quotation-1",
    quotationNumber: "QT-2026-041",
    quotationVersionId: "quotation-version-1",
    normalizationId: "norm-ready-for-approval",
    normalizationRevision: 1,
    readiness: QuotationComparisonVendorReadiness.ready,
    currency: "USD",
    totalAmount: "12470.00",
    leadTimeDays: 14,
    paymentTerms: "Net 30",
    deliveryTerms: "DDP",
    warrantyTerms: "3 years",
    complianceNotes: "Meets all mandatory requirements.",
    issueCounts: { blocking: 0, warning: 0, info: 0 },
    noteCount: 1,
    links: {
      quotationVersion: "/app/quotations/quotation-1/versions/1",
      normalization: "/app/quotations/normalizations/norm-ready-for-approval",
    },
    ...overrides,
  };
}

function buildLineRow(overrides: Partial<QuotationComparisonLineRow>): QuotationComparisonLineRow {
  return {
    rfqLineItemId: "rfq-line-1",
    name: "Developer laptops",
    description: "Laptop bundle for engineering staff.",
    quantity: 10,
    unit: "each",
    vendorCells: [
      {
        vendorId: "vendor-1",
        readiness: QuotationComparisonVendorCellReadiness.ready,
        value: "12470.00",
        description: "Dell Latitude 5450",
        pricingMode: "bundle",
        currency: "USD",
        quantity: "10",
        unit: "each",
        unitPrice: "1247.00",
        lineTotal: "12470.00",
        bundleTotalAmount: "12470.00",
        buyerNote: "Preferred bundle already accepted.",
      },
    ],
    ...overrides,
  };
}

function buildCommercialTerm(overrides: Partial<QuotationComparisonCommercialTerm>): QuotationComparisonCommercialTerm {
  return {
    id: "payment-terms",
    label: "Payment terms",
    vendorValues: [
      {
        vendorId: "vendor-1",
        value: "Net 30",
        readiness: QuotationComparisonCommercialTermValueReadiness.ready,
      },
    ],
    ...overrides,
  };
}

function buildComparison(overrides: Partial<QuotationComparison>): QuotationComparison {
  const comparison: QuotationComparison = {
    rfq: {
      id: "rfq-ready",
      number: "RFQ-2026-000041",
      title: "Laptop refresh program",
      status: "open",
      responseDueAt: "2026-05-30T00:00:00.000Z",
      scopeSummary: "Three vendors submitted comparable laptop bundles.",
      requisition: {
        id: "requisition-1",
        number: "REQ-2026-001",
        title: "Laptop refresh",
        status: "submitted",
        department: "Technology",
        neededByDate: "2026-06-15",
        currency: "USD",
        requester: {
          id: "user-1",
          name: "Priya Buyer",
          email: "priya@example.com",
        },
      },
      project: {
        id: "project-1",
        number: "PRJ-2026-010",
        name: "End-user compute refresh",
        status: "active",
      },
    },
    readiness: {
      responseCount: 3,
      approvedNormalizationCount: 3,
      pendingNormalizationCount: 0,
      missingResponseCount: 0,
      mixedCurrency: false,
    },
    vendors: [
      buildVendor({ vendorId: "vendor-1", vendorName: "Northwind Traders" }),
      buildVendor({
        vendorId: "vendor-2",
        vendorName: "Globex Corporation",
        quotationId: "quotation-2",
        quotationNumber: "QT-2026-042",
        quotationVersionId: "quotation-version-2",
        normalizationId: "norm-ready-for-approval",
        normalizationRevision: 1,
      }),
      buildVendor({
        vendorId: "vendor-3",
        vendorName: "Initech",
        quotationId: "quotation-3",
        quotationNumber: "QT-2026-043",
        quotationVersionId: "quotation-version-3",
        normalizationId: "norm-ready-for-approval",
        normalizationRevision: 1,
      }),
    ],
    lineRows: [buildLineRow({})],
    commercialTerms: [buildCommercialTerm({})],
    notes: [
      baseNote({
        id: "comparison-note-1",
        rfqId: "rfq-ready",
        section: "overall",
        note: "Executive summary is available.",
      }),
      baseNote({
        id: "comparison-note-2",
        rfqId: "rfq-ready",
        section: "price",
        quotationId: "quotation-1",
        vendorId: "vendor-1",
        note: "Northwind is the lowest-cost ready response.",
      }),
    ],
    noteGroups: [],
    permissions: basePermissions(),
    ...overrides,
  };

  refreshComparison(comparison);
  return comparison;
}

function cloneComparison(comparison: QuotationComparison): QuotationComparison {
  return structuredClone(comparison);
}

function createReadyComparison(): ComparisonFixtureState {
  return {
    comparison: buildComparison({}),
    nextNoteSequence: 3,
  };
}

function createMixedReadinessComparison(): ComparisonFixtureState {
  const comparison = buildComparison({
    rfq: {
      id: "rfq-mixed-readiness",
      number: "RFQ-2026-000042",
      title: "Laptop refresh with partial readiness",
      status: "open",
      responseDueAt: "2026-05-30T00:00:00.000Z",
      scopeSummary: "One vendor is still waiting on normalization.",
      requisition: {
        id: "requisition-1",
        number: "REQ-2026-001",
        title: "Laptop refresh",
        status: "submitted",
        department: "Technology",
        neededByDate: "2026-06-15",
        currency: "USD",
        requester: {
          id: "user-1",
          name: "Priya Buyer",
          email: "priya@example.com",
        },
      },
      project: {
        id: "project-1",
        number: "PRJ-2026-010",
        name: "End-user compute refresh",
        status: "active",
      },
    },
    readiness: {
      responseCount: 3,
      approvedNormalizationCount: 1,
      pendingNormalizationCount: 2,
      missingResponseCount: 0,
      mixedCurrency: false,
    },
    vendors: [
      buildVendor({
        vendorId: "vendor-1",
        vendorName: "Northwind Traders",
        readiness: QuotationComparisonVendorReadiness.ready,
        normalizationId: "norm-ready-for-approval",
        issueCounts: { blocking: 0, warning: 0, info: 0 },
      }),
      buildVendor({
        vendorId: "vendor-2",
        vendorName: "Globex Corporation",
        quotationId: "quotation-2",
        quotationNumber: "QT-2026-042",
        quotationVersionId: "quotation-version-2",
        normalizationId: "norm-mixed-readiness",
        normalizationRevision: 2,
        readiness: QuotationComparisonVendorReadiness.normalization_required,
        currency: "USD",
        totalAmount: "13140.00",
        leadTimeDays: 18,
        issueCounts: { blocking: 1, warning: 1, info: 0 },
        noteCount: 0,
      }),
      buildVendor({
        vendorId: "vendor-3",
        vendorName: "Initech",
        quotationId: "quotation-3",
        quotationNumber: "QT-2026-043",
        quotationVersionId: "quotation-version-3",
        readiness: QuotationComparisonVendorReadiness.normalization_required,
        normalizationId: "norm-mixed-readiness-2",
        normalizationRevision: 1,
        currency: "USD",
        totalAmount: "13990.00",
        leadTimeDays: 21,
        issueCounts: { blocking: 2, warning: 1, info: 1 },
        noteCount: 0,
      }),
    ],
    lineRows: [
      buildLineRow({
        vendorCells: [
          {
            vendorId: "vendor-1",
            readiness: QuotationComparisonVendorCellReadiness.ready,
            value: "12470.00",
            description: "Dell Latitude 5450",
            pricingMode: "bundle",
            currency: "USD",
            quantity: "10",
            unit: "each",
            unitPrice: "1247.00",
            lineTotal: "12470.00",
            bundleTotalAmount: "12470.00",
            buyerNote: "Ready quote.",
          },
          {
            vendorId: "vendor-2",
            readiness: QuotationComparisonVendorCellReadiness.normalization_required,
            value: "13140.00",
            description: "HP EliteBook 840",
            pricingMode: "bundle",
            currency: "USD",
            quantity: "10",
            unit: "each",
            unitPrice: "1314.00",
            lineTotal: "13140.00",
            bundleTotalAmount: "13140.00",
            buyerNote: "Needs normalization.",
          },
          {
            vendorId: "vendor-3",
            readiness: QuotationComparisonVendorCellReadiness.unmapped,
            value: null,
            description: "ThinkPad T14",
            pricingMode: "bundle",
            currency: "USD",
            quantity: "10",
            unit: "each",
            unitPrice: null,
            lineTotal: null,
            bundleTotalAmount: null,
            buyerNote: null,
          },
        ],
      }),
    ],
    commercialTerms: [
      buildCommercialTerm({
        vendorValues: [
          {
            vendorId: "vendor-1",
            value: "Net 30",
            readiness: QuotationComparisonCommercialTermValueReadiness.ready,
          },
          {
            vendorId: "vendor-2",
            value: "Net 45",
            readiness: QuotationComparisonCommercialTermValueReadiness.normalization_required,
          },
          {
            vendorId: "vendor-3",
            value: null,
            readiness: QuotationComparisonCommercialTermValueReadiness.normalization_required,
          },
        ],
      }),
    ],
    notes: [
      baseNote({
        id: "comparison-note-1",
        rfqId: "rfq-mixed-readiness",
        section: "overall",
        note: "One vendor still needs pricing normalization.",
      }),
      baseNote({
        id: "comparison-note-2",
        rfqId: "rfq-mixed-readiness",
        section: "risk",
        vendorId: "vendor-2",
        note: "Follow up on the pending normalization before award.",
      }),
    ],
    permissions: basePermissions(),
  });

  refreshComparison(comparison);
  return {
    comparison,
    nextNoteSequence: 3,
  };
}

function createMixedCurrencyComparison(): ComparisonFixtureState {
  const comparison = buildComparison({
    rfq: {
      id: "rfq-mixed-currency",
      number: "RFQ-2026-000043",
      title: "Laptop refresh with mixed currency offers",
      status: "open",
      responseDueAt: "2026-05-30T00:00:00.000Z",
      scopeSummary: "Responses are ready, but currencies differ.",
      requisition: {
        id: "requisition-1",
        number: "REQ-2026-001",
        title: "Laptop refresh",
        status: "submitted",
        department: "Technology",
        neededByDate: "2026-06-15",
        currency: "USD",
        requester: {
          id: "user-1",
          name: "Priya Buyer",
          email: "priya@example.com",
        },
      },
      project: {
        id: "project-1",
        number: "PRJ-2026-010",
        name: "End-user compute refresh",
        status: "active",
      },
    },
    readiness: {
      responseCount: 2,
      approvedNormalizationCount: 2,
      pendingNormalizationCount: 0,
      missingResponseCount: 0,
      mixedCurrency: true,
    },
    vendors: [
      buildVendor({
        vendorId: "vendor-1",
        vendorName: "Northwind Traders",
        currency: "USD",
        totalAmount: "12470.00",
        readiness: QuotationComparisonVendorReadiness.ready,
        issueCounts: { blocking: 0, warning: 0, info: 0 },
      }),
      buildVendor({
        vendorId: "vendor-2",
        vendorName: "Globex Corporation",
        quotationId: "quotation-2",
        quotationNumber: "QT-2026-042",
        quotationVersionId: "quotation-version-2",
        normalizationId: "norm-mixed-currency",
        normalizationRevision: 1,
        currency: "EUR",
        totalAmount: "11900.00",
        leadTimeDays: 16,
        readiness: QuotationComparisonVendorReadiness.ready,
        issueCounts: { blocking: 0, warning: 0, info: 0 },
        links: {
          quotationVersion: "/app/quotations/quotation-2/versions/2",
          normalization: "/app/quotations/normalizations/norm-mixed-currency",
        },
      }),
    ],
    lineRows: [
      buildLineRow({
        vendorCells: [
          {
            vendorId: "vendor-1",
            readiness: QuotationComparisonVendorCellReadiness.ready,
            value: "12470.00",
            description: "Dell Latitude 5450",
            pricingMode: "bundle",
            currency: "USD",
            quantity: "10",
            unit: "each",
            unitPrice: "1247.00",
            lineTotal: "12470.00",
            bundleTotalAmount: "12470.00",
            buyerNote: "USD quote.",
          },
          {
            vendorId: "vendor-2",
            readiness: QuotationComparisonVendorCellReadiness.ready,
            value: "11900.00",
            description: "Dell Latitude 5450",
            pricingMode: "bundle",
            currency: "EUR",
            quantity: "10",
            unit: "each",
            unitPrice: "1190.00",
            lineTotal: "11900.00",
            bundleTotalAmount: "11900.00",
            buyerNote: "EUR quote.",
          },
        ],
      }),
    ],
    commercialTerms: [
      buildCommercialTerm({
        vendorValues: [
          {
            vendorId: "vendor-1",
            value: "Net 30",
            readiness: QuotationComparisonCommercialTermValueReadiness.ready,
          },
          {
            vendorId: "vendor-2",
            value: "Net 30",
            readiness: QuotationComparisonCommercialTermValueReadiness.ready,
          },
        ],
      }),
    ],
    notes: [
      baseNote({
        id: "comparison-note-1",
        rfqId: "rfq-mixed-currency",
        section: "price",
        note: "The comparison should surface currency differences prominently.",
      }),
    ],
    permissions: basePermissions(),
  });

  refreshComparison(comparison);
  return {
    comparison,
    nextNoteSequence: 2,
  };
}

function createQuotationComparisonFixtureState(): ComparisonFixtureState[] {
  return [createReadyComparison(), createMixedReadinessComparison(), createMixedCurrencyComparison()];
}

let comparisonState = createQuotationComparisonFixtureState();

export function resetQuotationComparisonMockState() {
  comparisonState = createQuotationComparisonFixtureState();
}

resetQuotationComparisonMockState();

function findComparisonRecord(rfqId: string) {
  return comparisonState.find((entry) => entry.comparison.rfq.id === rfqId) ?? null;
}

export function getQuotationComparisonFixture(rfqId: string): QuotationComparison | null {
  const record = findComparisonRecord(rfqId);
  return record ? cloneComparison(record.comparison) : null;
}

export function listQuotationComparisonFixtures(): QuotationComparison[] {
  return comparisonState.map((record) => cloneComparison(record.comparison));
}

export function createQuotationComparisonNoteFixture(
  rfqId: string,
  payload: {
    section: QuotationComparisonNote["section"];
    note: string;
    quotationId?: string;
    vendorId?: string;
    rfqLineItemId?: string;
  },
): QuotationComparisonNote {
  const record = findComparisonRecord(rfqId);
  if (!record) {
    throw new Error(`Missing quotation comparison fixture: ${rfqId}`);
  }

  const note = baseNote({
    id: `comparison-note-${record.nextNoteSequence}`,
    rfqId,
    section: payload.section,
    note: payload.note,
    quotationId: payload.quotationId,
    vendorId: payload.vendorId,
    rfqLineItemId: payload.rfqLineItemId,
    createdAt: noteTime,
    updatedAt: noteTime,
  });
  record.nextNoteSequence += 1;
  record.comparison.notes = [...record.comparison.notes, note];
  refreshComparison(record.comparison);
  return structuredClone(note);
}

export function updateQuotationComparisonNoteFixture(
  rfqId: string,
  noteId: string,
  payload: {
    section: QuotationComparisonNote["section"];
    note: string;
    quotationId?: string;
    vendorId?: string;
    rfqLineItemId?: string;
  },
): QuotationComparisonNote {
  const record = findComparisonRecord(rfqId);
  if (!record) {
    throw new Error(`Missing quotation comparison fixture: ${rfqId}`);
  }

  const note = record.comparison.notes.find((entry) => entry.id === noteId);
  if (!note) {
    throw new Error(`Missing quotation comparison note fixture: ${noteId}`);
  }

  note.section = payload.section;
  note.note = payload.note;
  note.quotationId = payload.quotationId;
  note.vendorId = payload.vendorId;
  note.rfqLineItemId = payload.rfqLineItemId;
  note.updatedAt = noteTime;
  refreshComparison(record.comparison);
  return structuredClone(note);
}

export function deleteQuotationComparisonNoteFixture(rfqId: string, noteId: string): void {
  const record = findComparisonRecord(rfqId);
  if (!record) {
    throw new Error(`Missing quotation comparison fixture: ${rfqId}`);
  }

  const initialLength = record.comparison.notes.length;
  record.comparison.notes = record.comparison.notes.filter((entry) => entry.id !== noteId);
  if (record.comparison.notes.length === initialLength) {
    throw new Error(`Missing quotation comparison note fixture: ${noteId}`);
  }

  refreshComparison(record.comparison);
}
