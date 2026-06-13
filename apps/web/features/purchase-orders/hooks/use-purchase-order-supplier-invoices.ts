"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { purchaseOrderKeys } from "./use-purchase-order";
import {
  createPurchaseOrderSupplierInvoice,
  fetchPurchaseOrderSupplierInvoices,
  fetchSupplierInvoiceAttachments,
  uploadSupplierInvoiceAttachment,
} from "../api/purchase-order-supplier-invoice-api";
import type { CaptureSupplierInvoiceRequest } from "@cognify/api-client/schemas";

export const supplierInvoiceKeys = {
  all: ["purchase-orders", "supplier-invoices"] as const,
  list: (tenantId: string, purchaseOrderId: string) =>
    [...supplierInvoiceKeys.all, "list", tenantId, purchaseOrderId] as const,
  attachments: (tenantId: string, supplierInvoiceId: string) =>
    [...supplierInvoiceKeys.all, "attachments", tenantId, supplierInvoiceId] as const,
};

export function usePurchaseOrderSupplierInvoices(purchaseOrderId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryPurchaseOrderId = purchaseOrderId || "no-purchase-order";

  return useQuery({
    queryKey: supplierInvoiceKeys.list(queryTenantId, queryPurchaseOrderId),
    queryFn: () => fetchPurchaseOrderSupplierInvoices(purchaseOrderId, tenantId),
    enabled: Boolean(tenantId && purchaseOrderId),
  });
}

export function useCreatePurchaseOrderSupplierInvoice(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: (payload: CaptureSupplierInvoiceRequest) =>
      createPurchaseOrderSupplierInvoice(purchaseOrderId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: supplierInvoiceKeys.list(queryTenantId, purchaseOrderId),
        }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderKeys.detail(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}

export function useSupplierInvoiceAttachments(supplierInvoiceId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const querySupplierInvoiceId = supplierInvoiceId || "no-supplier-invoice";

  return useQuery({
    queryKey: supplierInvoiceKeys.attachments(queryTenantId, querySupplierInvoiceId),
    queryFn: () => fetchSupplierInvoiceAttachments(supplierInvoiceId, tenantId),
    enabled: Boolean(tenantId && supplierInvoiceId),
  });
}

export function useUploadSupplierInvoiceAttachment(supplierInvoiceId: string, purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: (file: File) => uploadSupplierInvoiceAttachment(supplierInvoiceId, file, tenantId),
    onSuccess: async () => {
      void purchaseOrderId;
      await queryClient.invalidateQueries({
        queryKey: supplierInvoiceKeys.attachments(queryTenantId, supplierInvoiceId),
      });
    },
  });
}
