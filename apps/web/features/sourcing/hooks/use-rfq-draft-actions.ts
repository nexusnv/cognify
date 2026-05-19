import { useMutation, useQueryClient } from "@tanstack/react-query";
import { cancelRfqDraft, createRfqDraftFromIntake, saveRfqDraft } from "../api/rfq-api";
import type { RfqCancelValues, RfqDraftFormValues } from "../schemas/rfq-draft-schema";
import { rfqDraftKeys } from "./use-rfq-draft";

function invalidateSourcingIntakeReview(queryClient: ReturnType<typeof useQueryClient>, reviewId: string) {
  void queryClient.invalidateQueries({ queryKey: ["sourcing-intake-review", reviewId] });
}

export function useCreateRfqDraftFromIntake(reviewId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => createRfqDraftFromIntake(reviewId),
    onSuccess: (rfq) => {
      queryClient.setQueryData(rfqDraftKeys.detail(rfq.id, rfq.tenantId), rfq);
      invalidateSourcingIntakeReview(queryClient, reviewId);
    },
  });
}

export function useSaveRfqDraft(rfqId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: RfqDraftFormValues) => saveRfqDraft(rfqId, values),
    onSuccess: (rfq) => {
      queryClient.setQueryData(rfqDraftKeys.detail(rfq.id, rfq.tenantId), rfq);
    },
  });
}

export function useCancelRfqDraft(rfqId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: RfqCancelValues) => cancelRfqDraft(rfqId, values),
    onSuccess: (rfq) => {
      queryClient.setQueryData(rfqDraftKeys.detail(rfq.id, rfq.tenantId), rfq);
    },
  });
}
