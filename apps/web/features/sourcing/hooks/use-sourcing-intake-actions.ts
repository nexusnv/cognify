import { useMutation, useQueryClient } from "@tanstack/react-query";
import {
  claimIntakeReview,
  decideIntakeReview,
  reassignIntakeReview,
  saveIntakeReview,
} from "../api/sourcing-api";

function invalidateReview(queryClient: ReturnType<typeof useQueryClient>, reviewId: string) {
  void queryClient.invalidateQueries({ queryKey: ["sourcing-intake-review", reviewId] });
  void queryClient.invalidateQueries({ queryKey: ["sourcing-intake-reviews"] });
}

export function useClaimSourcingIntakeReview(reviewId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => claimIntakeReview(reviewId),
    onSuccess: () => invalidateReview(queryClient, reviewId),
  });
}

export function useSaveSourcingIntakeReview(reviewId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (values: Parameters<typeof saveIntakeReview>[1]) => saveIntakeReview(reviewId, values),
    onSuccess: () => invalidateReview(queryClient, reviewId),
  });
}

export function useDecideSourcingIntakeReview(reviewId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (values: Parameters<typeof decideIntakeReview>[1]) =>
      decideIntakeReview(reviewId, values),
    onSuccess: () => invalidateReview(queryClient, reviewId),
  });
}

export function useReassignSourcingIntakeReview(reviewId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (buyerId: string) => reassignIntakeReview(reviewId, buyerId),
    onSuccess: () => invalidateReview(queryClient, reviewId),
  });
}
