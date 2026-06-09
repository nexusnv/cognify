"use client";

import {
  cancelPurchaseOrder as cancelPurchaseOrderEndpoint,
  listPurchaseOrders as listPurchaseOrdersEndpoint,
  markPurchaseOrderReadyForReview as markPurchaseOrderReadyForReviewEndpoint,
  showPurchaseOrder as showPurchaseOrderEndpoint,
  submitPurchaseOrderApproval as submitPurchaseOrderApprovalEndpoint,
  updatePurchaseOrder as updatePurchaseOrderEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  CancelPurchaseOrderRequest,
  MarkPurchaseOrderReadyForReviewRequest,
  PurchaseOrder,
  PurchaseOrderListResponse,
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
