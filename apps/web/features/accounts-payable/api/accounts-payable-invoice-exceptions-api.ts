"use client";

import {
  listSupplierInvoiceExceptions,
  resolveSupplierInvoiceException,
  escalateSupplierInvoiceException,
} from "@cognify/api-client/endpoints";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";
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

export async function fetchInvoiceExceptions(
  supplierInvoiceId: string,
  tenantId: string | null,
): Promise<SupplierInvoiceException[]> {
  const response = await listSupplierInvoiceExceptions(supplierInvoiceId, {
    headers: withActiveTenantHeader(tenantId),
  }).catch(throwResponseData);
  return unwrapData<SupplierInvoiceException[]>(response);
}

export interface ResolveExceptionPayload {
  lockVersion: number;
  resolutionType: "value_adjustment" | "explanation";
  adjustedValue?: string;
  explanation?: string;
}

export async function resolveException(
  supplierInvoiceId: string,
  exceptionId: string,
  payload: ResolveExceptionPayload,
  tenantId: string | null,
): Promise<SupplierInvoiceException> {
  const response = await resolveSupplierInvoiceException(
    supplierInvoiceId,
    exceptionId,
    payload,
    { headers: withActiveTenantHeader(tenantId) },
  ).catch(throwResponseData);
  return unwrapData<SupplierInvoiceException>(response);
}

export interface EscalateExceptionPayload {
  lockVersion: number;
  escalatedToUserId: string;
  note?: string;
}

export async function escalateException(
  supplierInvoiceId: string,
  exceptionId: string,
  payload: EscalateExceptionPayload,
  tenantId: string | null,
): Promise<SupplierInvoiceException> {
  const response = await escalateSupplierInvoiceException(
    supplierInvoiceId,
    exceptionId,
    payload,
    { headers: withActiveTenantHeader(tenantId) },
  ).catch(throwResponseData);
  return unwrapData<SupplierInvoiceException>(response);
}
