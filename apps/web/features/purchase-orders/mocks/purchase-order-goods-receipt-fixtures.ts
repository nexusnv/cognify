import type { GoodsReceipt } from "@cognify/api-client/schemas";

export function buildGoodsReceiptFixture(overrides: Partial<GoodsReceipt> = {}): GoodsReceipt {
  const base: GoodsReceipt = {
    id: "gr-1",
    purchaseOrderId: "po-1",
    number: "GR-2026-000001",
    status: "completed",
    receiptDate: "2026-06-11",
    receiptReference: "D/O 98765",
    notes: "Delivered on time.",
    recordedByUserId: "user-1",
    recordedAt: "2026-06-11T10:00:00Z",
    requesterConfirmedByUserId: null,
    requesterConfirmedAt: null,
    buyerConfirmedByUserId: null,
    buyerConfirmedAt: null,
    lockVersion: 1,
    lines: [
      {
        id: "gr-line-1",
        purchaseOrderLineId: "pol-1",
        lineNumber: 1,
        quantityOrdered: "10.0000",
        quantityReceived: "10.0000",
        quantityAccepted: "10.0000",
        rejectionReason: null,
        notes: "All items in good condition.",
      },
    ],
  };

  return { ...base, ...overrides };
}
