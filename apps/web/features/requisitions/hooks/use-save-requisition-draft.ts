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
    }) => {
      if (requisitionId) {
        if (lockVersion == null) {
          throw new Error("lockVersion required for updates");
        }

        return updateRequisitionDraft(requisitionId, values, lockVersion);
      }

      return createRequisitionDraft(values);
    },
    onSuccess: async (requisition) => {
      await queryClient.invalidateQueries({ queryKey: ["requisitions"] });
      queryClient.setQueryData(["requisition", requisition.id], requisition);
    },
  });
}
