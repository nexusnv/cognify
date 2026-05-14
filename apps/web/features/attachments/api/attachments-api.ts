import {
  deleteAttachment as deleteAttachmentEndpoint,
  downloadAttachment as downloadAttachmentEndpoint,
  getDownloadAttachmentUrl,
  getPreviewAttachmentUrl,
  listRequisitionAttachments,
  previewAttachment as previewAttachmentEndpoint,
  type uploadRequisitionAttachmentResponse,
} from "@cognify/api-client/endpoints";
import { cognifyFetch } from "@cognify/api-client";
import type { Attachment } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";

export async function listAttachments(requisitionId: string) {
  const response = await listRequisitionAttachments(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as Attachment[];
}

export async function uploadAttachment(requisitionId: string, file: File) {
  const formData = new FormData();
  formData.append("file", file, file.name);
  formData.append("filename", file.name);
  formData.append("mimeType", file.type || "application/octet-stream");
  formData.append("sizeBytes", String(file.size));

  const response = await cognifyFetch<uploadRequisitionAttachmentResponse>(
    `/api/requisitions/${requisitionId}/attachments`,
    {
      ...withActiveTenantHeader(),
      method: "POST",
      body: formData,
    },
  );
  if (response.status !== 201) throw response.data;
  return response.data.data as Attachment;
}

export async function deleteAttachment(attachmentId: string) {
  const response = await deleteAttachmentEndpoint(attachmentId, withActiveTenantHeader());
  if (response.status !== 204) throw response.data;
}

export async function previewAttachmentBlob(attachmentId: string) {
  const response = await previewAttachmentEndpoint(attachmentId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
}

export async function downloadAttachmentBlob(attachmentId: string) {
  const response = await downloadAttachmentEndpoint(attachmentId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
}

export function attachmentPreviewUrl(attachmentId: string) {
  return getPreviewAttachmentUrl(attachmentId);
}

export function attachmentDownloadUrl(attachmentId: string) {
  return getDownloadAttachmentUrl(attachmentId);
}

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  return tenantId
    ? {
        headers: {
          "X-Tenant-Id": tenantId,
        },
      }
    : undefined;
}
