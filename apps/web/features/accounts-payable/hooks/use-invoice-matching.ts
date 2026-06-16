"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  triggerInvoiceMatching,
  fetchInvoiceMatchResults,
  buildMatchSummary,
  MatchResult,
} from "../api/accounts-payable-invoices-api";

export function useInvoiceMatchResults(invoiceId: string | null) {
  return useQuery<MatchResult[]>({
    queryKey: ["supplier-invoice", "match-results", invoiceId],
    queryFn: () => fetchInvoiceMatchResults(invoiceId!),
    enabled: !!invoiceId,
  });
}

export function useRunInvoiceMatching(invoiceId: string | null) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ lockVersion }: { lockVersion: number }) =>
      triggerInvoiceMatching(invoiceId!, lockVersion),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["supplier-invoice", "match-results", invoiceId],
      });
      await queryClient.invalidateQueries({
        queryKey: ["supplier-invoice", "detail", invoiceId],
      });
      await queryClient.invalidateQueries({
        queryKey: ["supplier-invoice-queue"],
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
