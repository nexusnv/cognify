import type { PurchaseOrder, PurchaseOrderListResponse } from "@cognify/api-client/schemas";

export const purchaseOrderFixture: PurchaseOrder = {
  id: "po-1",
  number: "PO-2026-000001",
  status: "draft",
  currency: "MYR",
  subtotalAmount: "120000.00",
  taxAmount: "7200.00",
  freightAmount: "3900.00",
  discountAmount: "0.00",
  totalAmount: "131100.00",
  requestedPoDate: "2026-06-18",
  expectedDeliveryDate: "2026-07-02",
  billingName: "Acme Finance",
  billingAddress: { line1: "Level 10", city: "Kuala Lumpur", country: "MY" },
  shippingName: "Acme Warehouse",
  shippingAddress: { line1: "Dock 4", city: "Shah Alam", country: "MY" },
  deliveryAttention: "Warehouse receiving",
  paymentTerms: "Net 30",
  deliveryTerms: "DAP",
  buyerNote: "Confirm delivery slot before dispatch.",
  financeNote: "Charge to expansion budget.",
  source: { handoffId: "po-handoff-1", recommendationId: "award-1", rfqId: "1", snapshot: {} },
  vendor: { id: "vendor-1", name: "Northwind Traders" },
  approval: { finalDecision: "approved" },
  evidence: [],
  lines: [
    {
      id: "po-line-1",
      lineNumber: 1,
      description: "Pallet rack bay",
      unit: "each",
      quantity: "10.0000",
      unitPrice: "12000.00",
      subtotalAmount: "120000.00",
      totalAmount: "120000.00",
      currency: "MYR",
      source: {},
    },
  ],
  lockVersion: 1,
  permissions: { canUpdate: true, canMarkReadyForReview: true, canCancel: true },
};

export const purchaseOrderListResponseFixture: PurchaseOrderListResponse = {
  data: [purchaseOrderFixture],
  meta: {
    currentPage: 1,
    perPage: 15,
    total: 1,
    lastPage: 1,
  },
};
