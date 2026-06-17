import type { SupplierInvoiceException } from "@cognify/api-client/schemas";

export function buildExceptionFixture(overrides: Partial<SupplierInvoiceException> = {}): SupplierInvoiceException {
  return {
    id: "exception-1",
    supplierInvoiceId: "invoice-mismatch-1",
    dimension: "unit_price",
    matchType: "two_way",
    supplierInvoiceLineId: "line-1",
    purchaseOrderLineId: "po-line-1",
    expectedValue: "100.0000",
    actualValue: "150.0000",
    status: "open",
    resolutionType: null,
    resolutionData: null,
    resolvedByUserId: null,
    resolvedAt: null,
    escalatedToUserId: null,
    escalatedByUserId: null,
    escalatedAt: null,
    escalationNote: null,
    lockVersion: 1,
    createdAt: "2026-06-17T12:00:00Z",
    ...overrides,
  };
}

export const invoiceExceptionFixtures = {
  mismatchInvoice: {
    id: "invoice-mismatch-1",
    number: "INV-MISMATCH-001",
    status: "mismatch",
    matchingStatus: "mismatch",
    exceptionSummary: { total: 2, open: 2, resolved: 0, escalated: 0 },
  },
  exceptions: [
    buildExceptionFixture({ id: "exc-1", dimension: "unit_price" }),
    buildExceptionFixture({
      id: "exc-2",
      dimension: "line_total",
      expectedValue: "500.0000",
      actualValue: "750.0000",
    }),
  ],
};
