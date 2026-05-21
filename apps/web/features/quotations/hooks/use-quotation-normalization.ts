"use client";

import { useQuery } from "@tanstack/react-query";
import { showQuotationNormalization } from "../api/quotation-normalization-api";
import { quotationNormalizationKeys } from "./use-quotation-normalization-queue";

export function useQuotationNormalization(normalizationId: string | null | undefined) {
  return useQuery({
    queryKey: quotationNormalizationKeys.detail(normalizationId ?? ""),
    queryFn: () => showQuotationNormalization(normalizationId as string),
    enabled: Boolean(normalizationId),
  });
}
