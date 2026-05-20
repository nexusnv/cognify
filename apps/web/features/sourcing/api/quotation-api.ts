import {
  listQuotationAttachments as listQuotationAttachmentsEndpoint,
  showRfqInvitationQuotation as showRfqInvitationQuotationEndpoint,
  storeRfqInvitationQuotationAttachment as storeRfqInvitationQuotationAttachmentEndpoint,
} from "@cognify/api-client/endpoints";
import type { Attachment, Quotation } from "@cognify/api-client/schemas";
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
