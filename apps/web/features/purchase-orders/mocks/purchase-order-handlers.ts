import { http, HttpResponse } from "msw";
import type {
  CancelPurchaseOrderRequest,
  MarkPurchaseOrderReadyForReviewRequest,
  PurchaseOrder,
  SubmitPurchaseOrderApprovalRequest,
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

export function setPurchaseOrderMockState(nextPurchaseOrders: PurchaseOrder[]) {
  purchaseOrders = nextPurchaseOrders.map((purchaseOrder) => structuredClone(purchaseOrder));
}

function findPurchaseOrder(purchaseOrderId: string) {
  return purchaseOrders.find((purchaseOrder) => purchaseOrder.id === purchaseOrderId);
}

function hasRequiredPurchaseOrderReviewFields(purchaseOrder: PurchaseOrder) {
  const requiredFields = [
    purchaseOrder.billingName,
    purchaseOrder.billingAddress,
    purchaseOrder.shippingName,
    purchaseOrder.shippingAddress,
    purchaseOrder.paymentTerms,
  ];

  return requiredFields.every((value) => {
    if (typeof value === "string") return value.trim() !== "";
    if (Array.isArray(value)) return value.length > 0;
    return value !== null && value !== undefined;
  });
}

function conflictResponse() {
  return HttpResponse.json(
    { error: { code: "invalid_state", message: "The purchase order has changed. Reload and try again." } },
    { status: 409 },
  );
}

function validationFailedResponse(message: string, fields: Record<string, string[]>) {
  return HttpResponse.json(
    {
      error: {
        code: "validation_failed",
        message,
        details: { fields },
      },
    },
    { status: 422 },
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
    if (!hasRequiredPurchaseOrderReviewFields(purchaseOrder)) {
      return validationFailedResponse("Purchase order requires billing, shipping, and payment terms before review.", {
        billingName: ["Billing name is required."],
        billingAddress: ["Billing address is required."],
        shippingName: ["Shipping name is required."],
        shippingAddress: ["Shipping address is required."],
        paymentTerms: ["Payment terms are required."],
      });
    }

    const updated = {
      ...purchaseOrder,
      status: "ready_for_review" as const,
      lockVersion: purchaseOrder.lockVersion + 1,
      permissions: {
        ...purchaseOrder.permissions,
        canUpdate: false,
        canMarkReadyForReview: false,
        canSubmitForApproval: true,
      },
    };

    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/purchase-orders/:purchaseOrder/submit-approval", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as SubmitPurchaseOrderApprovalRequest;
    if (payload.lockVersion !== purchaseOrder.lockVersion) {
      return conflictResponse();
    }
    if (!["ready_for_review", "changes_requested"].includes(purchaseOrder.status)) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only ready or changes-requested purchase orders can be submitted for approval." } },
        { status: 409 },
      );
    }

    const updated = {
      ...purchaseOrder,
      status: "in_review" as const,
      lockVersion: purchaseOrder.lockVersion + 1,
      approval: {
        ...purchaseOrder.approval,
        approvalInstanceId: "approval-po-1",
        submittedByUserId: "buyer-1",
        submittedAt: "2026-06-09T08:00:00.000Z",
      },
      permissions: {
        ...purchaseOrder.permissions,
        canUpdate: false,
        canMarkReadyForReview: false,
        canCancel: false,
        canSubmitForApproval: false,
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
    if ((payload.reason?.trim() ?? "").length < 3) {
      return validationFailedResponse("The given data was invalid.", {
        reason: ["The reason field must be at least 3 characters."],
      });
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
        canSubmitForApproval: false,
      },
    };

    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json({ data: updated });
  }),
];
