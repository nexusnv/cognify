"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { getRfqScorecard } from "../api/quotation-scoring-api";
import { quotationScoringKeys } from "./use-quotation-scoring-templates";

export function useRfqScorecard(rfqId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryRfqId = rfqId ?? "no-rfq";

  return useQuery({
    queryKey: quotationScoringKeys.scorecard(tenantId, queryRfqId),
    queryFn: () => {
      if (!rfqId) {
        throw new Error("Cannot load an RFQ scorecard without an RFQ id.");
      }

      return getRfqScorecard(rfqId, tenantId);
    },
    enabled: Boolean(rfqId),
  });
}
