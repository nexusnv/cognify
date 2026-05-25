"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { showRfqAwardRecommendation } from "../api/quotation-award-recommendation-api";

export function rfqAwardRecommendationQueryKey(rfqId: string, tenantId?: string | null) {
  return ["rfq-award-recommendation", tenantId ?? "no-tenant", rfqId] as const;
}

export function useRfqAwardRecommendation(rfqId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryRfqId = rfqId ?? "no-rfq";

  return useQuery({
    queryKey: rfqAwardRecommendationQueryKey(queryRfqId, tenantId),
    queryFn: () => {
      if (!rfqId) {
        throw new Error("Cannot load RFQ award recommendation without an RFQ id.");
      }

      return showRfqAwardRecommendation(rfqId, tenantId);
    },
    enabled: Boolean(rfqId && tenantId),
  });
}
