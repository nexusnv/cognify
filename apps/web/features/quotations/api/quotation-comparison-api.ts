import {
  createQuotationComparisonNote as createQuotationComparisonNoteEndpoint,
  deleteQuotationComparisonNote as deleteQuotationComparisonNoteEndpoint,
  showQuotationComparison as showQuotationComparisonEndpoint,
  updateQuotationComparisonNote as updateQuotationComparisonNoteEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  QuotationComparison,
  QuotationComparisonNote,
  SaveQuotationComparisonNoteRequest,
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

export async function showQuotationComparison(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationComparison> {
  const response = await showQuotationComparisonEndpoint(rfqId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function createQuotationComparisonNote(
  rfqId: string,
  payload: SaveQuotationComparisonNoteRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationComparisonNote> {
  const response = await createQuotationComparisonNoteEndpoint(
    rfqId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  if (response.status !== 201) throw response.data;

  return response.data.data;
}

export async function updateQuotationComparisonNote(
  rfqId: string,
  noteId: string,
  payload: SaveQuotationComparisonNoteRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationComparisonNote> {
  const response = await updateQuotationComparisonNoteEndpoint(
    rfqId,
    noteId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function deleteQuotationComparisonNote(
  rfqId: string,
  noteId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<void> {
  const response = await deleteQuotationComparisonNoteEndpoint(rfqId, noteId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  if (response.status !== 204) throw response.data;
}
