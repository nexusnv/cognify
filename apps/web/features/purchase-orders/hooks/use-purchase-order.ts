"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  fetchPurchaseOrder,
  fetchPurchaseOrderChangeOrder,
  fetchPurchaseOrderChangeOrders,
  fetchPurchaseOrders,
} from "../api/purchase-order-api";

export const purchaseOrderKeys = {
  all: ["purchase-orders"] as const,
  list: (tenantId: string) => [...purchaseOrderKeys.all, "list", tenantId] as const,
  detail: (tenantId: string, purchaseOrderId: string) =>
    [...purchaseOrderKeys.all, "detail", tenantId, purchaseOrderId] as const,
  changeOrders: (tenantId: string, purchaseOrderId: string) =>
    [...purchaseOrderKeys.all, "change-orders", tenantId, purchaseOrderId] as const,
};

export function usePurchaseOrders() {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useQuery({
    queryKey: purchaseOrderKeys.list(queryTenantId),
    queryFn: () => fetchPurchaseOrders(tenantId),
    enabled: Boolean(tenantId),
  });
}

export function usePurchaseOrder(purchaseOrderId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryPurchaseOrderId = purchaseOrderId || "no-purchase-order";

  return useQuery({
    queryKey: purchaseOrderKeys.detail(queryTenantId, queryPurchaseOrderId),
    queryFn: () => fetchPurchaseOrder(purchaseOrderId, tenantId),
    enabled: Boolean(tenantId && purchaseOrderId),
  });
}

export function usePurchaseOrderChangeOrders(purchaseOrderId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryPurchaseOrderId = purchaseOrderId || "no-purchase-order";

  return useQuery({
    queryKey: purchaseOrderKeys.changeOrders(queryTenantId, queryPurchaseOrderId),
    queryFn: () => fetchPurchaseOrderChangeOrders(purchaseOrderId, tenantId),
    enabled: Boolean(tenantId && purchaseOrderId),
  });
}

export function usePurchaseOrderChangeOrder(changeOrderId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryChangeOrderId = changeOrderId || "no-change-order";

  return useQuery({
    queryKey: [...purchaseOrderKeys.all, "change-order", queryTenantId, queryChangeOrderId] as const,
    queryFn: () => fetchPurchaseOrderChangeOrder(changeOrderId, tenantId),
    enabled: Boolean(tenantId && changeOrderId),
  });
}
