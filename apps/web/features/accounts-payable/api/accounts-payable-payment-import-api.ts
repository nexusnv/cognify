"use client";

import {
  uploadPaymentImport,
  showPaymentImportBatch,
  updatePaymentImportRow,
  reconcilePaymentImportBatch,
  discardPaymentImportRow,
} from "@cognify/api-client/endpoints";
import type {
  ApPaymentImportBatchResponseData,
  ApPaymentImportRowResponse,
  ReconciliationResultResponseData,
  UploadPaymentImportRequest,
  UpdatePaymentImportRowRequest,
  ReconcilePaymentImportBatchRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData } from "./api-helpers";

function unwrapResource<T>(
  response: { status: number; data?: { data?: T } | unknown },
  success = 200,
): T {
  if (response.status !== success) {
    throw response.data ?? response;
  }

  if (typeof response.data !== "object" || response.data === null || !("data" in response.data)) {
    throw new Error(`Unexpected response shape: expected nested data envelope, got ${typeof response.data}`);
  }

  return (response.data as { data: T }).data;
}

export async function uploadPaymentImportFile(
  payload: UploadPaymentImportRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentImportBatchResponseData> {
  const response = await uploadPaymentImport(
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentImportBatchResponseData>(response, 201);
}

export async function showPaymentImportBatchDetail(
  batchId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentImportBatchResponseData> {
  const response = await showPaymentImportBatch(
    batchId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentImportBatchResponseData>(response);
}

export async function updatePaymentImportRowDetail(
  importId: string,
  payload: UpdatePaymentImportRowRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentImportRowResponse> {
  const response = await updatePaymentImportRow(
    importId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentImportRowResponse>(response);
}

export async function reconcilePaymentImportBatchDetail(
  batchId: string,
  payload?: ReconcilePaymentImportBatchRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ReconciliationResultResponseData> {
  const response = await reconcilePaymentImportBatch(
    batchId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ReconciliationResultResponseData>(response);
}

export async function discardPaymentImportRowDetail(
  importId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentImportRowResponse> {
  const response = await discardPaymentImportRow(
    importId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentImportRowResponse>(response);
}
