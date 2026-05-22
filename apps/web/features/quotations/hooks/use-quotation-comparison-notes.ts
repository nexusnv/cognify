"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type { SaveQuotationComparisonNoteRequest } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  createQuotationComparisonNote,
  deleteQuotationComparisonNote,
  updateQuotationComparisonNote,
} from "../api/quotation-comparison-api";
import { quotationComparisonKeys } from "./use-quotation-comparison";

function invalidateQuotationComparison(
  queryClient: ReturnType<typeof useQueryClient>,
  rfqId: string,
  tenantId: string | null,
) {
  queryClient.invalidateQueries({ queryKey: quotationComparisonKeys.detail(rfqId, tenantId) });
}

export function useCreateQuotationComparisonNote(rfqId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: SaveQuotationComparisonNoteRequest) =>
      createQuotationComparisonNote(rfqId, payload, tenantId),
    onSuccess: () => invalidateQuotationComparison(queryClient, rfqId, tenantId),
  });
}

export function useUpdateQuotationComparisonNote(rfqId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: ({
      noteId,
      payload,
    }: {
      noteId: string;
      payload: SaveQuotationComparisonNoteRequest;
    }) => updateQuotationComparisonNote(rfqId, noteId, payload, tenantId),
    onSuccess: () => invalidateQuotationComparison(queryClient, rfqId, tenantId),
  });
}

export function useDeleteQuotationComparisonNote(rfqId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (noteId: string) => deleteQuotationComparisonNote(rfqId, noteId, tenantId),
    onSuccess: () => invalidateQuotationComparison(queryClient, rfqId, tenantId),
  });
}
