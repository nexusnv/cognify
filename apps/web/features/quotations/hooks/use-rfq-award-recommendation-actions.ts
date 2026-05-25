"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type {
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
  WithdrawRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  saveRfqAwardRecommendation,
  submitRfqAwardRecommendation,
  withdrawRfqAwardRecommendation,
} from "../api/quotation-award-recommendation-api";
import { rfqAwardRecommendationQueryKey } from "./use-rfq-award-recommendation";

export function useSaveRfqAwardRecommendation(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SaveRfqAwardRecommendationRequest) => saveRfqAwardRecommendation(rfqId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationQueryKey(rfqId, tenantId) });
    },
  });
}

export function useSubmitRfqAwardRecommendation(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload?: SubmitRfqAwardRecommendationRequest) => submitRfqAwardRecommendation(rfqId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationQueryKey(rfqId, tenantId) });
    },
  });
}

export function useWithdrawRfqAwardRecommendation(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: WithdrawRfqAwardRecommendationRequest) =>
      withdrawRfqAwardRecommendation(rfqId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationQueryKey(rfqId, tenantId) });
    },
  });
}
