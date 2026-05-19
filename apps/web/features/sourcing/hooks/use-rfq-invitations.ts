import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { fetchRfqInvitations } from "../api/rfq-invitation-api";

export const rfqInvitationKeys = {
  list: (rfqId: string, tenantId: string | null = getStoredActiveTenantId()) =>
    ["sourcing", "rfq-invitations", tenantId ?? "no-tenant", rfqId] as const,
};

export function useRfqInvitations(rfqId: string) {
  return useQuery({
    queryKey: rfqInvitationKeys.list(rfqId),
    queryFn: () => fetchRfqInvitations(rfqId),
    enabled: Boolean(rfqId),
  });
}
