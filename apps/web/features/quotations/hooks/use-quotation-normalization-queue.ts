"use client";

import { useQuery } from "@tanstack/react-query";
import type { ListQuotationNormalizationsParams } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { listQuotationNormalizations } from "../api/quotation-normalization-api";

export const quotationNormalizationKeys = {
  all: (tenantId: string | null = getStoredActiveTenantId()) =>
    ["quotation-normalizations", tenantId ?? "no-tenant"] as const,
  list: (
    filters: Record<string, unknown>,
    tenantId: string | null = getStoredActiveTenantId(),
  ) => [...quotationNormalizationKeys.all(tenantId), "list", filters] as const,
  detail: (
    normalizationId: string,
    tenantId: string | null = getStoredActiveTenantId(),
  ) => [...quotationNormalizationKeys.all(tenantId), "detail", normalizationId] as const,
};

export function useQuotationNormalizations(filters: ListQuotationNormalizationsParams = {}) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: quotationNormalizationKeys.list(filters, tenantId),
    queryFn: () => listQuotationNormalizations(filters, tenantId),
  });
}
