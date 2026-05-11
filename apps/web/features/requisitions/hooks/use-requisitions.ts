"use client";

import { useQuery } from "@tanstack/react-query";
import { listRequisitions } from "../api/requisitions-api";

export function useRequisitions(query: { search?: string; status?: string } = {}) {
  return useQuery({
    queryKey: ["requisitions", query],
    queryFn: () => listRequisitions(query),
  });
}
