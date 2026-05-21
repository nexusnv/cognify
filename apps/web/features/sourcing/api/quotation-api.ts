import {
  createQuotationVersion as createQuotationVersionEndpoint,
  listQuotationAttachments as listQuotationAttachmentsEndpoint,
  listQuotationVersions as listQuotationVersionsEndpoint,
  saveRfqInvitationQuotationManualEntry as saveRfqInvitationQuotationManualEntryEndpoint,
  saveQuotationManualEntry as saveQuotationManualEntryEndpoint,
  showRfqInvitationQuotation as showRfqInvitationQuotationEndpoint,
  showQuotationVersion as showQuotationVersionEndpoint,
  storeRfqInvitationQuotationAttachment as storeRfqInvitationQuotationAttachmentEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  Attachment,
  CreateQuotationRevisionRequest,
  Quotation,
  QuotationVersion,
  SaveQuotationManualEntryRequest,
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

export async function fetchRfqInvitationQuotation(
  invitationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Quotation | null> {
  const response = await showRfqInvitationQuotationEndpoint(invitationId, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function uploadRfqInvitationQuotationAttachment(
  invitationId: string,
  file: File,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Quotation> {
  const response = await storeRfqInvitationQuotationAttachmentEndpoint(
    invitationId,
    { file },
    withActiveTenantHeader(tenantId),
  );
  if (response.status !== 201) throw response.data;

  return response.data.data;
}

export async function fetchQuotationAttachments(
  quotationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Attachment[]> {
  const response = await listQuotationAttachmentsEndpoint(quotationId, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function listQuotationVersions(
  quotationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationVersion[]> {
  const response = await listQuotationVersionsEndpoint(quotationId, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function showQuotationVersion(
  quotationId: string,
  versionNumber: number,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationVersion> {
  const response = await showQuotationVersionEndpoint(
    quotationId,
    versionNumber,
    withActiveTenantHeader(tenantId),
  );
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function createQuotationVersion(
  quotationId: string,
  payload: CreateQuotationRevisionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationVersion> {
  const response = await createQuotationVersionEndpoint(
    quotationId,
    payload,
    withActiveTenantHeader(tenantId),
  );
  if (response.status !== 201) throw response.data;

  return response.data.data;
}

export async function saveQuotationManualEntry(
  quotationId: string,
  payload: SaveQuotationManualEntryRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Quotation> {
  const response = await saveQuotationManualEntryEndpoint(quotationId, payload, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function saveRfqInvitationQuotationManualEntry(
  invitationId: string,
  payload: SaveQuotationManualEntryRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Quotation> {
  const response = await saveRfqInvitationQuotationManualEntryEndpoint(
    invitationId,
    payload,
    withActiveTenantHeader(tenantId),
  );
  if (response.status !== 200) throw response.data;

  return response.data.data;
}
