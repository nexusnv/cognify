import { http, HttpResponse } from "msw";
import type {
  CancelPurchaseOrderRequest,
  MarkPurchaseOrderReadyForReviewRequest,
  PurchaseOrder,
  UpdatePurchaseOrderRequest,
} from "@cognify/api-client/schemas";
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

function conflictResponse() {
  return HttpResponse.json(
    { error: { code: "conflict", message: "Lock version mismatch." } },
    { status: 409 },
  );
}

function missingTenantResponse(request: Request) {
  if (request.headers.get("x-tenant-id")) {
    return null;
  }

  return HttpResponse.json(
    { error: { code: "ambiguous_tenant", message: "Tenant context is required." } },
    { status: 400 },
  );
}

export const purchaseOrderHandlers = [
  http.get("/api/purchase-orders", ({ request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    return HttpResponse.json({
      data: purchaseOrders,
      meta: {
        ...purchaseOrderListResponseFixture.meta,
        total: purchaseOrders.length,
      },
    });
  }),

  http.get("/api/purchase-orders/:purchaseOrder", ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    return HttpResponse.json({ data: purchaseOrder });
  }),

  http.patch("/api/purchase-orders/:purchaseOrder", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as UpdatePurchaseOrderRequest;
    if (payload.lockVersion !== purchaseOrder.lockVersion) {
      return conflictResponse();
    }

    const updated = {
      ...purchaseOrder,
      ...payload,
      lockVersion: purchaseOrder.lockVersion + 1,
    };

    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/purchase-orders/:purchaseOrder/ready-for-review", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as MarkPurchaseOrderReadyForReviewRequest;
    if (payload.lockVersion !== purchaseOrder.lockVersion) {
      return conflictResponse();
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
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as CancelPurchaseOrderRequest;
    if (payload.lockVersion !== purchaseOrder.lockVersion) {
      return conflictResponse();
    }

    const updated = {
      ...purchaseOrder,
      status: "cancelled" as const,
      buyerNote: payload.reason,
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
