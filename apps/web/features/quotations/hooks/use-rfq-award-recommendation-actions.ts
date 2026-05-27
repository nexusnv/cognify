"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type {
  CancelPurchaseOrderRequestHandoffRequest,
  MarkPurchaseOrderRequestHandoffReadyRequest,
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
  UpdatePurchaseOrderRequestHandoffRequest,
  WithdrawRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  cancelRfqAwardRecommendationPoHandoff,
  createRfqAwardRecommendationPoHandoffForRfq,
  downloadPurchaseOrderRequestHandoffCsv,
  exportRfqAwardRecommendationPoHandoffJson,
  markRfqAwardRecommendationPoHandoffReady,
  routeRfqAwardRecommendationApproval,
  saveRfqAwardRecommendation,
  submitRfqAwardRecommendation,
  updateRfqAwardRecommendationPoHandoff,
  withdrawRfqAwardRecommendation,
} from "../api/quotation-award-recommendation-api";
import {
  rfqAwardRecommendationApprovalSummaryQueryKey,
  rfqAwardRecommendationPoHandoffQueryKey,
  rfqAwardRecommendationQueryKey,
} from "./use-rfq-award-recommendation";

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

export function useRouteRfqAwardRecommendationApproval(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => routeRfqAwardRecommendationApproval(rfqId, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationQueryKey(rfqId, tenantId) }),
        queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationApprovalSummaryQueryKey(rfqId, tenantId) }),
        queryClient.invalidateQueries({ queryKey: ["approval-tasks"] }),
      ]);
    },
  });
}

function useInvalidatePoHandoff(rfqId: string, tenantId: string | null) {
  const queryClient = useQueryClient();

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationPoHandoffQueryKey(rfqId, tenantId) }),
      queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationQueryKey(rfqId, tenantId) }),
    ]);
  };
}

export function useCreateRfqAwardRecommendationPoHandoff(rfqId: string) {
  const tenantId = getStoredActiveTenantId();
  const invalidate = useInvalidatePoHandoff(rfqId, tenantId);

  return useMutation({
    mutationFn: () => createRfqAwardRecommendationPoHandoffForRfq(rfqId, tenantId),
    onSuccess: invalidate,
  });
}

export function useUpdateRfqAwardRecommendationPoHandoff(rfqId: string, handoffId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const invalidate = useInvalidatePoHandoff(rfqId, tenantId);

  return useMutation({
    mutationFn: (payload: UpdatePurchaseOrderRequestHandoffRequest) => {
      if (!handoffId) throw new Error("Cannot update PO handoff without a handoff id.");

      return updateRfqAwardRecommendationPoHandoff(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useMarkRfqAwardRecommendationPoHandoffReady(rfqId: string, handoffId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const invalidate = useInvalidatePoHandoff(rfqId, tenantId);

  return useMutation({
    mutationFn: (payload: MarkPurchaseOrderRequestHandoffReadyRequest) => {
      if (!handoffId) throw new Error("Cannot mark PO handoff ready without a handoff id.");

      return markRfqAwardRecommendationPoHandoffReady(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useCancelRfqAwardRecommendationPoHandoff(rfqId: string, handoffId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const invalidate = useInvalidatePoHandoff(rfqId, tenantId);

  return useMutation({
    mutationFn: (payload: CancelPurchaseOrderRequestHandoffRequest) => {
      if (!handoffId) throw new Error("Cannot cancel PO handoff without a handoff id.");

      return cancelRfqAwardRecommendationPoHandoff(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useExportRfqAwardRecommendationPoHandoffJson(rfqId: string, handoffId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const invalidate = useInvalidatePoHandoff(rfqId, tenantId);

  return useMutation({
    mutationFn: () => {
      if (!handoffId) throw new Error("Cannot export PO handoff without a handoff id.");

      return exportRfqAwardRecommendationPoHandoffJson(handoffId, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useDownloadRfqAwardRecommendationPoHandoffCsv(rfqId: string, handoffId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const invalidate = useInvalidatePoHandoff(rfqId, tenantId);

  return useMutation({
    mutationFn: () => {
      if (!handoffId) throw new Error("Cannot export PO handoff without a handoff id.");

      return downloadPurchaseOrderRequestHandoffCsv(handoffId, tenantId);
    },
    onSuccess: invalidate,
  });
}
