"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchVendorPortalRfqInvitation } from "../api/vendor-portal-api";

export const vendorPortalKeys = {
  invitation: (token: string) => ["vendor-portal", "rfq-invitation", token] as const,
  quotation: (token: string) => ["vendor-portal", "quotation", token] as const,
  quotationVersions: (token: string) => ["vendor-portal", "quotation", token, "versions"] as const,
};

export function useVendorRfqInvitation(token: string) {
  return useQuery({
    queryKey: vendorPortalKeys.invitation(token),
    queryFn: () => fetchVendorPortalRfqInvitation(token),
    enabled: token.length > 0,
    retry: false,
  });
}
