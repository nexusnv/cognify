"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  ApproveQuotationNormalizationRequest,
  ApproveQuotationNormalizationWithWarningsRequest,
  ListQuotationNormalizationsParams,
  QuotationNormalization,
  SaveQuotationNormalizationCorrectionsRequest,
  SaveQuotationNormalizationLineMappingsRequest,
} from "@cognify/api-client/schemas";
import {
  approveQuotationNormalization,
  approveQuotationNormalizationWithWarnings,
  createQuotationNormalizationRevision,
  listQuotationNormalizations,
  retryQuotationVersionNormalization,
  saveQuotationNormalizationCorrections,
  saveQuotationNormalizationLineMappings,
  showQuotationNormalization,
} from "../api/quotation-normalization-api";

export const quotationNormalizationKeys = {
  all: ["quotation-normalizations"] as const,
  list: (filters: Record<string, unknown>) => [...quotationNormalizationKeys.all, "list", filters] as const,
  detail: (normalizationId: string) =>
    [...quotationNormalizationKeys.all, "detail", normalizationId] as const,
};

export function useQuotationNormalizations(filters: ListQuotationNormalizationsParams = {}) {
  return useQuery({
    queryKey: quotationNormalizationKeys.list(filters),
    queryFn: () => listQuotationNormalizations(filters),
  });
}

export function useQuotationNormalization(normalizationId: string | null | undefined) {
  return useQuery({
    queryKey: quotationNormalizationKeys.detail(normalizationId ?? "no-normalization"),
    queryFn: () => showQuotationNormalization(normalizationId as string),
    enabled: Boolean(normalizationId),
  });
}

function invalidateQuotationNormalization(queryClient: ReturnType<typeof useQueryClient>, normalization: QuotationNormalization) {
  queryClient.invalidateQueries({ queryKey: quotationNormalizationKeys.all });
  queryClient.invalidateQueries({ queryKey: quotationNormalizationKeys.detail(normalization.id) });
}

export function useSaveQuotationNormalizationCorrections(normalizationId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SaveQuotationNormalizationCorrectionsRequest) =>
      saveQuotationNormalizationCorrections(normalizationId, payload),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization),
  });
}

export function useSaveQuotationNormalizationLineMappings(normalizationId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SaveQuotationNormalizationLineMappingsRequest) =>
      saveQuotationNormalizationLineMappings(normalizationId, payload),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization),
  });
}

export function useApproveQuotationNormalization(normalizationId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ApproveQuotationNormalizationRequest = {}) =>
      approveQuotationNormalization(normalizationId, payload),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization),
  });
}

export function useApproveQuotationNormalizationWithWarnings(normalizationId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ApproveQuotationNormalizationWithWarningsRequest) =>
      approveQuotationNormalizationWithWarnings(normalizationId, payload),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization),
  });
}

export function useCreateQuotationNormalizationRevision(normalizationId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => createQuotationNormalizationRevision(normalizationId),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization),
  });
}

export function useRetryQuotationVersionNormalization(version: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => retryQuotationVersionNormalization(version),
    onSuccess: (normalization) => invalidateQuotationNormalization(queryClient, normalization),
  });
}
