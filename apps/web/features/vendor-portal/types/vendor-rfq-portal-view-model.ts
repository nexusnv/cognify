import type { VendorPortalRfqInvitation } from "@cognify/api-client/schemas";

export type VendorRfqPortalViewModel = {
  invitation: VendorPortalRfqInvitation["invitation"];
  tenant: VendorPortalRfqInvitation["tenant"];
  vendor: VendorPortalRfqInvitation["vendor"];
  rfq: VendorPortalRfqInvitation["rfq"];
  deadlineSummary: string;
};

export function toVendorRfqPortalViewModel(
  payload: VendorPortalRfqInvitation,
): VendorRfqPortalViewModel {
  return {
    invitation: payload.invitation,
    tenant: payload.tenant,
    vendor: payload.vendor,
    rfq: payload.rfq,
    deadlineSummary: payload.invitation.portalExpiresAt
      ? `Portal access expires ${formatDateTime(payload.invitation.portalExpiresAt)}`
      : "Portal access expiry has not been recorded.",
  };
}

export function formatDateTime(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
