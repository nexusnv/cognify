"use client";

import {
  acknowledgePurchaseOrderSupplier as acknowledgePurchaseOrderSupplierEndpoint,
  cancelPurchaseOrder as cancelPurchaseOrderEndpoint,
  cancelPurchaseOrderChangeOrder as cancelPurchaseOrderChangeOrderEndpoint,
  exportPurchaseOrderSupplierJson as exportPurchaseOrderSupplierJsonEndpoint,
  issuePurchaseOrderToSupplier as issuePurchaseOrderToSupplierEndpoint,
  listPurchaseOrders as listPurchaseOrdersEndpoint,
  listPurchaseOrderChangeOrders as listPurchaseOrderChangeOrdersEndpoint,
  markPurchaseOrderReadyForReview as markPurchaseOrderReadyForReviewEndpoint,
  recordPurchaseOrderSupplierJsonExport as recordPurchaseOrderSupplierJsonExportEndpoint,
  showPurchaseOrder as showPurchaseOrderEndpoint,
  showPurchaseOrderChangeOrder as showPurchaseOrderChangeOrderEndpoint,
  savePurchaseOrderChangeOrder as savePurchaseOrderChangeOrderEndpoint,
  submitPurchaseOrderChangeOrder as submitPurchaseOrderChangeOrderEndpoint,
  submitPurchaseOrderApproval as submitPurchaseOrderApprovalEndpoint,
  updatePurchaseOrderChangeOrder as updatePurchaseOrderChangeOrderEndpoint,
  updatePurchaseOrder as updatePurchaseOrderEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  AcknowledgePurchaseOrderRequest,
  CancelPurchaseOrderRequest,
  CancelPurchaseOrderChangeOrderRequest,
  IssuedPurchaseOrderExport,
  IssuePurchaseOrderRequest,
  MarkPurchaseOrderReadyForReviewRequest,
  PurchaseOrderChangeOrder,
  PurchaseOrderChangeOrdersResponse,
  PurchaseOrder,
  PurchaseOrderListResponse,
  SavePurchaseOrderChangeOrderRequest,
  SubmitPurchaseOrderChangeOrderRequest,
  SubmitPurchaseOrderApprovalRequest,
  UpdatePurchaseOrderRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

function unwrapOk(response: { status: number; data: unknown }, expectedStatus = 200): unknown {
  if (response.status !== expectedStatus) {
    throw response.data;
  }

  return (response.data as { data: unknown }).data;
}

function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw (error as { data: unknown }).data;
  }

  throw error;
}

export async function fetchPurchaseOrders(
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderListResponse> {
  const response = await listPurchaseOrdersEndpoint(undefined, withActiveTenantHeader(tenantId));
  if (response.status !== 200) {
    throw response.data;
  }

  return response.data;
}

export async function fetchPurchaseOrder(
  purchaseOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrder> {
  const response = await showPurchaseOrderEndpoint(purchaseOrderId, withActiveTenantHeader(tenantId));
  return unwrapOk(response) as PurchaseOrder;
}

export async function fetchPurchaseOrderChangeOrders(
  purchaseOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderChangeOrder[]> {
  const response = await listPurchaseOrderChangeOrdersEndpoint(purchaseOrderId, withActiveTenantHeader(tenantId));
  if (response.status !== 200) {
    throw response.data;
  }

  return (response.data as PurchaseOrderChangeOrdersResponse).data;
}

export async function fetchPurchaseOrderChangeOrder(
  changeOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderChangeOrder> {
  const response = await showPurchaseOrderChangeOrderEndpoint(changeOrderId, withActiveTenantHeader(tenantId));
  return unwrapOk(response) as PurchaseOrderChangeOrder;
}

export async function savePurchaseOrder(
  purchaseOrderId: string,
  payload: UpdatePurchaseOrderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrder> {
  const response = await updatePurchaseOrderEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrder;
}

export async function createPurchaseOrderChangeOrder(
  purchaseOrderId: string,
  payload: SavePurchaseOrderChangeOrderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderChangeOrder> {
  const response = await savePurchaseOrderChangeOrderEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response, 201) as PurchaseOrderChangeOrder;
}

export async function updatePurchaseOrderChangeOrder(
  changeOrderId: string,
  payload: SavePurchaseOrderChangeOrderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderChangeOrder> {
  const response = await updatePurchaseOrderChangeOrderEndpoint(
    changeOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrderChangeOrder;
}

export async function submitPurchaseOrderChangeOrder(
  changeOrderId: string,
  payload: SubmitPurchaseOrderChangeOrderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderChangeOrder> {
  const response = await submitPurchaseOrderChangeOrderEndpoint(
    changeOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrderChangeOrder;
}

export async function cancelPurchaseOrderChangeOrder(
  changeOrderId: string,
  payload: CancelPurchaseOrderChangeOrderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderChangeOrder> {
  const response = await cancelPurchaseOrderChangeOrderEndpoint(
    changeOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrderChangeOrder;
}

export async function readyPurchaseOrder(
  purchaseOrderId: string,
  payload: MarkPurchaseOrderReadyForReviewRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrder> {
  const response = await markPurchaseOrderReadyForReviewEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrder;
}

export async function submitPurchaseOrderApproval(
  purchaseOrderId: string,
  payload: SubmitPurchaseOrderApprovalRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrder> {
  const response = await submitPurchaseOrderApprovalEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrder;
}

export async function cancelDraftPurchaseOrder(
  purchaseOrderId: string,
  payload: CancelPurchaseOrderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrder> {
  const response = await cancelPurchaseOrderEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrder;
}

export async function issuePurchaseOrderToSupplier(
  purchaseOrderId: string,
  payload: IssuePurchaseOrderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrder> {
  const response = await issuePurchaseOrderToSupplierEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrder;
}

export async function acknowledgePurchaseOrderSupplier(
  purchaseOrderId: string,
  payload: AcknowledgePurchaseOrderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrder> {
  const response = await acknowledgePurchaseOrderSupplierEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrder;
}

export async function exportPurchaseOrderSupplierJson(
  purchaseOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<IssuedPurchaseOrderExport> {
  const response = await exportPurchaseOrderSupplierJsonEndpoint(
    purchaseOrderId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  if (response.status !== 200) {
    throw response.data;
  }

  return response.data;
}

export async function recordPurchaseOrderSupplierJsonExport(
  purchaseOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<IssuedPurchaseOrderExport> {
  const response = await recordPurchaseOrderSupplierJsonExportEndpoint(
    purchaseOrderId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  if (response.status !== 200) {
    throw response.data;
  }

  return response.data;
}
