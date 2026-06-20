"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  UploadPaymentImportRequest,
  UpdatePaymentImportRowRequest,
  ReconcilePaymentImportBatchRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  uploadPaymentImportFile,
  showPaymentImportBatchDetail,
  updatePaymentImportRowDetail,
  reconcilePaymentImportBatchDetail,
  discardPaymentImportRowDetail,
} from "../api/accounts-payable-payment-import-api";

export const apPaymentImportKeys = {
  all: ["accounts-payable", "payment-imports"] as const,
  batch: (tenantId: string, batchId: string) =>
    [...apPaymentImportKeys.all, "batch", tenantId, batchId] as const,
};

function useInvalidateImportCaches() {
  const queryClient = useQueryClient();

  return async () => {
    await queryClient.invalidateQueries({ queryKey: apPaymentImportKeys.all });
  };
}

export function useUploadPaymentImport() {
  const invalidate = useInvalidateImportCaches();

  return useMutation({
    mutationFn: (payload: UploadPaymentImportRequest) => {
      const tenantId = getStoredActiveTenantId();
      return uploadPaymentImportFile(payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function usePaymentImportBatch(batchId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: apPaymentImportKeys.batch(tenantId ?? "no-tenant", batchId ?? "missing"),
    queryFn: () => {
      if (!batchId) {
        throw new Error("batchId is required");
      }
      return showPaymentImportBatchDetail(batchId, tenantId);
    },
    enabled: Boolean(tenantId) && Boolean(batchId),
  });
}

export function useUpdatePaymentImportRow(importId: string) {
  const invalidate = useInvalidateImportCaches();

  return useMutation({
    mutationFn: (payload: UpdatePaymentImportRowRequest) => {
      const tenantId = getStoredActiveTenantId();
      return updatePaymentImportRowDetail(importId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useReconcilePaymentImportBatch(batchId: string) {
  const invalidate = useInvalidateImportCaches();

  return useMutation({
    mutationFn: (payload?: ReconcilePaymentImportBatchRequest) => {
      const tenantId = getStoredActiveTenantId();
      return reconcilePaymentImportBatchDetail(batchId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useDiscardPaymentImportRow(importId: string) {
  const invalidate = useInvalidateImportCaches();

  return useMutation({
    mutationFn: () => {
      const tenantId = getStoredActiveTenantId();
      return discardPaymentImportRowDetail(importId, tenantId);
    },
    onSuccess: invalidate,
  });
}
