"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type { SaveQuotationManualEntryRequest } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  saveQuotationManualEntry,
  saveRfqInvitationQuotationManualEntry,
} from "../api/quotation-api";
import { quotationKeys } from "./use-quotation-upload";

export function useSaveQuotationManualEntry(invitationId: string, quotationId: string | null | undefined) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: SaveQuotationManualEntryRequest) => {
      if (!quotationId) {
        return saveRfqInvitationQuotationManualEntry(invitationId, payload, tenantId);
      }

      return saveQuotationManualEntry(quotationId, payload, tenantId);
    },
    onSuccess: (quotation) => {
      queryClient.setQueryData(quotationKeys.byInvitation(invitationId, tenantId), quotation);
      queryClient.setQueryData(quotationKeys.attachments(quotation.id, tenantId), quotation.attachments);
    },
  });
}
