"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type {
  CancelPurchaseOrderRequest,
  MarkPurchaseOrderReadyForReviewRequest,
  UpdatePurchaseOrderRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  cancelDraftPurchaseOrder,
  readyPurchaseOrder,
  savePurchaseOrder,
} from "../api/purchase-order-api";
import { purchaseOrderKeys } from "./use-purchase-order";

function queryTenantIdOrFallback() {
  return getStoredActiveTenantId() ?? "no-tenant";
}

export function useUpdatePurchaseOrder(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = queryTenantIdOrFallback();

  return useMutation({
    mutationFn: (payload: UpdatePurchaseOrderRequest) =>
      savePurchaseOrder(purchaseOrderId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.list(queryTenantId) }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderKeys.detail(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}

export function useMarkPurchaseOrderReadyForReview(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = queryTenantIdOrFallback();

  return useMutation({
    mutationFn: (payload: MarkPurchaseOrderReadyForReviewRequest) =>
      readyPurchaseOrder(purchaseOrderId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.list(queryTenantId) }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderKeys.detail(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}

export function useCancelPurchaseOrder(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = queryTenantIdOrFallback();

  return useMutation({
    mutationFn: (payload: CancelPurchaseOrderRequest) =>
      cancelDraftPurchaseOrder(purchaseOrderId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.list(queryTenantId) }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderKeys.detail(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}
