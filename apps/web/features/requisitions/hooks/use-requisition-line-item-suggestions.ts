"use client";

import { useQuery } from "@tanstack/react-query";
import { listRequisitionLineItemSuggestions } from "../api/requisitions-api";

export function useRequisitionLineItemSuggestions(search: string, currency: string) {
  const normalizedSearch = search.trim();

  return useQuery({
    queryKey: ["requisition-line-item-suggestions", normalizedSearch, currency],
    queryFn: () => listRequisitionLineItemSuggestions({ search: normalizedSearch, currency }),
    enabled: normalizedSearch.length >= 2,
  });
}
