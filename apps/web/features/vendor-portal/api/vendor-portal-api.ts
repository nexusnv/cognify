import { showVendorPortalRfqInvitation } from "@cognify/api-client/endpoints";
import type { VendorPortalRfqInvitation } from "@cognify/api-client/schemas";
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
