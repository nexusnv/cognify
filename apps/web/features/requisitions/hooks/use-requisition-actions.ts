"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import {
  cancelRequisition,
  requestRequisitionChanges,
  resubmitRequisition,
  withdrawRequisition,
} from "../api/requisitions-api";

function invalidateWorkspace(queryClient: ReturnType<typeof useQueryClient>, requisitionId: string) {
  void queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId] });
  void queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId, "activity"] });
  void queryClient.invalidateQueries({ queryKey: ["requisitions"] });
}

export function useRequestRequisitionChanges(requisitionId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: { reason: string; requestedFields: string[] }) =>
      requestRequisitionChanges(requisitionId, values),
    onSuccess: () => invalidateWorkspace(queryClient, requisitionId),
  });
}

export function useResubmitRequisition(requisitionId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => resubmitRequisition(requisitionId),
    onSuccess: () => invalidateWorkspace(queryClient, requisitionId),
  });
}

export function useWithdrawRequisition(requisitionId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: { reason: string }) => withdrawRequisition(requisitionId, values),
    onSuccess: () => invalidateWorkspace(queryClient, requisitionId),
  });
}

export function useCancelRequisition(requisitionId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: { reason: string }) => cancelRequisition(requisitionId, values),
    onSuccess: () => invalidateWorkspace(queryClient, requisitionId),
  });
}
