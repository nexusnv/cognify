import { useQuery } from "@tanstack/react-query";
import { fetchSourcingIntakeReview } from "../api/sourcing-api";

export function useSourcingIntakeReview(reviewId: string) {
  return useQuery({
    queryKey: ["sourcing-intake-review", reviewId],
    queryFn: () => fetchSourcingIntakeReview(reviewId),
    enabled: Boolean(reviewId),
  });
}
