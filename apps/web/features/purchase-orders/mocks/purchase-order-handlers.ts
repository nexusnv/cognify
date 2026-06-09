import { http, HttpResponse } from "msw";
import type { PurchaseOrder } from "@cognify/api-client/schemas";
import {
  purchaseOrderFixture,
  purchaseOrderListResponseFixture,
} from "./purchase-order-fixtures";

let purchaseOrders: PurchaseOrder[] = [structuredClone(purchaseOrderFixture)];

export function resetPurchaseOrderMockState() {
  purchaseOrders = [structuredClone(purchaseOrderFixture)];
}

function findPurchaseOrder(purchaseOrderId: string) {
  return purchaseOrders.find((purchaseOrder) => purchaseOrder.id === purchaseOrderId);
}

export const purchaseOrderHandlers = [
  http.get("/api/purchase-orders", () => {
    return HttpResponse.json({
      data: purchaseOrders,
      meta: {
        ...purchaseOrderListResponseFixture.meta,
        total: purchaseOrders.length,
      },
    });
  }),

  http.get("/api/purchase-orders/:purchaseOrder", ({ params }) => {
    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    return HttpResponse.json({ data: purchaseOrder });
  }),

  http.patch("/api/purchase-orders/:purchaseOrder", async ({ params, request }) => {
    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as Partial<PurchaseOrder> & { lockVersion: number };
    const updated = {
      ...purchaseOrder,
      ...payload,
      lockVersion: purchaseOrder.lockVersion + 1,
    };

    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/purchase-orders/:purchaseOrder/ready-for-review", ({ params }) => {
    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const updated = {
      ...purchaseOrder,
      status: "ready_for_review" as const,
      lockVersion: purchaseOrder.lockVersion + 1,
      permissions: {
        ...purchaseOrder.permissions,
        canUpdate: false,
        canMarkReadyForReview: false,
      },
    };

    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/purchase-orders/:purchaseOrder/cancel", async ({ params, request }) => {
    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as { reason?: string };
    const updated = {
      ...purchaseOrder,
      status: "cancelled" as const,
      buyerNote: payload.reason ?? purchaseOrder.buyerNote,
      lockVersion: purchaseOrder.lockVersion + 1,
      permissions: {
        ...purchaseOrder.permissions,
        canUpdate: false,
        canMarkReadyForReview: false,
        canCancel: false,
      },
    };

    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json({ data: updated });
  }),
];
