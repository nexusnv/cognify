"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  CreateQuotationRevisionRequest,
  SaveQuotationManualEntryRequestForVendor,
} from "@cognify/api-client/schemas";
import {
  createVendorPortalQuotationVersion,
  fetchVendorPortalQuotation,
  listVendorPortalQuotationVersions,
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
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: vendorPortalKeys.quotation(token) });
      void queryClient.refetchQueries({ queryKey: vendorPortalKeys.quotationVersions(token) });
    },
  });
}

export function useVendorQuotationManualEntry(token: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SaveQuotationManualEntryRequestForVendor) =>
      saveVendorPortalQuotationManualEntry(token, payload),
    onSuccess: (quotation) => {
      queryClient.setQueryData(vendorPortalKeys.quotation(token), quotation);
      void queryClient.refetchQueries({ queryKey: vendorPortalKeys.quotationVersions(token) });
    },
  });
}

export function useVendorQuotationVersions(token: string) {
  return useQuery({
    queryKey: vendorPortalKeys.quotationVersions(token),
    queryFn: () => listVendorPortalQuotationVersions(token),
    enabled: token.length > 0,
    retry: false,
  });
}

export function useCreateVendorQuotationVersion(token: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateQuotationRevisionRequest) => createVendorPortalQuotationVersion(token, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: vendorPortalKeys.quotation(token) });
      void queryClient.refetchQueries({ queryKey: vendorPortalKeys.quotationVersions(token) });
    },
  });
}
