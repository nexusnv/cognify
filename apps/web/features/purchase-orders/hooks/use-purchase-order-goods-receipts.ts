"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  ConfirmGoodsReceiptRequest,
  RecordGoodsReceiptRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  confirmGoodsReceiptBuyer,
  confirmGoodsReceiptRequester,
  fetchGoodsReceipts,
  recordGoodsReceipt,
} from "../api/purchase-order-goods-receipt-api";

export const goodsReceiptKeys = {
  all: ["purchase-orders", "goods-receipts"] as const,
  list: (tenantId: string, purchaseOrderId: string) =>
    [...goodsReceiptKeys.all, "list", tenantId, purchaseOrderId] as const,
};

export function useGoodsReceipts(purchaseOrderId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryPurchaseOrderId = purchaseOrderId || "no-purchase-order";

  return useQuery({
    queryKey: goodsReceiptKeys.list(queryTenantId, queryPurchaseOrderId),
    queryFn: () => fetchGoodsReceipts(purchaseOrderId, tenantId),
    enabled: Boolean(tenantId && purchaseOrderId),
  });
}

export function useRecordGoodsReceipt(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: (payload: RecordGoodsReceiptRequest) =>
      recordGoodsReceipt(purchaseOrderId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: goodsReceiptKeys.list(queryTenantId, purchaseOrderId),
      });
    },
  });
}

export function useConfirmGoodsReceiptRequester(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: ({ goodsReceiptId, payload }: { goodsReceiptId: string; payload: ConfirmGoodsReceiptRequest }) =>
      confirmGoodsReceiptRequester(goodsReceiptId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: goodsReceiptKeys.list(queryTenantId, purchaseOrderId),
      });
    },
  });
}

export function useConfirmGoodsReceiptBuyer(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: ({ goodsReceiptId, payload }: { goodsReceiptId: string; payload: ConfirmGoodsReceiptRequest }) =>
      confirmGoodsReceiptBuyer(goodsReceiptId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: goodsReceiptKeys.list(queryTenantId, purchaseOrderId),
      });
    },
  });
}
