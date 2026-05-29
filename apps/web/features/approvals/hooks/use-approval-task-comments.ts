"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { CreateCollaborationCommentRequest } from "@cognify/api-client/schemas";
import {
  createApprovalTaskComment,
  listApprovalTaskComments,
} from "../api/approvals-api";

export function useApprovalTaskComments(taskId: string) {
  return useQuery({
    queryKey: ["approval-task", taskId, "comments"],
    queryFn: () => listApprovalTaskComments(taskId),
  });
}

export function useCreateApprovalTaskComment(taskId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: CreateCollaborationCommentRequest) =>
      createApprovalTaskComment(taskId, values),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["approval-task", taskId, "comments"] });
    },
  });
}
