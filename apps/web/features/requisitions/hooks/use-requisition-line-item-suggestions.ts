"use client";

import { useQuery } from "@tanstack/react-query";
import { listRequisitionLineItemSuggestions } from "../api/requisitions-api";

export function useRequisitionLineItemSuggestions(search: string, currency: string) {
  return useQuery({
    queryKey: ["requisition-line-item-suggestions", search, currency],
    queryFn: () => listRequisitionLineItemSuggestions({ search, currency }),
    enabled: search.trim().length >= 2,
  });
}
