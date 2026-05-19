import { useQuery } from "@tanstack/react-query";
import { fetchSourcingIntakeReviews } from "../api/sourcing-api";
import type { SourcingIntakeQuery } from "../types/sourcing-view-model";

export function useSourcingIntakeReviews(query: SourcingIntakeQuery = {}) {
  return useQuery({
    queryKey: ["sourcing-intake-reviews", query],
    queryFn: () => fetchSourcingIntakeReviews(query),
  });
}
