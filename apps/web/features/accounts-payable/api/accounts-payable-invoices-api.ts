"use client";

import {
  completeSupplierInvoiceReview,
  listSupplierInvoiceQueue,
  markSupplierInvoiceNeedsInformation,
  showSupplierInvoice,
  startSupplierInvoiceReview,
} from "@cognify/api-client/endpoints";
import type {
  ListSupplierInvoiceQueueParams,
  SupplierInvoice,
  SupplierInvoiceCompleteReviewRequest,
  SupplierInvoiceNeedsInformationRequest,
  SupplierInvoiceQueueItem,
  SupplierInvoiceStartReviewRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export type AccountsPayableInvoiceFilters = ListSupplierInvoiceQueueParams;

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

export async function fetchAccountsPayableInvoices(
  filters: AccountsPayableInvoiceFilters,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoiceQueueItem[]> {
  const response = await listSupplierInvoiceQueue(filters, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierInvoiceQueueItem[]>(response);
}

export async function fetchSupplierInvoiceDetail(
  id: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await showSupplierInvoice(id, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierInvoice>(response);
}

export async function startReview(
  id: string,
  payload: SupplierInvoiceStartReviewRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await startSupplierInvoiceReview(id, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  return unwrapData<SupplierInvoice>(response);
}

export async function markNeedsInformation(
  id: string,
  payload: SupplierInvoiceNeedsInformationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await markSupplierInvoiceNeedsInformation(id, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  return unwrapData<SupplierInvoice>(response);
}

export async function completeReview(
  id: string,
  payload: SupplierInvoiceCompleteReviewRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await completeSupplierInvoiceReview(id, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  return unwrapData<SupplierInvoice>(response);
}
