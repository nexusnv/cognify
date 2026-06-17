"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  triggerInvoiceMatching,
  fetchInvoiceMatchResults,
  buildMatchSummary,
} from "../api/accounts-payable-invoices-api";
import { accountsPayableInvoiceKeys } from "./use-accounts-payable-invoices";

export function useInvoiceMatchResults(invoiceId: string | null) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: accountsPayableInvoiceKeys.matchResults(tenantId ?? "no-tenant", invoiceId ?? "no-invoice"),
    queryFn: () => fetchInvoiceMatchResults(invoiceId!, tenantId),
    enabled: Boolean(tenantId) && !!invoiceId,
  });
}

export function useRunInvoiceMatching(invoiceId: string | null) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ lockVersion }: { lockVersion: number }) => {
      if (invoiceId === null) {
        throw new Error("Cannot run matching: no invoice selected");
      }
      return triggerInvoiceMatching(invoiceId, lockVersion);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: accountsPayableInvoiceKeys.all,
      });
    },
  });
}

export function useInvoiceMatchSummary(invoiceId: string | null) {
  const { data: results, isLoading, isError } = useInvoiceMatchResults(invoiceId);

  return {
    summary: results ? buildMatchSummary(results) : null,
    results,
    isLoading,
    isError,
  };
}
