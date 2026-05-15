"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { getSearchErrorMessage, searchRecords } from "../api/search-api";
import type { SearchResponse, SearchResultViewModel } from "../types/search-view-model";
import { GLOBAL_SEARCH_TYPES } from "../search-contract";

const SEARCH_DEBOUNCE_MS = 250;
const SEARCH_LIMIT = 10;

export function useGlobalSearch(query: string, tenantId: string | null) {
  const debouncedQuery = useDebouncedValue(query.trim(), SEARCH_DEBOUNCE_MS);
  const queryEnabled = debouncedQuery.length >= 2 && tenantId !== null;

  const searchQuery = useQuery({
    queryKey: ["search", tenantId, debouncedQuery],
    queryFn: async () => {
      return searchRecords(
        {
          query: debouncedQuery,
          types: GLOBAL_SEARCH_TYPES,
          limit: SEARCH_LIMIT,
        },
        tenantId,
      );
    },
    enabled: queryEnabled,
    retry: false,
    staleTime: 30_000,
  });

  return {
    query: debouncedQuery,
    results: (searchQuery.data?.data ?? []) as SearchResultViewModel[],
    meta: (searchQuery.data?.meta ?? null) as SearchResponse["meta"] | null,
    isLoading: searchQuery.isFetching && queryEnabled,
    isError: searchQuery.isError,
    errorMessage: searchQuery.error ? getSearchErrorMessage(searchQuery.error) : null,
  };
}

function useDebouncedValue<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => window.clearTimeout(timer);
  }, [delay, value]);

  return debouncedValue;
}
