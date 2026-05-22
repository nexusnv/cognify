"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type {
  ApproveQuotationNormalizationRequest,
  ApproveQuotationNormalizationWithWarningsRequest,
  QuotationNormalization,
  SaveQuotationNormalizationCorrectionsRequest,
  SaveQuotationNormalizationLineMappingsRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  approveQuotationNormalization,
  approveQuotationNormalizationWithWarnings,
  createQuotationNormalizationRevision,
  retryQuotationVersionNormalization,
  saveQuotationNormalizationCorrections,
  saveQuotationNormalizationLineMappings,
} from "../api/quotation-normalization-api";
import { quotationNormalizationKeys } from "./use-quotation-normalization-queue";

function invalidateQuotationNormalization(
  queryClient: ReturnType<typeof useQueryClient>,
  normalization: QuotationNormalization,
  tenantId: string | null,
  touchedNormalizationIds: string[] = [],
) {
  queryClient.invalidateQueries({ queryKey: quotationNormalizationKeys.all(tenantId) });
  for (const normalizationId of new Set([normalization.id, ...touchedNormalizationIds])) {
    queryClient.invalidateQueries({ queryKey: quotationNormalizationKeys.detail(normalizationId, tenantId) });
  }
}

export function useSaveQuotationNormalizationCorrections(normalizationId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: SaveQuotationNormalizationCorrectionsRequest) =>
      saveQuotationNormalizationCorrections(normalizationId, payload, tenantId),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization, tenantId),
  });
}

export function useSaveQuotationNormalizationLineMappings(normalizationId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: SaveQuotationNormalizationLineMappingsRequest) =>
      saveQuotationNormalizationLineMappings(normalizationId, payload, tenantId),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization, tenantId),
  });
}

export function useApproveQuotationNormalization(normalizationId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: ApproveQuotationNormalizationRequest = {}) =>
      approveQuotationNormalization(normalizationId, payload, tenantId),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization, tenantId),
  });
}

export function useApproveQuotationNormalizationWithWarnings(normalizationId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: ApproveQuotationNormalizationWithWarningsRequest) =>
      approveQuotationNormalizationWithWarnings(normalizationId, payload, tenantId),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization, tenantId),
  });
}

export function useCreateQuotationNormalizationRevision(normalizationId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: () => createQuotationNormalizationRevision(normalizationId, tenantId),
    onSuccess: (normalization) =>
      invalidateQuotationNormalization(queryClient, normalization, tenantId, [normalizationId]),
  });
}

export function useRetryQuotationVersionNormalization(version: number) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: () => retryQuotationVersionNormalization(version, tenantId),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization, tenantId),
  });
}
