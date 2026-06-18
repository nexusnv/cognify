"use client";

import { submitSupplierInvoiceApproval } from "@cognify/api-client/endpoints";
import type {
  SubmitSupplierInvoiceApprovalRequest,
  SupplierInvoice,
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

export async function submitForApproval(
  invoiceId: string,
  payload: SubmitSupplierInvoiceApprovalRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await submitSupplierInvoiceApproval(invoiceId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  return unwrapData<SupplierInvoice>(response);
}
