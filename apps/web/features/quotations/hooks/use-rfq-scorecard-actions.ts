"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  completeRfqScorecard,
  createRfqScorecard,
  reopenRfqScorecard,
  updateRfqScorecardScores,
  UpdateScoreEntryInput,
} from "../api/quotation-scoring-api";
import { quotationScoringKeys } from "./use-quotation-scoring-templates";

export function useCreateRfqScorecard(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (templateId: string) => createRfqScorecard(rfqId, templateId, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: quotationScoringKeys.scorecard(tenantId, rfqId) });
    },
  });
}

export function useUpdateRfqScorecardScores(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (entries: UpdateScoreEntryInput[]) => updateRfqScorecardScores(rfqId, entries, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: quotationScoringKeys.scorecard(tenantId, rfqId) });
    },
  });
}

export function useCompleteRfqScorecard(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => completeRfqScorecard(rfqId, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: quotationScoringKeys.scorecard(tenantId, rfqId) });
    },
  });
}

export function useReopenRfqScorecard(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => reopenRfqScorecard(rfqId, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: quotationScoringKeys.scorecard(tenantId, rfqId) });
    },
  });
}
