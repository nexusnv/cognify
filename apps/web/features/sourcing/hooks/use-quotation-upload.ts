import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  fetchQuotationAttachments,
  fetchRfqInvitationQuotation,
  uploadRfqInvitationQuotationAttachment,
} from "../api/quotation-api";

export const quotationKeys = {
  byInvitation: (invitationId: string, tenantId: string | null = getStoredActiveTenantId()) =>
    ["sourcing", "quotations", tenantId ?? "no-tenant", invitationId] as const,
  attachments: (quotationId: string, tenantId: string | null = getStoredActiveTenantId()) =>
    ["sourcing", "quotation-attachments", tenantId ?? "no-tenant", quotationId] as const,
};

export function useRfqInvitationQuotation(invitationId: string) {
  return useQuery({
    queryKey: quotationKeys.byInvitation(invitationId),
    queryFn: () => fetchRfqInvitationQuotation(invitationId),
    enabled: Boolean(invitationId),
  });
}

export function useQuotationAttachments(quotationId: string | null | undefined) {
  return useQuery({
    queryKey: quotationKeys.attachments(quotationId ?? "no-quotation"),
    queryFn: () => {
      if (!quotationId) return Promise.resolve([]);
      return fetchQuotationAttachments(quotationId);
    },
    enabled: Boolean(quotationId),
  });
}

export function useRfqInvitationQuotationUpload(invitationId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (file: File) => uploadRfqInvitationQuotationAttachment(invitationId, file),
    onSuccess: (quotation) => {
      const tenantId = getStoredActiveTenantId();

      queryClient.invalidateQueries({ queryKey: quotationKeys.byInvitation(invitationId, tenantId) });
      queryClient.invalidateQueries({ queryKey: quotationKeys.attachments(quotation.id, tenantId) });
    },
  });
}
