import {
  saveRfqAwardRecommendation as saveRfqAwardRecommendationEndpoint,
  showRfqAwardRecommendation as showRfqAwardRecommendationEndpoint,
  submitRfqAwardRecommendation as submitRfqAwardRecommendationEndpoint,
  withdrawRfqAwardRecommendation as withdrawRfqAwardRecommendationEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  RfqAwardRecommendation,
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
  WithdrawRfqAwardRecommendationRequest,
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

function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw (error as { data: unknown }).data;
  }

  throw error;
}

function unwrapOk(response: { status: number; data: unknown }, expectedStatus = 200): unknown {
  if (response.status !== expectedStatus) throw response.data;

  return (response.data as { data: unknown }).data;
}

export async function showRfqAwardRecommendation(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqAwardRecommendation> {
  const response = await showRfqAwardRecommendationEndpoint(rfqId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqAwardRecommendation;
}

export async function saveRfqAwardRecommendation(
  rfqId: string,
  payload: SaveRfqAwardRecommendationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqAwardRecommendation> {
  const response = await saveRfqAwardRecommendationEndpoint(rfqId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqAwardRecommendation;
}

export async function submitRfqAwardRecommendation(
  rfqId: string,
  payload?: SubmitRfqAwardRecommendationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqAwardRecommendation> {
  const response = await submitRfqAwardRecommendationEndpoint(rfqId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqAwardRecommendation;
}

export async function withdrawRfqAwardRecommendation(
  rfqId: string,
  payload: WithdrawRfqAwardRecommendationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqAwardRecommendation> {
  const response = await withdrawRfqAwardRecommendationEndpoint(rfqId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqAwardRecommendation;
}
