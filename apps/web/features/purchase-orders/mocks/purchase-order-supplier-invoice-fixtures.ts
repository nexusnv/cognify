import type { Attachment, SupplierInvoice } from "@cognify/api-client/schemas";

let supplierInvoiceSequence = 1;
let supplierInvoiceAttachmentSequence = 1;

export function resetSupplierInvoiceFixtureState() {
  supplierInvoiceSequence = 1;
  supplierInvoiceAttachmentSequence = 1;
}

export function buildSupplierInvoiceFixture(overrides: Partial<SupplierInvoice> = {}): SupplierInvoice {
  const sequence = supplierInvoiceSequence++;
  const base: SupplierInvoice = {
    id: `supplier-invoice-${sequence}`,
    purchaseOrderId: "po-1",
    vendorId: "vendor-1",
    number: `SI-2026-${String(sequence).padStart(6, "0")}`,
    invoiceNumber: `INV-${String(20000 + sequence)}`,
    status: "captured",
    invoiceDate: "2026-06-11",
    dueDate: "2026-07-11",
    currency: "MYR",
    subtotalAmount: "120000.00",
    taxAmount: "7200.00",
    freightAmount: "3900.00",
    totalAmount: "131100.00",
    notes: "Supplier invoice received by AP.",
    capturedByUserId: "user-1",
    capturedAt: "2026-06-11T10:00:00Z",
    purchaseOrder: {
      id: "po-1",
      number: "PO-2026-000001",
    },
    vendor: {
      id: "vendor-1",
      name: "Northwind Traders",
    },
    attachmentCount: 0,
    reviewStartedByUserId: null,
    reviewStartedAt: null,
    reviewedByUserId: null,
    reviewedAt: null,
    reviewNotes: null,
    reviewChecklist: null,
    reviewChecklistSummary: {
      total: 5,
      passed: 0,
      needsAttention: 0,
      failed: 0,
    },
    reviewBlockers: [],
    reviewBlockerCount: 0,
    permissions: {
      canReview: true,
    },
    lockVersion: 1,
    paymentStatus: null,
    lines: [
      {
        id: `supplier-invoice-line-${sequence}`,
        purchaseOrderLineId: "po-line-1",
        lineNumber: 1,
        descriptionSnapshot: "Pallet rack bay",
        quantityOrdered: "10.0000",
        quantityInvoiced: "10.0000",
        unitPrice: "12000.0000",
        lineSubtotal: "120000.00",
        notes: "Matches PO line.",
      },
    ],
  };

  return {
    ...base,
    ...overrides,
    lines: overrides.lines ?? base.lines,
  };
}

export function buildSupplierInvoiceAttachmentFixture(overrides: Partial<Attachment> = {}): Attachment {
  const sequence = supplierInvoiceAttachmentSequence++;
  const base: Attachment = {
    id: `supplier-invoice-attachment-${sequence}`,
    parentType: "supplier_invoice",
    parentId: "supplier-invoice-1",
    filename: `invoice-support-${sequence}.pdf`,
    mimeType: "application/pdf",
    extension: "pdf",
    sizeBytes: 248_000,
    previewable: true,
    uploadedBy: {
      id: "user-1",
      name: "Maya Tan",
    },
    createdAt: "2026-06-11T10:05:00Z",
    permissions: {
      canPreview: true,
      canDownload: true,
      canDelete: false,
    },
  };

  return { ...base, ...overrides };
}
