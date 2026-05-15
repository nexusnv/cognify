import {
  applyRequisitionTemplate as applyRequisitionTemplateEndpoint,
  createRequisition,
  getRequisition as getRequisitionEndpoint,
  getRequisitionIntakeOptions as getRequisitionIntakeOptionsEndpoint,
  listRequisitionActivity,
  listRequisitionLineItemSuggestions as listRequisitionLineItemSuggestionsEndpoint,
  listRequisitionTemplates as listRequisitionTemplatesEndpoint,
  listRequisitions as listRequisitionsEndpoint,
  submitRequisition as submitRequisitionEndpoint,
  updateRequisition,
} from "@cognify/api-client/endpoints";
import type {
  ApplyRequisitionTemplateRequest,
  ListRequisitionActivity200,
  ListRequisitionsParams,
  Requisition as ApiRequisition,
  RequisitionIntakeOptions as ApiRequisitionIntakeOptions,
  RequisitionItemSuggestion as ApiRequisitionItemSuggestion,
  RequisitionListResponse as ApiRequisitionListResponse,
  RequisitionTemplate as ApiRequisitionTemplate,
} from "@cognify/api-client/schemas";
import type {
  RequisitionIntakeOptions,
  Requisition,
  RequisitionFormValues,
  RequisitionLineItem,
  RequisitionListResponse,
  RequisitionItemSuggestion,
  RequisitionTemplate,
  RequisitionTemplateMode,
} from "../types/requisition-view-model";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";

type RequisitionQuery = {
  search?: string;
  status?: string;
  owner?: string;
  neededByFrom?: string;
  neededByTo?: string;
};

export async function listRequisitions(query: RequisitionQuery = {}) {
  const response = await listRequisitionsEndpoint(query as ListRequisitionsParams, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapRequisitionListResponse(response.data);
}

export async function getRequisition(requisitionId: string) {
  const response = await getRequisitionEndpoint(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapRequisition(response.data.data);
}

export async function getRequisitionActivity(requisitionId: string) {
  const response = await listRequisitionActivity(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data satisfies ListRequisitionActivity200;
}

export async function createRequisitionDraft(values: RequisitionFormValues) {
  const response = await createRequisition(values, withActiveTenantHeader());
  if (response.status !== 201) throw response.data;
  return mapRequisition(response.data.data);
}

export async function updateRequisitionDraft(
  requisitionId: string,
  values: RequisitionFormValues,
  lockVersion: number,
) {
  const response = await updateRequisition(
    requisitionId,
    { ...values, lockVersion },
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return mapRequisition(response.data.data);
}

export async function listRequisitionTemplates() {
  const response = await listRequisitionTemplatesEndpoint(withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data.map(mapRequisitionTemplate);
}

export async function applyRequisitionTemplate(
  requisitionId: string,
  templateId: string,
  mode: RequisitionTemplateMode,
  lockVersion: number,
) {
  const response = await applyRequisitionTemplateEndpoint(
    requisitionId,
    {
      templateId,
      mode,
      lockVersion,
    } satisfies ApplyRequisitionTemplateRequest,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return mapRequisition(response.data.data);
}

export async function listRequisitionLineItemSuggestions(query: {
  search?: string;
  category?: string;
  currency?: string;
}) {
  const response = await listRequisitionLineItemSuggestionsEndpoint(query, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data.map(mapRequisitionItemSuggestion);
}

export async function getRequisitionIntakeOptions() {
  const response = await getRequisitionIntakeOptionsEndpoint(withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapRequisitionIntakeOptions(response.data.data);
}

export async function submitRequisition(requisitionId: string) {
  const response = await submitRequisitionEndpoint(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return { data: mapRequisition(response.data.data) };
}

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

function mapRequisitionListResponse(response: ApiRequisitionListResponse): RequisitionListResponse {
  return {
    data: response.data.map(mapRequisition),
    meta: response.meta,
  };
}

function mapRequisition(requisition: ApiRequisition): Requisition {
  return {
    id: requisition.id,
    number: requisition.number,
    tenantId: requisition.tenantId,
    title: requisition.title,
    status: requisition.status,
    lockVersion: requisition.lockVersion,
    businessJustification: requisition.businessJustification ?? "",
    neededByDate: requisition.neededByDate ?? "",
    department: requisition.department ?? undefined,
    projectId: requisition.projectId ?? undefined,
    costCenter: requisition.costCenter ?? undefined,
    deliveryLocation: requisition.deliveryLocation ?? undefined,
    currency: requisition.currency ?? "MYR",
    estimatedTotal: requisition.estimatedTotal,
    requester: {
      id: requisition.requester.id,
      name: requisition.requester.name,
      email: requisition.requester.email ?? "",
    },
    lineItems: requisition.lineItems.map(mapRequisitionLineItem),
    createdAt: requisition.createdAt,
    updatedAt: requisition.updatedAt,
    submittedAt: requisition.submittedAt ?? undefined,
    permissions: requisition.permissions,
  };
}

function mapRequisitionLineItem(lineItem: ApiRequisition["lineItems"][number]): RequisitionLineItem {
  return {
    id: lineItem.id ?? undefined,
    name: lineItem.name,
    description: lineItem.description ?? undefined,
    quantity: lineItem.quantity,
    unit: lineItem.unit,
    estimatedUnitPrice: lineItem.estimatedUnitPrice,
    currency: lineItem.currency,
    estimatedLineTotal: lineItem.estimatedLineTotal ?? undefined,
  };
}

function mapRequisitionTemplate(template: ApiRequisitionTemplate): RequisitionTemplate {
  return {
    id: template.id,
    name: template.name,
    description: template.description,
    category: template.category,
    defaults: mapTemplateDefaults(template.defaults),
  };
}

function mapTemplateDefaults(defaults: unknown): Partial<RequisitionFormValues> {
  const record = toRecord(defaults);
  const lineItems = Array.isArray(record.lineItems)
    ? record.lineItems.map(mapTemplateLineItem).filter((lineItem): lineItem is RequisitionLineItem => lineItem !== null)
    : undefined;

  return {
    title: toOptionalString(record.title),
    businessJustification: toOptionalString(record.businessJustification),
    neededByDate: toOptionalString(record.neededByDate),
    department: toOptionalString(record.department),
    projectId: toOptionalString(record.projectId),
    costCenter: toOptionalString(record.costCenter),
    deliveryLocation: toOptionalString(record.deliveryLocation),
    currency: toOptionalString(record.currency),
    lineItems,
  };
}

function mapTemplateLineItem(lineItem: unknown): RequisitionLineItem | null {
  const record = toRecord(lineItem);
  const name = toOptionalString(record.name);

  if (!name) {
    return null;
  }

  return {
    id: toOptionalString(record.id),
    name,
    description: toOptionalString(record.description),
    quantity: toNumber(record.quantity) ?? 1,
    unit: toOptionalString(record.unit) ?? "each",
    estimatedUnitPrice: toNumber(record.estimatedUnitPrice) ?? 0,
    currency: toOptionalString(record.currency) ?? "MYR",
    estimatedLineTotal: toNumber(record.estimatedLineTotal) ?? undefined,
  };
}

function mapRequisitionItemSuggestion(
  suggestion: ApiRequisitionItemSuggestion,
): RequisitionItemSuggestion {
  return {
    id: suggestion.id,
    name: suggestion.name,
    category: suggestion.category ?? undefined,
    unit: suggestion.unit,
    estimatedUnitPrice: suggestion.estimatedUnitPrice,
    currency: suggestion.currency,
  };
}

function mapRequisitionIntakeOptions(
  options: ApiRequisitionIntakeOptions,
): RequisitionIntakeOptions {
  return {
    departments: options.departments.map((department) => ({ name: department.name })),
    costCenters: options.costCenters.map((costCenter) => ({
      code: costCenter.code,
      name: costCenter.name,
    })),
    currencies: [...options.currencies],
    units: [...options.units],
  };
}

function toRecord(value: unknown): Record<string, unknown> {
  return typeof value === "object" && value !== null ? (value as Record<string, unknown>) : {};
}

function toOptionalString(value: unknown): string | undefined {
  return typeof value === "string" && value.trim() !== "" ? value : undefined;
}

function toNumber(value: unknown): number | undefined {
  return typeof value === "number" && Number.isFinite(value) ? value : undefined;
}
