"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { showQuotationComparison } from "../api/quotation-comparison-api";

export const quotationComparisonKeys = {
  all: (tenantId: string | null = getStoredActiveTenantId()) =>
    ["quotation-comparisons", tenantId ?? "no-tenant"] as const,
  detail: (rfqId: string, tenantId: string | null = getStoredActiveTenantId()) =>
    [...quotationComparisonKeys.all(tenantId), "detail", rfqId] as const,
};

export function useQuotationComparison(rfqId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryRfqId = rfqId ?? "no-rfq";

  return useQuery({
    queryKey: quotationComparisonKeys.detail(queryRfqId, tenantId),
    queryFn: () => {
      if (!rfqId) {
        throw new Error("Cannot load a quotation comparison without an rfq id.");
      }

      return showQuotationComparison(rfqId, tenantId);
    },
    enabled: Boolean(rfqId),
  });
}
