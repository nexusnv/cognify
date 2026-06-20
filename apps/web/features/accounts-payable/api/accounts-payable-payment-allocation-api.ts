"use client";

import {
  listApPaymentAllocations,
  createApPaymentAllocation,
  showApPaymentAllocation,
} from "@cognify/api-client/endpoints";
import type {
  ApPaymentAllocation,
  AddApPaymentAllocationRequest,
  ListApPaymentAllocations200,
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

export type ApPaymentAllocationListResult = {
  allocations: ApPaymentAllocation[];
};

export async function listPaymentAllocations(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentAllocationListResult> {
  const response = await listApPaymentAllocations(
    handoffId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  if (typeof response.data !== "object" || response.data === null) {
    throw new Error(`Unexpected response shape: expected object, got ${typeof response.data}`);
  }

  const body = response.data as ListApPaymentAllocations200;

  return { allocations: body.data ?? [] };
}

export async function createPaymentAllocation(
  handoffId: string,
  payload: AddApPaymentAllocationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentAllocation> {
  const response = await createApPaymentAllocation(
    handoffId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentAllocation>(response, 201);
}

export async function showPaymentAllocation(
  allocationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentAllocation> {
  const response = await showApPaymentAllocation(
    allocationId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapResource<ApPaymentAllocation>(response);
}
