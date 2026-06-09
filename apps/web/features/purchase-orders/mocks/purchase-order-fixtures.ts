import type { PurchaseOrder, PurchaseOrderListResponse } from "@cognify/api-client/schemas";

export function buildPurchaseOrderFixture(overrides: Partial<PurchaseOrder> = {}): PurchaseOrder {
  const base: PurchaseOrder = {
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
  approval: {
    approvalInstanceId: null,
    submittedByUserId: null,
    submittedAt: null,
    approvedByUserId: null,
    approvedAt: null,
    rejectedByUserId: null,
    rejectedAt: null,
    rejectedReason: null,
    changesRequestedByUserId: null,
    changesRequestedAt: null,
    changesRequestedReason: null,
    changesRequestedFields: [],
    snapshot: { finalDecision: "approved" },
  },
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
  permissions: { canUpdate: true, canMarkReadyForReview: true, canCancel: true, canSubmitForApproval: false },
  };

  return {
    ...base,
    ...overrides,
    approval: { ...base.approval, ...overrides.approval },
    permissions: { ...base.permissions, ...overrides.permissions },
  };
}

export const purchaseOrderFixture: PurchaseOrder = buildPurchaseOrderFixture();

export const readyPurchaseOrderFixture: PurchaseOrder = buildPurchaseOrderFixture({
  status: "ready_for_review",
  lockVersion: 2,
  permissions: { canUpdate: false, canMarkReadyForReview: false, canCancel: false, canSubmitForApproval: true },
});

export const inReviewPurchaseOrderFixture: PurchaseOrder = buildPurchaseOrderFixture({
  status: "in_review",
  lockVersion: 3,
  approval: { approvalInstanceId: "approval-po-1", submittedByUserId: "buyer-1", submittedAt: "2026-06-09T08:00:00.000Z", changesRequestedFields: [] },
  permissions: { canUpdate: false, canMarkReadyForReview: false, canCancel: false, canSubmitForApproval: false },
});

export const changesRequestedPurchaseOrderFixture: PurchaseOrder = buildPurchaseOrderFixture({
  status: "changes_requested",
  lockVersion: 4,
  approval: {
    approvalInstanceId: "approval-po-1",
    submittedByUserId: "buyer-1",
    submittedAt: "2026-06-09T08:00:00.000Z",
    changesRequestedByUserId: "approver-1",
    changesRequestedAt: "2026-06-09T09:00:00.000Z",
    changesRequestedReason: "Payment terms and tax amount require correction.",
    changesRequestedFields: ["taxAmount", "paymentTerms"],
  },
  permissions: { canUpdate: true, canMarkReadyForReview: false, canCancel: false, canSubmitForApproval: true },
});

export const approvedPurchaseOrderFixture: PurchaseOrder = buildPurchaseOrderFixture({
  status: "approved",
  lockVersion: 5,
  approval: {
    approvalInstanceId: "approval-po-1",
    submittedByUserId: "buyer-1",
    submittedAt: "2026-06-09T08:00:00.000Z",
    approvedByUserId: "approver-1",
    approvedAt: "2026-06-09T10:00:00.000Z",
    changesRequestedFields: [],
  },
  permissions: { canUpdate: false, canMarkReadyForReview: false, canCancel: false, canSubmitForApproval: false },
});

export const rejectedPurchaseOrderFixture: PurchaseOrder = buildPurchaseOrderFixture({
  status: "rejected",
  lockVersion: 5,
  approval: {
    approvalInstanceId: "approval-po-1",
    submittedByUserId: "buyer-1",
    submittedAt: "2026-06-09T08:00:00.000Z",
    rejectedByUserId: "approver-1",
    rejectedAt: "2026-06-09T10:00:00.000Z",
    rejectedReason: "Tax coding does not match the approved quotation.",
    changesRequestedFields: [],
  },
  permissions: { canUpdate: false, canMarkReadyForReview: false, canCancel: false, canSubmitForApproval: false },
});

export const purchaseOrderListResponseFixture: PurchaseOrderListResponse = {
  data: [purchaseOrderFixture],
  meta: {
    currentPage: 1,
    perPage: 15,
    total: 1,
    lastPage: 1,
  },
};
