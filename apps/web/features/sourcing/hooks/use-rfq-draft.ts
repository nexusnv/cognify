import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { fetchRfqDraft } from "../api/rfq-api";

export const rfqDraftKeys = {
  detail: (rfqId: string, tenantId: string | null = getStoredActiveTenantId()) =>
    ["sourcing", "rfq", tenantId ?? "no-tenant", rfqId] as const,
};

export function useRfqDraft(rfqId: string) {
  return useQuery({
    queryKey: rfqDraftKeys.detail(rfqId),
    queryFn: () => fetchRfqDraft(rfqId),
    enabled: Boolean(rfqId),
  });
}
