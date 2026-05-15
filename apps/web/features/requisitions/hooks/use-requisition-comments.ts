"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  createRequisitionComment,
  listRequisitionComments,
  listRequisitionMentionCandidates,
} from "../api/requisitions-api";

export function useRequisitionComments(requisitionId: string) {
  return useQuery({
    queryKey: ["requisition", requisitionId, "comments"],
    queryFn: () => listRequisitionComments(requisitionId),
  });
}

export function useCreateRequisitionComment(requisitionId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: { body: string; mentionedUserIds: string[] }) =>
      createRequisitionComment(requisitionId, values),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId, "comments"] });
      void queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId, "activity"] });
    },
  });
}

export function useRequisitionMentionCandidates(requisitionId: string, enabled: boolean) {
  return useQuery({
    queryKey: ["requisition", requisitionId, "mention-candidates"],
    queryFn: () => listRequisitionMentionCandidates(requisitionId),
    enabled,
  });
}
