"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { createRequisitionDraft, updateRequisitionDraft } from "../api/requisitions-api";
import type { RequisitionFormValues } from "../types/requisition-view-model";

export function useSaveRequisitionDraft() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      requisitionId,
      values,
      lockVersion,
    }: {
      requisitionId?: string;
      values: RequisitionFormValues;
      lockVersion?: number;
    }) =>
      requisitionId
        ? updateRequisitionDraft(requisitionId, values, lockVersion ?? 0)
        : createRequisitionDraft(values),
    onSuccess: async (requisition) => {
      await queryClient.invalidateQueries({ queryKey: ["requisitions"] });
      queryClient.setQueryData(["requisition", requisition.id], requisition);
    },
  });
}
