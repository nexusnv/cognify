import {
  showVendorPortalQuotation,
  showVendorPortalRfqInvitation,
  storeVendorPortalQuotationAttachment,
} from "@cognify/api-client/endpoints";
import type { Quotation, VendorPortalRfqInvitation } from "@cognify/api-client/schemas";
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

export async function fetchVendorPortalQuotation(token: string): Promise<Quotation | null> {
  const response = await showVendorPortalQuotation(token);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function uploadVendorPortalQuotationAttachment(
  token: string,
  file: File,
): Promise<Quotation> {
  const response = await storeVendorPortalQuotationAttachment(token, { file });
  if (response.status !== 201) throw response.data;

  return response.data.data;
}
