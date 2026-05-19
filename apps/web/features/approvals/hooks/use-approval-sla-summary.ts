"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchApprovalSlaSummary } from "../api/approvals-api";

export function useApprovalSlaSummary() {
  return useQuery({
    queryKey: ["approval-sla-summary"],
    queryFn: fetchApprovalSlaSummary,
  });
}
