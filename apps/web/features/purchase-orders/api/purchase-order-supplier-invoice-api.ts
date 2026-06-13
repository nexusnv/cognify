"use client";

import {
  createSupplierInvoice as createSupplierInvoiceEndpoint,
  listSupplierInvoiceAttachments as listSupplierInvoiceAttachmentsEndpoint,
  listSupplierInvoices as listSupplierInvoicesEndpoint,
  uploadSupplierInvoiceAttachment as uploadSupplierInvoiceAttachmentEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  Attachment,
  CaptureSupplierInvoiceRequest,
  SupplierInvoice,
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

  return (response.data as { data: { data: unknown } | unknown }).data;
}

function unwrapNestedData(response: { status: number; data: unknown }, expectedStatus = 200): unknown {
  const data = unwrapOk(response, expectedStatus) as { data: unknown } | unknown;

  if (typeof data === "object" && data !== null && "data" in data) {
    return (data as { data: unknown }).data;
  }

  return data;
}

function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw (error as { data: unknown }).data;
  }

  throw error;
}

export async function fetchPurchaseOrderSupplierInvoices(
  purchaseOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice[]> {
  const response = await listSupplierInvoicesEndpoint(
    purchaseOrderId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapNestedData(response) as SupplierInvoice[];
}

export async function createPurchaseOrderSupplierInvoice(
  purchaseOrderId: string,
  payload: CaptureSupplierInvoiceRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await createSupplierInvoiceEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapNestedData(response as { status: number; data: unknown }, 201) as SupplierInvoice;
}

export async function fetchSupplierInvoiceAttachments(
  supplierInvoiceId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Attachment[]> {
  const response = await listSupplierInvoiceAttachmentsEndpoint(
    supplierInvoiceId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapNestedData(response) as Attachment[];
}

export async function uploadSupplierInvoiceAttachment(
  supplierInvoiceId: string,
  file: File,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Attachment> {
  const response = await uploadSupplierInvoiceAttachmentEndpoint(
    supplierInvoiceId,
    { file },
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapNestedData(response as { status: number; data: unknown }, 201) as Attachment;
}
