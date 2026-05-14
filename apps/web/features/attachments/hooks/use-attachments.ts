import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { ApiClientError } from "@cognify/api-client";
import type { Attachment, ValidationFailedResponse } from "@cognify/api-client/schemas";
import {
  deleteAttachment,
  listAttachments,
  uploadAttachment,
} from "../api/attachments-api";

export function useAttachments(requisitionId: string) {
  return useQuery({
    queryKey: ["attachments", "requisition", requisitionId],
    queryFn: () => listAttachments(requisitionId),
  });
}

export function useAttachmentUpload(requisitionId: string) {
  const queryClient = useQueryClient();
  return useMutation<Attachment, ApiClientError<ValidationFailedResponse>, File>({
    mutationFn: (file: File) => uploadAttachment(requisitionId, file),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["attachments", "requisition", requisitionId] }),
  });
}

export function useAttachmentDelete(requisitionId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (attachmentId: string) => deleteAttachment(attachmentId),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["attachments", "requisition", requisitionId] }),
  });
}
