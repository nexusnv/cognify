"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { fetchVendorPortalQuotation, uploadVendorPortalQuotationAttachment } from "../api/vendor-portal-api";
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
