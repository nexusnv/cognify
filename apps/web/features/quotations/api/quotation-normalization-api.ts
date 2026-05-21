import {
  approveQuotationNormalization as approveEndpoint,
  approveQuotationNormalizationWithWarnings as approveWithWarningsEndpoint,
  createQuotationNormalizationRevision as createRevisionEndpoint,
  listQuotationNormalizations as listEndpoint,
  retryQuotationVersionNormalization as retryEndpoint,
  saveQuotationNormalizationCorrections as saveCorrectionsEndpoint,
  saveQuotationNormalizationLineMappings as saveLineMappingsEndpoint,
  showQuotationNormalization as getEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  ApproveQuotationNormalizationRequest,
  ApproveQuotationNormalizationWithWarningsRequest,
  ListQuotationNormalizationsParams,
  QuotationNormalization,
  QuotationNormalizationSummary,
  SaveQuotationNormalizationCorrectionsRequest,
  SaveQuotationNormalizationLineMappingsRequest,
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

export async function listQuotationNormalizations(
  params: ListQuotationNormalizationsParams = {},
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationNormalizationSummary[]> {
  const response = await listEndpoint(params, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function showQuotationNormalization(
  normalizationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationNormalization> {
  const response = await getEndpoint(normalizationId, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function saveQuotationNormalizationCorrections(
  normalizationId: string,
  payload: SaveQuotationNormalizationCorrectionsRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationNormalization> {
  const response = await saveCorrectionsEndpoint(normalizationId, payload, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function saveQuotationNormalizationLineMappings(
  normalizationId: string,
  payload: SaveQuotationNormalizationLineMappingsRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationNormalization> {
  const response = await saveLineMappingsEndpoint(normalizationId, payload, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function approveQuotationNormalization(
  normalizationId: string,
  payload: ApproveQuotationNormalizationRequest = {},
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationNormalization> {
  const response = await approveEndpoint(normalizationId, payload, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function approveQuotationNormalizationWithWarnings(
  normalizationId: string,
  payload: ApproveQuotationNormalizationWithWarningsRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationNormalization> {
  const response = await approveWithWarningsEndpoint(normalizationId, payload, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function createQuotationNormalizationRevision(
  normalizationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationNormalization> {
  const response = await createRevisionEndpoint(normalizationId, withActiveTenantHeader(tenantId));
  if (response.status !== 201) throw response.data;

  return response.data.data;
}

export async function retryQuotationVersionNormalization(
  version: number,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationNormalization> {
  const response = await retryEndpoint(version, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}
