"use client";

import { useQuery } from "@tanstack/react-query";
import type { ListQuotationNormalizationsParams } from "@cognify/api-client/schemas";
import { listQuotationNormalizations } from "../api/quotation-normalization-api";

export const quotationNormalizationKeys = {
  all: ["quotation-normalizations"] as const,
  list: (filters: Record<string, unknown>) => [...quotationNormalizationKeys.all, "list", filters] as const,
  detail: (normalizationId: string) => [...quotationNormalizationKeys.all, "detail", normalizationId] as const,
};

export function useQuotationNormalizations(filters: ListQuotationNormalizationsParams = {}) {
  return useQuery({
    queryKey: quotationNormalizationKeys.list(filters),
    queryFn: () => listQuotationNormalizations(filters),
  });
}
