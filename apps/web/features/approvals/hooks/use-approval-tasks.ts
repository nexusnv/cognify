"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchApprovalTask, listApprovalTasks } from "../api/approvals-api";
import type { ApprovalTaskFilters } from "../types/approval-view-model";

export const approvalTaskKeys = {
  all: ["approval-tasks"] as const,
  list: (filters: ApprovalTaskFilters = {}) => [...approvalTaskKeys.all, "list", filters] as const,
  detail: (taskId: string) => [...approvalTaskKeys.all, "detail", taskId] as const,
};

export function useApprovalTasks(filters: ApprovalTaskFilters = {}) {
  return useQuery({
    queryKey: approvalTaskKeys.list(filters),
    queryFn: () => listApprovalTasks(filters),
  });
}

export function useApprovalTask(taskId: string) {
  return useQuery({
    queryKey: approvalTaskKeys.detail(taskId),
    queryFn: () => fetchApprovalTask(taskId),
  });
}
