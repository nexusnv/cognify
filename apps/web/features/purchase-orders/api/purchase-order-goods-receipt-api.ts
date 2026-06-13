"use client";

import {
  listGoodsReceipts as listGoodsReceiptsEndpoint,
  recordGoodsReceipt as recordGoodsReceiptEndpoint,
  confirmGoodsReceiptRequester as confirmRequesterEndpoint,
  confirmGoodsReceiptBuyer as confirmBuyerEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  ConfirmGoodsReceiptRequest,
  GoodsReceipt,
  RecordGoodsReceiptRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
  if (!tenantId) return undefined;

  const xsrfToken = getXsrfToken();

  return {
    credentials: "include",
    headers: {
      "X-Tenant-Id": tenantId,
      ...(xsrfToken ? { "X-XSRF-TOKEN": xsrfToken } : {}),
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
    throw normalizeErrorData((error as { data: unknown }).data);
  }

  throw error;
}

function normalizeErrorData(data: unknown): { message: string; code?: string } {
  if (typeof data === "object" && data !== null) {
    const payload = data as { message?: unknown; code?: unknown };
    const message =
      typeof payload.message === "string"
        ? payload.message
        : JSON.stringify(data);

    return {
      message,
      ...(typeof payload.code === "string" ? { code: payload.code } : {}),
    };
  }

  return { message: String(data) };
}

function getXsrfToken(): string | null {
  if (typeof document === "undefined") return null;

  const token = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
    ?.split("=")[1];

  return token ? decodeURIComponent(token) : null;
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
