import { http, HttpResponse } from "msw";
import type {
  AcknowledgePurchaseOrderRequest,
  CancelPurchaseOrderRequest,
  IssuePurchaseOrderRequest,
  MarkPurchaseOrderReadyForReviewRequest,
  PurchaseOrderChangeOrder,
  PurchaseOrderChangeOrderType,
  PurchaseOrder,
  PurchaseOrderChangeOrdersResponse,
  SavePurchaseOrderChangeOrderRequest,
  SubmitPurchaseOrderChangeOrderRequest,
  CancelPurchaseOrderChangeOrderRequest,
  SubmitPurchaseOrderApprovalRequest,
  UpdatePurchaseOrderRequest,
} from "@cognify/api-client/schemas";
import {
  purchaseOrderFixture,
  purchaseOrderListResponseFixture,
} from "./purchase-order-fixtures";

let purchaseOrders: PurchaseOrder[] = [structuredClone(purchaseOrderFixture)];
let purchaseOrderChangeOrders: Record<string, PurchaseOrderChangeOrder[]> = {};

export function resetPurchaseOrderMockState() {
  purchaseOrders = [structuredClone(purchaseOrderFixture)];
  purchaseOrderChangeOrders = {};
}

export function setPurchaseOrderMockState(nextPurchaseOrders: PurchaseOrder[]) {
  purchaseOrders = nextPurchaseOrders.map((purchaseOrder) => structuredClone(purchaseOrder));
  purchaseOrderChangeOrders = {};
}

export function setPurchaseOrderChangeOrdersMockState(
  purchaseOrderId: string,
  nextChangeOrders: PurchaseOrderChangeOrder[],
) {
  purchaseOrderChangeOrders[purchaseOrderId] = nextChangeOrders.map((changeOrder) => structuredClone(changeOrder));
  syncPurchaseOrderChangeOrderSummary(purchaseOrderId);
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

function changeOrderPurchaseOrderStatus(changeOrderType: PurchaseOrderChangeOrderType) {
  return changeOrderType === "full_cancellation" ? "cancelled" : "change_pending";
}

function changeOrderSummary(changeOrder: PurchaseOrderChangeOrder) {
  return {
    id: changeOrder.id,
    number: changeOrder.number,
    status: changeOrder.status,
    changeType: changeOrder.changeType,
    materialChange: changeOrder.materialChange,
    requiresApproval: changeOrder.requiresApproval,
  };
}

function syncPurchaseOrderChangeOrderSummary(purchaseOrderId: string) {
  const purchaseOrder = findPurchaseOrder(purchaseOrderId);
  if (!purchaseOrder) return;

  const changeOrders = purchaseOrderChangeOrders[purchaseOrderId] ?? [];
  const activeChangeOrder = [...changeOrders].reverse().find((changeOrder) =>
    ["draft", "changes_requested"].includes(changeOrder.status),
  );
  const latestChangeOrder = changeOrders[changeOrders.length - 1] ?? null;

  const summary = {
    currentChangeOrder: activeChangeOrder ? changeOrderSummary(activeChangeOrder) : null,
    latestChangeOrder: latestChangeOrder ? changeOrderSummary(latestChangeOrder) : null,
  };

  purchaseOrders = purchaseOrders.map((item) =>
    item.id === purchaseOrderId ? { ...item, changeOrdersSummary: summary } : item,
  );
}

function setChangeOrders(purchaseOrderId: string, nextChangeOrders: PurchaseOrderChangeOrder[]) {
  purchaseOrderChangeOrders[purchaseOrderId] = nextChangeOrders.map((changeOrder) => structuredClone(changeOrder));
  syncPurchaseOrderChangeOrderSummary(purchaseOrderId);
}

function nextChangeOrderNumber(purchaseOrder: PurchaseOrder) {
  const changeOrders = purchaseOrderChangeOrders[purchaseOrder.id] ?? [];
  return `CO-${purchaseOrder.number}-${String(changeOrders.length + 1).padStart(3, "0")}`;
}

function changeOrderSnapshotFromPurchaseOrder(purchaseOrder: PurchaseOrder) {
  return {
    requestedPoDate: purchaseOrder.requestedPoDate,
    expectedDeliveryDate: purchaseOrder.expectedDeliveryDate,
    billingName: purchaseOrder.billingName,
    billingAddress: purchaseOrder.billingAddress,
    shippingName: purchaseOrder.shippingName,
    shippingAddress: purchaseOrder.shippingAddress,
    deliveryAttention: purchaseOrder.deliveryAttention,
    paymentTerms: purchaseOrder.paymentTerms,
    deliveryTerms: purchaseOrder.deliveryTerms,
    buyerNote: purchaseOrder.buyerNote,
    financeNote: purchaseOrder.financeNote,
  };
}

function changeOrderSnapshotFromRequest(payload: SavePurchaseOrderChangeOrderRequest) {
  return {
    requestedPoDate: payload.requestedPoDate ?? null,
    expectedDeliveryDate: payload.expectedDeliveryDate ?? null,
    billingName: payload.billingName ?? null,
    billingAddress: payload.billingAddress ?? null,
    shippingName: payload.shippingName ?? null,
    shippingAddress: payload.shippingAddress ?? null,
    deliveryAttention: payload.deliveryAttention ?? null,
    paymentTerms: payload.paymentTerms ?? null,
    deliveryTerms: payload.deliveryTerms ?? null,
    buyerNote: payload.buyerNote ?? null,
    financeNote: payload.financeNote ?? null,
  };
}

function buildChangeOrder(
  purchaseOrder: PurchaseOrder,
  payload: SavePurchaseOrderChangeOrderRequest,
  existingChangeOrder?: PurchaseOrderChangeOrder,
): PurchaseOrderChangeOrder {
  const status = existingChangeOrder?.status ?? "draft";
  const materialChange = payload.changeType !== "full_cancellation";
  const number = existingChangeOrder?.number ?? nextChangeOrderNumber(purchaseOrder);
  const lines = buildChangeOrderLines(purchaseOrder, payload);

  return {
    id: existingChangeOrder?.id ?? `change-order-${purchaseOrder.id}-${purchaseOrderChangeOrders[purchaseOrder.id]?.length ?? 0}`,
    purchaseOrderId: purchaseOrder.id,
    number,
    status,
    changeType: payload.changeType,
    reason: payload.reason,
    materialChange,
    requiresApproval: materialChange,
    fromPurchaseOrderStatus: purchaseOrder.status,
    toPurchaseOrderStatus: changeOrderPurchaseOrderStatus(payload.changeType),
    before: changeOrderSnapshotFromPurchaseOrder(purchaseOrder),
    after: changeOrderSnapshotFromRequest(payload),
    delta: { changeType: payload.changeType, reason: payload.reason },
    supplierVersionNumber: 0,
    approvalInstanceId: existingChangeOrder?.approvalInstanceId ?? null,
    requestedAt: existingChangeOrder?.requestedAt ?? "2026-06-10T00:00:00.000Z",
    submittedAt: existingChangeOrder?.submittedAt ?? null,
    approvedAt: existingChangeOrder?.approvedAt ?? null,
    rejectedAt: existingChangeOrder?.rejectedAt ?? null,
    cancelledAt: existingChangeOrder?.cancelledAt ?? null,
    lockVersion: existingChangeOrder ? existingChangeOrder.lockVersion + 1 : 1,
    lines,
  };
}

function buildChangeOrderLines(
  purchaseOrder: PurchaseOrder,
  payload: SavePurchaseOrderChangeOrderRequest,
): PurchaseOrderChangeOrder["lines"] {
  return (payload.lines ?? []).map((lineChange, index) => {
    const line = findPurchaseOrderLine(purchaseOrder, lineChange.lineId);
    const lineNumber = line?.lineNumber ?? index + 1;
    const changeAction = lineChange.action;
    const quantityAfter = changeAction === "cancel" ? null : stringValue(lineChange.quantity ?? line?.quantity ?? null);
    const unitPriceAfter = changeAction === "cancel" ? null : stringValue(lineChange.unitPrice ?? line?.unitPrice ?? null);
    const expectedDeliveryDateAfter = changeAction === "cancel" ? null : (lineChange.expectedDeliveryDate ?? line?.expectedDeliveryDate ?? null);
    const deliveryLocationAfter = changeAction === "cancel" ? null : (lineChange.deliveryLocation ?? line?.deliveryLocation ?? null);
    const notesAfter = changeAction === "cancel" ? null : (lineChange.notes ?? line?.notes ?? null);

    return {
      id: `change-line-${purchaseOrder.id}-${lineChange.lineId}`,
      lineId: lineChange.lineId,
      lineNumber,
      changeAction,
      quantityBefore: line?.quantity ?? null,
      quantityAfter,
      unitPriceBefore: line?.unitPrice ?? null,
      unitPriceAfter,
      subtotalAmountBefore: line?.subtotalAmount ?? null,
      subtotalAmountAfter: quantityAfter && unitPriceAfter ? formatAmount(Number(quantityAfter) * Number(unitPriceAfter)) : null,
      taxAmountBefore: line?.taxAmount ?? null,
      taxAmountAfter: null,
      freightAmountBefore: line?.freightAmount ?? null,
      freightAmountAfter: null,
      discountAmountBefore: line?.discountAmount ?? null,
      discountAmountAfter: null,
      totalAmountBefore: line?.totalAmount ?? null,
      totalAmountAfter: quantityAfter && unitPriceAfter ? formatAmount(Number(quantityAfter) * Number(unitPriceAfter)) : null,
      expectedDeliveryDateBefore: line?.expectedDeliveryDate ?? null,
      expectedDeliveryDateAfter,
      deliveryLocationBefore: line?.deliveryLocation ?? null,
      deliveryLocationAfter,
      notesBefore: line?.notes ?? null,
      notesAfter,
      delta: {},
    };
  });
}

function findPurchaseOrderLine(purchaseOrder: PurchaseOrder, lineId: string) {
  return purchaseOrder.lines.find((line) => line.id === lineId);
}

function formatAmount(value: number) {
  return Number.isFinite(value) ? value.toFixed(2) : "0.00";
}

function stringValue(value: string | number | null | undefined) {
  if (typeof value === "string") return value;
  if (typeof value === "number") return String(value);
  return "";
}

function findChangeOrder(changeOrderId: string) {
  for (const [purchaseOrderId, changeOrders] of Object.entries(purchaseOrderChangeOrders)) {
    const changeOrder = changeOrders.find((item) => item.id === changeOrderId);
    if (changeOrder) {
      return { purchaseOrderId, changeOrder };
    }
  }

  return null;
}

function supplierExportPayload(purchaseOrder: PurchaseOrder) {
  return {
    format: "json" as const,
    exportedAt: "2026-06-10T02:10:00.000Z",
    purchaseOrder: {
      id: purchaseOrder.id,
      number: purchaseOrder.number,
      currency: purchaseOrder.currency,
      totalAmount: purchaseOrder.totalAmount,
      paymentTerms: purchaseOrder.paymentTerms,
      deliveryTerms: purchaseOrder.deliveryTerms,
    },
    vendor: purchaseOrder.vendor,
    lines: purchaseOrder.lines,
    source: purchaseOrder.source,
    approval: purchaseOrder.approval,
    issue: {
      versionNumber: purchaseOrder.supplierIssue.supplierVersionNumber,
      issuedAt: purchaseOrder.supplierIssue.issuedAt,
      issueMethod: purchaseOrder.supplierIssue.issueMethod,
      supplierContactName: purchaseOrder.supplierIssue.supplierContactName,
      supplierContactEmail: purchaseOrder.supplierIssue.supplierContactEmail,
      message: purchaseOrder.supplierIssue.message,
    },
  };
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

  http.get("/api/purchase-orders/:purchaseOrder/change-orders", ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const data = purchaseOrderChangeOrders[purchaseOrder.id] ?? [];
    return HttpResponse.json({ data } satisfies PurchaseOrderChangeOrdersResponse);
  }),

  http.post("/api/purchase-orders/:purchaseOrder/change-orders", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as SavePurchaseOrderChangeOrderRequest;
    if (payload.lockVersion !== purchaseOrder.lockVersion) {
      return conflictResponse();
    }

    const nextChangeOrder = buildChangeOrder(purchaseOrder, payload);
    const nextChangeOrders = [...(purchaseOrderChangeOrders[purchaseOrder.id] ?? []), nextChangeOrder];
    setChangeOrders(purchaseOrder.id, nextChangeOrders);

    const updatedPurchaseOrder: PurchaseOrder = {
      ...purchaseOrder,
      permissions: {
        ...purchaseOrder.permissions,
        canUpdateChangeOrder: true,
        canSubmitChangeOrder: true,
        canCancelChangeOrder: true,
      },
    };
    purchaseOrders = purchaseOrders.map((item) => (item.id === updatedPurchaseOrder.id ? updatedPurchaseOrder : item));

    return HttpResponse.json({ data: nextChangeOrder }, { status: 201 });
  }),

  http.get("/api/purchase-order-change-orders/:changeOrder", ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const found = findChangeOrder(String(params.changeOrder));
    if (!found) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    return HttpResponse.json({ data: found.changeOrder });
  }),

  http.patch("/api/purchase-order-change-orders/:changeOrder", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const found = findChangeOrder(String(params.changeOrder));
    if (!found) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const purchaseOrder = findPurchaseOrder(found.purchaseOrderId);
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as SavePurchaseOrderChangeOrderRequest;
    if (payload.lockVersion !== found.changeOrder.lockVersion) {
      return conflictResponse();
    }

    const updated = buildChangeOrder(purchaseOrder, payload, found.changeOrder);
    const nextChangeOrders = (purchaseOrderChangeOrders[found.purchaseOrderId] ?? []).map((item) =>
      item.id === updated.id ? updated : item,
    );
    setChangeOrders(found.purchaseOrderId, nextChangeOrders);

    const updatedPurchaseOrder: PurchaseOrder = {
      ...purchaseOrder,
      permissions: {
        ...purchaseOrder.permissions,
        canUpdateChangeOrder: true,
        canSubmitChangeOrder: true,
        canCancelChangeOrder: true,
      },
    };
    purchaseOrders = purchaseOrders.map((item) => (item.id === updatedPurchaseOrder.id ? updatedPurchaseOrder : item));

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/purchase-order-change-orders/:changeOrder/submit", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const found = findChangeOrder(String(params.changeOrder));
    if (!found) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as SubmitPurchaseOrderChangeOrderRequest;
    if (payload.lockVersion !== found.changeOrder.lockVersion) {
      return conflictResponse();
    }

    const purchaseOrder = findPurchaseOrder(found.purchaseOrderId);
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const updatedChangeOrder: PurchaseOrderChangeOrder = {
      ...found.changeOrder,
      status: "pending_approval",
      submittedAt: "2026-06-10T04:00:00.000Z",
      lockVersion: found.changeOrder.lockVersion + 1,
    };

    const nextChangeOrders = (purchaseOrderChangeOrders[found.purchaseOrderId] ?? []).map((item) =>
      item.id === updatedChangeOrder.id ? updatedChangeOrder : item,
    );
    setChangeOrders(found.purchaseOrderId, nextChangeOrders);

    const updatedPurchaseOrder: PurchaseOrder = {
      ...purchaseOrder,
      status: changeOrderPurchaseOrderStatus(updatedChangeOrder.changeType),
      lockVersion: purchaseOrder.lockVersion + 1,
      permissions: {
        ...purchaseOrder.permissions,
        canUpdateChangeOrder: false,
        canSubmitChangeOrder: false,
        canCancelChangeOrder: false,
        canCreateChangeOrder: false,
      },
    };
    purchaseOrders = purchaseOrders.map((item) => (item.id === updatedPurchaseOrder.id ? updatedPurchaseOrder : item));

    return HttpResponse.json({ data: updatedChangeOrder });
  }),

  http.post("/api/purchase-order-change-orders/:changeOrder/cancel", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const found = findChangeOrder(String(params.changeOrder));
    if (!found) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as CancelPurchaseOrderChangeOrderRequest;
    if (payload.lockVersion !== found.changeOrder.lockVersion) {
      return conflictResponse();
    }

    const purchaseOrder = findPurchaseOrder(found.purchaseOrderId);
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const updatedChangeOrder: PurchaseOrderChangeOrder = {
      ...found.changeOrder,
      status: "cancelled",
      cancelledAt: "2026-06-10T05:00:00.000Z",
      lockVersion: found.changeOrder.lockVersion + 1,
    };

    const nextChangeOrders = (purchaseOrderChangeOrders[found.purchaseOrderId] ?? []).map((item) =>
      item.id === updatedChangeOrder.id ? updatedChangeOrder : item,
    );
    setChangeOrders(found.purchaseOrderId, nextChangeOrders);

    const updatedPurchaseOrder: PurchaseOrder = {
      ...purchaseOrder,
      status: purchaseOrder.status === "change_pending" ? found.changeOrder.fromPurchaseOrderStatus : purchaseOrder.status,
      lockVersion: purchaseOrder.lockVersion + 1,
      permissions: {
        ...purchaseOrder.permissions,
        canUpdateChangeOrder: true,
        canSubmitChangeOrder: true,
        canCancelChangeOrder: true,
        canCreateChangeOrder: true,
      },
    };
    purchaseOrders = purchaseOrders.map((item) => (item.id === updatedPurchaseOrder.id ? updatedPurchaseOrder : item));

    return HttpResponse.json({ data: updatedChangeOrder });
  }),

  http.post("/api/purchase-orders/:purchaseOrder/issue", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as IssuePurchaseOrderRequest;
    if (payload.lockVersion !== purchaseOrder.lockVersion) {
      return conflictResponse();
    }
    if (purchaseOrder.status !== "approved") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only approved purchase orders can be issued to suppliers." } },
        { status: 409 },
      );
    }

    const updated: PurchaseOrder = {
      ...purchaseOrder,
      status: "issued",
      lockVersion: purchaseOrder.lockVersion + 1,
      supplierIssue: {
        ...purchaseOrder.supplierIssue,
        issuedByUserId: "buyer-1",
        issuedAt: "2026-06-10T02:00:00.000Z",
        issueMethod: payload.method,
        supplierContactName: payload.supplierContactName ?? null,
        supplierContactEmail: payload.supplierContactEmail ?? null,
        message: payload.message ?? null,
        supplierVersionNumber: 1,
      },
      permissions: {
        ...purchaseOrder.permissions,
        canIssueToSupplier: false,
        canExportSupplierVersion: true,
        canAcknowledgeSupplier: true,
      },
    };

    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json({ data: updated });
  }),

  http.get("/api/purchase-orders/:purchaseOrder/supplier-export.json", ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    return HttpResponse.json(supplierExportPayload(purchaseOrder));
  }),

  http.post("/api/purchase-orders/:purchaseOrder/supplier-export.json", ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const updated: PurchaseOrder = {
      ...purchaseOrder,
      lockVersion: purchaseOrder.lockVersion + 1,
      supplierIssue: {
        ...purchaseOrder.supplierIssue,
        lastExportedByUserId: "buyer-1",
        lastExportedAt: "2026-06-10T02:10:00.000Z",
        lastExportFormat: "json",
      },
    };
    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json(supplierExportPayload(updated));
  }),

  http.post("/api/purchase-orders/:purchaseOrder/acknowledge", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrder = findPurchaseOrder(String(params.purchaseOrder));
    if (!purchaseOrder) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const payload = (await request.json()) as AcknowledgePurchaseOrderRequest;
    if (payload.lockVersion !== purchaseOrder.lockVersion) {
      return conflictResponse();
    }
    if (purchaseOrder.status !== "issued") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only issued purchase orders can be acknowledged by suppliers." } },
        { status: 409 },
      );
    }
    const hasEvidence = [
      payload.acknowledgedContactName,
      payload.acknowledgementReference,
      payload.acknowledgementNote,
    ].some((value) => typeof value === "string" && value.trim() !== "");

    if (!hasEvidence) {
      return validationFailedResponse("At least one acknowledgement evidence field is required.", {
        acknowledgementReference: ["At least one acknowledgement evidence field is required."],
      });
    }

    const updated: PurchaseOrder = {
      ...purchaseOrder,
      status: "acknowledged",
      lockVersion: purchaseOrder.lockVersion + 1,
      supplierIssue: {
        ...purchaseOrder.supplierIssue,
        acknowledgedByUserId: "buyer-1",
        acknowledgedAt: "2026-06-10T03:00:00.000Z",
        acknowledgedContactName: payload.acknowledgedContactName ?? null,
        acknowledgementReference: payload.acknowledgementReference ?? null,
        acknowledgementNote: payload.acknowledgementNote ?? null,
      },
      permissions: {
        ...purchaseOrder.permissions,
        canAcknowledgeSupplier: false,
      },
    };

    purchaseOrders = purchaseOrders.map((item) => (item.id === updated.id ? updated : item));

    return HttpResponse.json({ data: updated });
  }),
];
