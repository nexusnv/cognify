"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { SaveQuotationManualEntryRequestForVendor } from "@cognify/api-client/schemas";
import {
  fetchVendorPortalQuotation,
  saveVendorPortalQuotationManualEntry,
  uploadVendorPortalQuotationAttachment,
} from "../api/vendor-portal-api";
import { vendorPortalKeys } from "./use-vendor-rfq-invitation";

export function useVendorQuotation(token: string) {
  return useQuery({
    queryKey: vendorPortalKeys.quotation(token),
    queryFn: () => fetchVendorPortalQuotation(token),
    enabled: token.length > 0,
    retry: false,
  });
}

export function useVendorQuotationUpload(token: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (file: File) => uploadVendorPortalQuotationAttachment(token, file),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: vendorPortalKeys.quotation(token) }),
  });
}

export function useVendorQuotationManualEntry(token: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SaveQuotationManualEntryRequestForVendor) =>
      saveVendorPortalQuotationManualEntry(token, payload),
    onSuccess: (quotation) => {
      queryClient.setQueryData(vendorPortalKeys.quotation(token), quotation);
    },
  });
}
