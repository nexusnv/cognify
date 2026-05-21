"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type {
  ApproveQuotationNormalizationRequest,
  ApproveQuotationNormalizationWithWarningsRequest,
  QuotationNormalization,
  SaveQuotationNormalizationCorrectionsRequest,
  SaveQuotationNormalizationLineMappingsRequest,
} from "@cognify/api-client/schemas";
import {
  approveQuotationNormalization,
  approveQuotationNormalizationWithWarnings,
  createQuotationNormalizationRevision,
  retryQuotationVersionNormalization,
  saveQuotationNormalizationCorrections,
  saveQuotationNormalizationLineMappings,
} from "../api/quotation-normalization-api";
import { quotationNormalizationKeys } from "./use-quotation-normalization-queue";

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
