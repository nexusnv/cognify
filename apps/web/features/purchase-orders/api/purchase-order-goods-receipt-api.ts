"use client";

import {
  getApiPurchaseOrdersPurchaseOrderGoodsReceipts as listGoodsReceiptsEndpoint,
  postApiPurchaseOrdersPurchaseOrderGoodsReceipts as recordGoodsReceiptEndpoint,
  postApiGoodsReceiptsGoodsReceiptConfirmRequester as confirmRequesterEndpoint,
  postApiGoodsReceiptsGoodsReceiptConfirmBuyer as confirmBuyerEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  ConfirmGoodsReceiptRequest,
  GoodsReceipt,
  RecordGoodsReceiptRequest,
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

export async function fetchGoodsReceipts(
  purchaseOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<GoodsReceipt[]> {
  const response = await listGoodsReceiptsEndpoint(purchaseOrderId, withActiveTenantHeader(tenantId)).catch(throwResponseData);

  return unwrapOk(response) as GoodsReceipt[];
}

export async function recordGoodsReceipt(
  purchaseOrderId: string,
  payload: RecordGoodsReceiptRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<GoodsReceipt> {
  const response = await recordGoodsReceiptEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response, 201) as GoodsReceipt;
}

export async function confirmGoodsReceiptRequester(
  goodsReceiptId: string,
  payload: ConfirmGoodsReceiptRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<GoodsReceipt> {
  const response = await confirmRequesterEndpoint(
    goodsReceiptId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response) as GoodsReceipt;
}

export async function confirmGoodsReceiptBuyer(
  goodsReceiptId: string,
  payload: ConfirmGoodsReceiptRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<GoodsReceipt> {
  const response = await confirmBuyerEndpoint(
    goodsReceiptId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response) as GoodsReceipt;
}
