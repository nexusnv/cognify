"use client";

import {
  scheduleApPaymentHandoff,
  markApPaymentHandoffPaid,
  closeApPaymentHandoffWithVariance,
  markApPaymentHandoffFailed,
  voidApPaymentHandoff,
  rescheduleApPaymentHandoff,
} from "@cognify/api-client/endpoints";
import type {
  ApPaymentHandoff,
  ScheduleApPaymentHandoffRequest,
  MarkApPaymentHandoffPaidRequest,
  CloseApPaymentHandoffWithVarianceRequest,
  MarkApPaymentHandoffFailedRequest,
  VoidApPaymentHandoffRequest,
  RescheduleApPaymentHandoffRequest,
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

export async function schedulePaymentHandoff(
  handoffId: string,
  payload: ScheduleApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await scheduleApPaymentHandoff(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function markPaymentHandoffPaid(
  handoffId: string,
  payload: MarkApPaymentHandoffPaidRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await markApPaymentHandoffPaid(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function closePaymentHandoffWithVariance(
  handoffId: string,
  payload: CloseApPaymentHandoffWithVarianceRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await closeApPaymentHandoffWithVariance(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function markPaymentHandoffFailed(
  handoffId: string,
  payload: MarkApPaymentHandoffFailedRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await markApPaymentHandoffFailed(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function voidPaymentHandoff(
  handoffId: string,
  payload: VoidApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await voidApPaymentHandoff(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}

export async function reschedulePaymentHandoff(
  handoffId: string,
  payload: RescheduleApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const response = await rescheduleApPaymentHandoff(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentHandoff>(response);
}
