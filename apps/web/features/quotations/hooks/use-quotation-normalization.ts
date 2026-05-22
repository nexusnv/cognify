"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { showQuotationNormalization } from "../api/quotation-normalization-api";
import { quotationNormalizationKeys } from "./use-quotation-normalization-queue";

export function useQuotationNormalization(normalizationId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryNormalizationId = normalizationId ?? "no-normalization";

  return useQuery({
    queryKey: quotationNormalizationKeys.detail(queryNormalizationId, tenantId),
    queryFn: () => {
      if (!normalizationId) {
        throw new Error("Cannot load a quotation normalization without an id.");
      }

      return showQuotationNormalization(normalizationId, tenantId);
    },
    enabled: Boolean(normalizationId),
  });
}
