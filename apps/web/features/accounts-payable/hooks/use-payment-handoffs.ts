"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  ApPaymentHandoff,
  CancelApPaymentHandoffRequest,
  CreateApPaymentHandoffRequest,
  ListApPaymentHandoffsParams,
  MarkApPaymentHandoffReadyRequest,
  RefreshApPaymentHandoffSnapshotRequest,
  UpdateApPaymentHandoffRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  cancelPaymentHandoff,
  createPaymentHandoff,
  exportPaymentHandoffCsv,
  exportPaymentHandoffJson,
  listPaymentHandoffs,
  markPaymentHandoffReady,
  recordPaymentHandoffCsvExport,
  recordPaymentHandoffJsonExport,
  refreshPaymentHandoffSnapshot,
  showPaymentHandoff,
  updatePaymentHandoff,
  type ApPaymentHandoffJsonExport,
} from "../api/accounts-payable-handoff-api";
import { accountsPayableInvoiceKeys } from "./use-accounts-payable-invoices";

/**
 * Query keys for the AP payment handoff cache. Held separately from the invoice
 * keys because handoffs are an independent resource, but mutations that move
 * invoices between handoff/payment states must invalidate both caches.
 */
export const apPaymentHandoffKeys = {
  all: ["accounts-payable", "payment-handoffs"] as const,
  list: (tenantId: string, params?: ListApPaymentHandoffsParams) =>
    [...apPaymentHandoffKeys.all, "list", tenantId, params ?? {}] as const,
  detail: (tenantId: string, handoffId: string) =>
    [...apPaymentHandoffKeys.all, "detail", tenantId, handoffId] as const,
};

/** Invalidate every handoff + invoice query — used after handoff mutations. */
function useInvalidatePaymentCaches() {
  const queryClient = useQueryClient();

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: apPaymentHandoffKeys.all }),
      queryClient.invalidateQueries({ queryKey: accountsPayableInvoiceKeys.all }),
    ]);
  };
}

export function useApPaymentHandoffs(params?: ListApPaymentHandoffsParams) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: apPaymentHandoffKeys.list(tenantId ?? "no-tenant", params),
    queryFn: () => listPaymentHandoffs(params, tenantId),
    enabled: Boolean(tenantId),
  });
}

export function useApPaymentHandoff(handoffId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: apPaymentHandoffKeys.detail(tenantId ?? "no-tenant", handoffId ?? "missing"),
    queryFn: () => showPaymentHandoff(handoffId as string, tenantId),
    enabled: Boolean(tenantId) && Boolean(handoffId),
  });
}

export function useCreateApPaymentHandoff() {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: CreateApPaymentHandoffRequest) => {
      const tenantId = getStoredActiveTenantId();
      return createPaymentHandoff(payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useUpdateApPaymentHandoff(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: UpdateApPaymentHandoffRequest) => {
      const tenantId = getStoredActiveTenantId();
      return updatePaymentHandoff(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useRefreshApPaymentHandoffSnapshot(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload?: RefreshApPaymentHandoffSnapshotRequest) => {
      const tenantId = getStoredActiveTenantId();
      return refreshPaymentHandoffSnapshot(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useMarkApPaymentHandoffReady(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: MarkApPaymentHandoffReadyRequest) => {
      const tenantId = getStoredActiveTenantId();
      return markPaymentHandoffReady(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useCancelApPaymentHandoff(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: CancelApPaymentHandoffRequest) => {
      const tenantId = getStoredActiveTenantId();
      return cancelPaymentHandoff(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

/**
 * Export a handoff as JSON and record the export against it. Returns the export
 * payload for download/display and invalidates the cache so the handoff reflects
 * its new `exported` status.
 */
export function useExportApPaymentHandoffJson(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation<ApPaymentHandoffJsonExport, unknown, void>({
    mutationFn: async () => {
      const tenantId = getStoredActiveTenantId();
      const payload = await exportPaymentHandoffJson(handoffId, tenantId);
      await recordPaymentHandoffJsonExport(handoffId, tenantId);
      return payload;
    },
    onSuccess: invalidate,
  });
}

/**
 * Export a handoff as CSV and record the export against it. Returns the raw CSV
 * text (BOM + header + rows) for download.
 */
export function useExportApPaymentHandoffCsv(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation<string, unknown, void>({
    mutationFn: async () => {
      const tenantId = getStoredActiveTenantId();
      const csv = await exportPaymentHandoffCsv(handoffId, tenantId);
      await recordPaymentHandoffCsvExport(handoffId, tenantId);
      return csv;
    },
    onSuccess: invalidate,
  });
}

export type { ApPaymentHandoff };
