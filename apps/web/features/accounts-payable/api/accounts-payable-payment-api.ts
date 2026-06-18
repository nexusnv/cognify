"use client";

import {
  placeSupplierInvoiceOnPaymentHold,
  releaseSupplierInvoicePaymentHold,
  retrySupplierInvoicePaymentInduction,
} from "@cognify/api-client/endpoints";
import type {
  PlaceInvoiceOnHoldRequest,
  ReleaseInvoiceHoldRequest,
  RetryPaymentInductionRequest,
  SupplierInvoicePaymentResponseData,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit {
  if (!tenantId) {
    throw new Error("Missing active tenant context");
  }

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

function unwrapData<T>(response: { status: number; data?: unknown }, success = 200): T {
  if (response.status !== success) {
    throw response.data ?? response;
  }

  return (response.data as { data: T }).data;
}

function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw (error as { data: unknown }).data;
  }

  throw error;
}

export async function placeInvoiceOnPaymentHold(
  invoiceId: string,
  payload: PlaceInvoiceOnHoldRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoicePaymentResponseData> {
  const response = await placeSupplierInvoiceOnPaymentHold(
    invoiceId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapData<SupplierInvoicePaymentResponseData>(response);
}

export async function releaseInvoicePaymentHold(
  invoiceId: string,
  payload: ReleaseInvoiceHoldRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoicePaymentResponseData> {
  const response = await releaseSupplierInvoicePaymentHold(
    invoiceId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapData<SupplierInvoicePaymentResponseData>(response);
}

export async function retryInvoicePaymentInduction(
  invoiceId: string,
  payload: RetryPaymentInductionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoicePaymentResponseData> {
  const response = await retrySupplierInvoicePaymentInduction(
    invoiceId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapData<SupplierInvoicePaymentResponseData>(response);
}
