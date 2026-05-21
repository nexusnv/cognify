"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { CreateQuotationRevisionRequest } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { quotationKeys } from "./use-quotation-upload";
import {
  createQuotationVersion,
  listQuotationVersions,
  showQuotationVersion,
} from "../api/quotation-api";

export const quotationVersionKeys = {
  byQuotation: (quotationId: string, tenantId: string | null = getStoredActiveTenantId()) =>
    ["sourcing", "quotation-versions", tenantId ?? "no-tenant", quotationId] as const,
  list: (quotationId: string, tenantId: string | null = getStoredActiveTenantId()) =>
    [...quotationVersionKeys.byQuotation(quotationId, tenantId), "list"] as const,
  detail: (
    quotationId: string,
    versionNumber: number,
    tenantId: string | null = getStoredActiveTenantId(),
  ) => [...quotationVersionKeys.byQuotation(quotationId, tenantId), "detail", versionNumber] as const,
};

export function useQuotationVersions(quotationId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: quotationVersionKeys.list(quotationId ?? "no-quotation", tenantId),
    queryFn: () => listQuotationVersions(quotationId as string, tenantId),
    enabled: Boolean(quotationId),
    retry: false,
  });
}

export function useQuotationVersion(
  quotationId: string | null | undefined,
  versionNumber: number | null | undefined,
) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: quotationVersionKeys.detail(quotationId ?? "no-quotation", versionNumber ?? -1, tenantId),
    queryFn: () => showQuotationVersion(quotationId as string, versionNumber as number, tenantId),
    enabled: Boolean(quotationId && versionNumber !== null && versionNumber !== undefined),
    retry: false,
  });
}

export function useCreateQuotationVersion(quotationId: string | null | undefined, invitationId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: CreateQuotationRevisionRequest) => {
      if (!quotationId) {
        throw new Error("Cannot create a quotation version without a quotation id.");
      }

      return createQuotationVersion(quotationId, payload, tenantId);
    },
    onSuccess: (version) => {
      if (!quotationId) return;

      queryClient.setQueryData(
        quotationVersionKeys.detail(quotationId, version.versionNumber, tenantId),
        version,
      );
      queryClient.invalidateQueries({ queryKey: quotationVersionKeys.byQuotation(quotationId, tenantId) });
      queryClient.invalidateQueries({ queryKey: quotationKeys.byInvitation(invitationId, tenantId) });
    },
  });
}
