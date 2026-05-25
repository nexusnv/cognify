import {
  completeRfqScorecard as completeRfqScorecardEndpoint,
  createQuotationScoringTemplate as createQuotationScoringTemplateEndpoint,
  createRfqScorecard as createRfqScorecardEndpoint,
  deactivateQuotationScoringTemplate as deactivateQuotationScoringTemplateEndpoint,
  listQuotationScoringTemplates as listQuotationScoringTemplatesEndpoint,
  reopenRfqScorecard as reopenRfqScorecardEndpoint,
  showQuotationScoringTemplate as showQuotationScoringTemplateEndpoint,
  showRfqScorecard as showRfqScorecardEndpoint,
  updateQuotationScoringTemplate as updateQuotationScoringTemplateEndpoint,
  updateRfqScorecardScores as updateRfqScorecardScoresEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  QuotationScoringTemplate,
  RfqScorecard,
  SaveQuotationScoringTemplateRequest,
  UpdateRfqScorecardScoreEntryRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export type SaveScoringTemplateInput = SaveQuotationScoringTemplateRequest & {
  id?: string;
};

export type UpdateScoreEntryInput = UpdateRfqScorecardScoreEntryRequest;

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

export async function listScoringTemplates(
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationScoringTemplate[]> {
  const response = await listQuotationScoringTemplatesEndpoint(withActiveTenantHeader(tenantId)).catch(throwResponseData);

  return unwrapOk(response) as QuotationScoringTemplate[];
}

export async function getScoringTemplate(
  templateId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationScoringTemplate> {
  const response = await showQuotationScoringTemplateEndpoint(templateId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as QuotationScoringTemplate;
}

export async function saveScoringTemplate(
  input: SaveScoringTemplateInput,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationScoringTemplate> {
  const { id, ...payload } = input;
  const response = id
    ? await updateQuotationScoringTemplateEndpoint(id, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData)
    : await createQuotationScoringTemplateEndpoint(payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);

  return unwrapOk(response) as QuotationScoringTemplate;
}

export async function deactivateScoringTemplate(
  templateId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationScoringTemplate> {
  const response = await deactivateQuotationScoringTemplateEndpoint(templateId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as QuotationScoringTemplate;
}

export async function getRfqScorecard(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqScorecard> {
  const response = await showRfqScorecardEndpoint(rfqId, withActiveTenantHeader(tenantId)).catch(throwResponseData);

  return unwrapOk(response) as RfqScorecard;
}

export async function createRfqScorecard(
  rfqId: string,
  templateId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqScorecard> {
  const response = await createRfqScorecardEndpoint(rfqId, { templateId }, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqScorecard;
}

export async function updateRfqScorecardScores(
  rfqId: string,
  entries: UpdateScoreEntryInput[],
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqScorecard> {
  const response = await updateRfqScorecardScoresEndpoint(rfqId, { entries }, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqScorecard;
}

export async function completeRfqScorecard(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqScorecard> {
  const response = await completeRfqScorecardEndpoint(rfqId, withActiveTenantHeader(tenantId)).catch(throwResponseData);

  return unwrapOk(response) as RfqScorecard;
}

export async function reopenRfqScorecard(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqScorecard> {
  const response = await reopenRfqScorecardEndpoint(rfqId, withActiveTenantHeader(tenantId)).catch(throwResponseData);

  return unwrapOk(response) as RfqScorecard;
}
