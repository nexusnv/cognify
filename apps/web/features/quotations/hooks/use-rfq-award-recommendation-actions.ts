"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
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
  createPurchaseOrderFromPoHandoff,
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

function queryTenantIdOrFallback() {
  return getStoredActiveTenantId() ?? "no-tenant";
}

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
  const queryTenantId = tenantId ?? "no-tenant";

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationPoHandoffQueryKey(rfqId, queryTenantId) }),
      queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationQueryKey(rfqId, queryTenantId) }),
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

export function useCreatePurchaseOrderFromRfqAwardHandoff(rfqId: string, handoffId: string | null | undefined) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = queryTenantIdOrFallback();

  return useMutation({
    mutationFn: () => {
      if (!handoffId) throw new Error("Cannot create a purchase order without a handoff id.");

      return createPurchaseOrderFromPoHandoff(handoffId, tenantId);
    },
    onSuccess: async (purchaseOrder) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationPoHandoffQueryKey(rfqId, queryTenantId) }),
        queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationQueryKey(rfqId, queryTenantId) }),
      ]);
      router.push(`/purchase-orders/${purchaseOrder.id}`);
    },
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
