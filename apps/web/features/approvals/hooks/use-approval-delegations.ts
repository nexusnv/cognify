"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { DelegateApprovalTaskRequest, StoreApprovalDelegationRequest } from "@cognify/api-client/schemas";
import {
  createTaskDelegation,
  delegateApprovalTask,
  listApprovalDelegations,
} from "../api/approvals-api";

export function useApprovalDelegations() {
  return useQuery({
    queryKey: ["approval-delegations"],
    queryFn: listApprovalDelegations,
  });
}

export function useCreateApprovalDelegation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: StoreApprovalDelegationRequest) => createTaskDelegation(values),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["approval-delegations"] });
    },
  });
}

export function useDelegateApprovalTask(taskId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: DelegateApprovalTaskRequest) => delegateApprovalTask(taskId, values),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["approval-task", taskId] });
      void queryClient.invalidateQueries({ queryKey: ["approval-tasks"] });
    },
  });
}
