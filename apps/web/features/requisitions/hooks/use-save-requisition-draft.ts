"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { createRequisitionDraft, updateRequisitionDraft } from "../api/requisitions-api";
import type { RequisitionFormValues } from "../types/requisition-view-model";

export function useSaveRequisitionDraft() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ requisitionId, values }: { requisitionId?: string; values: RequisitionFormValues }) =>
      requisitionId ? updateRequisitionDraft(requisitionId, values) : createRequisitionDraft(values),
    onSuccess: async (requisition) => {
      await queryClient.invalidateQueries({ queryKey: ["requisitions"] });
      queryClient.setQueryData(["requisition", requisition.id], requisition);
    },
  });
}
