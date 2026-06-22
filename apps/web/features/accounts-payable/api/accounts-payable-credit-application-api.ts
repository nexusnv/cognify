import {
  listCreditApplications,
  createCreditApplication,
  showCreditApplication,
  voidCreditApplication,
} from "@cognify/api-client/endpoints";
import type {
  CreditApplication,
  CreateCreditApplicationRequest,
  VoidCreditApplicationRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData, unwrapData } from "./api-helpers";

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

export async function listCreditApplicationsForMemo(
  creditMemoId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<CreditApplication[]> {
  const response = await listCreditApplications(creditMemoId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  const body = response.data as { data?: CreditApplication[] };
  return body.data ?? [];
}

export async function createCreditApplicationApi(
  creditMemoId: string,
  payload: CreateCreditApplicationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<CreditApplication> {
  const response = await createCreditApplication(creditMemoId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapResource<CreditApplication>(response, 201);
}

export async function showCreditApplicationApi(
  applicationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<CreditApplication> {
  const response = await showCreditApplication(applicationId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<CreditApplication>(response);
}

export async function voidCreditApplicationApi(
  applicationId: string,
  payload: VoidCreditApplicationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<CreditApplication> {
  const response = await voidCreditApplication(applicationId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<CreditApplication>(response);
}
