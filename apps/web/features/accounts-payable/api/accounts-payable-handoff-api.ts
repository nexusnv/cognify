"use client";

import {
  cancelApPaymentHandoff,
  createApPaymentHandoff,
  exportApPaymentHandoffCsv,
  exportApPaymentHandoffJson,
  listApPaymentHandoffs,
  markApPaymentHandoffReady,
  recordApPaymentHandoffCsvExport,
  recordApPaymentHandoffJsonExport,
  refreshApPaymentHandoffSnapshot,
  showApPaymentHandoff,
  updateApPaymentHandoff,
} from "@cognify/api-client/endpoints";
import type {
  ApPaymentHandoff,
  ApPaymentHandoffListResponse,
  CancelApPaymentHandoffRequest,
  CreateApPaymentHandoffRequest,
  ListApPaymentHandoffsParams,
  MarkApPaymentHandoffReadyRequest,
  RefreshApPaymentHandoffSnapshotRequest,
  UpdateApPaymentHandoffRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData } from "./api-helpers";

/**
 * Unwrap a single-resource envelope (`{ data: <resource> }`) for a 2xx
 * response, rethrowing the payload otherwise so the UI can surface errors.
 */
function unwrapResource<T>(
  response: { status: number; data?: { data?: T } | unknown },
  success = 200,
): T {
  if (response.status !== success) {
    throw response.data ?? response;
  }

  if (typeof response.data !== 'object' || response.data === null || !('data' in response.data)) {
    throw new Error(`Unexpected response shape: expected nested data envelope, got ${typeof response.data}`);
  }

  return (response.data as { data: T }).data;
}

export type ApPaymentHandoffListResult = {
  handoffs: ApPaymentHandoff[];
  meta: ApPaymentHandoffListResponse["meta"];
};

export async function listPaymentHandoffs(
  params?: ListApPaymentHandoffsParams,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoffListResult> {
  const response = await listApPaymentHandoffs(params, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  if (typeof response.data !== 'object' || response.data === null) {
    throw new Error(`Unexpected response shape: expected object, got ${typeof response.data}`);
  }

  const body = response.data as ApPaymentHandoffListResponse;

  return { handoffs: body.data, meta: body.meta };
}

export async function createPaymentHandoff(
  payload: CreateApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await createApPaymentHandoff(
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response, 201);
}

export async function showPaymentHandoff(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await showApPaymentHandoff(
    handoffId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function updatePaymentHandoff(
  handoffId: string,
  payload: UpdateApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await updateApPaymentHandoff(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function refreshPaymentHandoffSnapshot(
  handoffId: string,
  payload?: RefreshApPaymentHandoffSnapshotRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await refreshApPaymentHandoffSnapshot(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function markPaymentHandoffReady(
  handoffId: string,
  payload: MarkApPaymentHandoffReadyRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await markApPaymentHandoffReady(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function cancelPaymentHandoff(
  handoffId: string,
  payload: CancelApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await cancelApPaymentHandoff(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export type ApPaymentHandoffJsonExport = {
  exportedAt?: string;
  format?: string;
  handoff?: {
    id?: string;
    number?: string;
    status?: string;
    currency?: string;
    totalAmount?: string;
    effectivePaymentDate?: string | null;
    notes?: string | null;
    remittanceReference?: string | null;
    invoices?: Array<{
      id?: string;
      number?: string;
      invoiceNumber?: string;
      totalAmount?: string | null;
      dueDate?: string | null;
      currency?: string;
    }>;
  };
};

export async function exportPaymentHandoffJson(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoffJsonExport> {
  const response = await exportApPaymentHandoffJson(
    handoffId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return response.data as ApPaymentHandoffJsonExport;
}

export async function exportPaymentHandoffCsv(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<string> {
  const response = await exportApPaymentHandoffCsv(
    handoffId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return response.data as string;
}

/**
 * Record that a handoff was exported, transitioning ready → exported and
 * stamping the export metadata. The export GET endpoints return the payload
 * without mutating state; this POST records the export against the handoff.
 */
export async function recordPaymentHandoffJsonExport(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoffJsonExport> {
  const response = await recordApPaymentHandoffJsonExport(
    handoffId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return response.data as ApPaymentHandoffJsonExport;
}

export async function recordPaymentHandoffCsvExport(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<string> {
  const response = await recordApPaymentHandoffCsvExport(
    handoffId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return response.data as string;
}
