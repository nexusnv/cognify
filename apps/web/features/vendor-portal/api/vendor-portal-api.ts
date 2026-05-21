import {
  createVendorPortalQuotationVersion as createVendorPortalQuotationVersionEndpoint,
  listVendorPortalQuotationVersions as listVendorPortalQuotationVersionsEndpoint,
  saveVendorPortalQuotationManualEntry as saveVendorPortalQuotationManualEntryEndpoint,
  showVendorPortalQuotation,
  showVendorPortalRfqInvitation,
  storeVendorPortalQuotationAttachment,
} from "@cognify/api-client/endpoints";
import type {
  QuotationVendorPortal,
  SaveQuotationManualEntryRequestForVendor,
  VendorCreateQuotationRevisionRequest,
  VendorPortalRfqInvitation,
  VendorQuotationVersion,
} from "@cognify/api-client/schemas";
import {
  toVendorRfqPortalViewModel,
  type VendorRfqPortalViewModel,
} from "../types/vendor-rfq-portal-view-model";

export async function fetchVendorPortalRfqInvitation(
  token: string,
): Promise<VendorRfqPortalViewModel> {
  const response = await showVendorPortalRfqInvitation(token);
  if (response.status !== 200) throw response.data;

  return toVendorRfqPortalViewModel(response.data.data as VendorPortalRfqInvitation);
}

export async function fetchVendorPortalQuotation(token: string): Promise<QuotationVendorPortal | null> {
  const response = await showVendorPortalQuotation(token);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function uploadVendorPortalQuotationAttachment(
  token: string,
  file: File,
): Promise<QuotationVendorPortal> {
  const response = await storeVendorPortalQuotationAttachment(token, { file });
  if (response.status !== 201) throw response.data;

  return response.data.data;
}

export async function saveVendorPortalQuotationManualEntry(
  token: string,
  payload: SaveQuotationManualEntryRequestForVendor,
): Promise<QuotationVendorPortal> {
  const response = await saveVendorPortalQuotationManualEntryEndpoint(token, payload);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function listVendorPortalQuotationVersions(token: string): Promise<VendorQuotationVersion[]> {
  const response = await listVendorPortalQuotationVersionsEndpoint(token);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function createVendorPortalQuotationVersion(
  token: string,
  payload: VendorCreateQuotationRevisionRequest,
): Promise<VendorQuotationVersion> {
  const response = await createVendorPortalQuotationVersionEndpoint(token, payload);
  if (response.status !== 201) throw response.data;

  return response.data.data;
}
