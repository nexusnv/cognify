"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import {
  approveApprovalTask,
  markApprovalTaskViewed,
  rejectApprovalTask,
  requestApprovalTaskChanges,
} from "../api/approvals-api";
import { approvalTaskKeys } from "./use-approval-tasks";

export function useApprovalTaskActions(taskId: string) {
  const queryClient = useQueryClient();
  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: approvalTaskKeys.all });
    await queryClient.invalidateQueries({ queryKey: ["requisition"] });
  };

  return {
    view: useMutation({
      mutationFn: () => markApprovalTaskViewed(taskId),
      onSuccess: invalidate,
    }),
    approve: useMutation({
      mutationFn: (values: { lockVersion: number }) => approveApprovalTask(taskId, values),
      onSuccess: invalidate,
    }),
    reject: useMutation({
      mutationFn: (values: { lockVersion: number; reason: string }) =>
        rejectApprovalTask(taskId, values),
      onSuccess: invalidate,
    }),
    requestChanges: useMutation({
      mutationFn: (values: { lockVersion: number; reason: string; requestedFields?: string[] }) =>
        requestApprovalTaskChanges(taskId, values),
      onSuccess: invalidate,
    }),
  };
}
