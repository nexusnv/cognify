import { http, HttpResponse } from "msw";
import type { GoodsReceipt, RecordGoodsReceiptRequest, ConfirmGoodsReceiptRequest } from "@cognify/api-client/schemas";
import { buildGoodsReceiptFixture } from "./purchase-order-goods-receipt-fixtures";

let goodsReceipts: GoodsReceipt[] = [buildGoodsReceiptFixture()];
let goodsReceiptIdCounter = 2;
let goodsReceiptLineIdCounter = 2;

export function resetGoodsReceiptMockState() {
  goodsReceipts = [buildGoodsReceiptFixture()];
  goodsReceiptIdCounter = 2;
  goodsReceiptLineIdCounter = 2;
}

export function setGoodsReceiptMockState(nextReceipts: GoodsReceipt[]) {
  goodsReceipts = nextReceipts.map((receipt) => structuredClone(receipt));
}

function conflictResponse() {
  return HttpResponse.json(
    { error: { code: "invalid_state", message: "The receipt has changed. Reload and try again." } },
    { status: 409 },
  );
}

export const purchaseOrderGoodsReceiptHandlers = [
  http.get("/api/purchase-orders/:purchaseOrderId/goods-receipts", ({ params }) => {
    const { purchaseOrderId } = params;

    return HttpResponse.json({
      data: goodsReceipts.filter((gr) => gr.purchaseOrderId === purchaseOrderId),
    });
  }),

  http.post("/api/purchase-orders/:purchaseOrderId/goods-receipts", async ({ params, request }) => {
    const { purchaseOrderId } = params;
    const body = (await request.json()) as RecordGoodsReceiptRequest;

    const receiptId = goodsReceiptIdCounter++;
    const newReceipt: GoodsReceipt = {
      id: `gr-${receiptId}`,
      purchaseOrderId: purchaseOrderId as string,
      number: `GR-2026-${String(receiptId).padStart(6, "0")}`,
      status: "completed",
      receiptDate: body.receiptDate,
      receiptReference: body.receiptReference ?? null,
      notes: body.notes ?? null,
      recordedByUserId: "user-1",
      recordedAt: new Date().toISOString(),
      requesterConfirmedByUserId: null,
      requesterConfirmedAt: null,
      buyerConfirmedByUserId: null,
      buyerConfirmedAt: null,
      lockVersion: 1,
      lines: body.lines.map((line) => ({
        id: `gr-line-${goodsReceiptLineIdCounter++}`,
        purchaseOrderLineId: line.purchaseOrderLineId,
        lineNumber: 1,
        quantityOrdered: "10.0000",
        quantityReceived: line.quantityReceived,
        quantityAccepted: line.quantityAccepted ?? line.quantityReceived,
        rejectionReason: line.rejectionReason ?? null,
        notes: line.notes ?? null,
      })),
    };

    goodsReceipts.push(newReceipt);

    return HttpResponse.json({ data: newReceipt }, { status: 201 });
  }),

  http.get("/api/goods-receipts/:goodsReceiptId", ({ params }) => {
    const { goodsReceiptId } = params;
    const receipt = goodsReceipts.find((gr) => gr.id === goodsReceiptId);

    if (!receipt) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Goods receipt not found." } },
        { status: 404 },
      );
    }

    return HttpResponse.json({ data: receipt });
  }),

  http.post("/api/goods-receipts/:goodsReceiptId/confirm-requester", async ({ params, request }) => {
    const { goodsReceiptId } = params;
    const body = (await request.json()) as ConfirmGoodsReceiptRequest;
    const receiptIndex = goodsReceipts.findIndex((gr) => gr.id === goodsReceiptId);

    if (receiptIndex === -1) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Goods receipt not found." } },
        { status: 404 },
      );
    }

    const receipt = goodsReceipts[receiptIndex];

    if (receipt.lockVersion !== body.lockVersion) {
      return conflictResponse();
    }

    goodsReceipts[receiptIndex] = {
      ...receipt,
      status: "requester_confirmed",
      requesterConfirmedByUserId: "user-1",
      requesterConfirmedAt: new Date().toISOString(),
      lockVersion: receipt.lockVersion + 1,
    };

    return HttpResponse.json({ data: goodsReceipts[receiptIndex] });
  }),

  http.post("/api/goods-receipts/:goodsReceiptId/confirm-buyer", async ({ params, request }) => {
    const { goodsReceiptId } = params;
    const body = (await request.json()) as ConfirmGoodsReceiptRequest;
    const receiptIndex = goodsReceipts.findIndex((gr) => gr.id === goodsReceiptId);

    if (receiptIndex === -1) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Goods receipt not found." } },
        { status: 404 },
      );
    }

    const receipt = goodsReceipts[receiptIndex];

    if (receipt.lockVersion !== body.lockVersion) {
      return conflictResponse();
    }

    goodsReceipts[receiptIndex] = {
      ...receipt,
      status: "buyer_confirmed",
      buyerConfirmedByUserId: "user-1",
      buyerConfirmedAt: new Date().toISOString(),
      lockVersion: receipt.lockVersion + 1,
    };

    return HttpResponse.json({ data: goodsReceipts[receiptIndex] });
  }),
];
