"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { applyRequisitionTemplate, listRequisitionTemplates } from "../api/requisitions-api";
import type { RequisitionTemplateMode } from "../types/requisition-view-model";

export function useRequisitionTemplates() {
  return useQuery({
    queryKey: ["requisition-templates"],
    queryFn: listRequisitionTemplates,
  });
}

export function useApplyRequisitionTemplate() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      requisitionId,
      templateId,
      mode,
      lockVersion,
    }: {
      requisitionId: string;
      templateId: string;
      mode: RequisitionTemplateMode;
      lockVersion: number;
    }) => applyRequisitionTemplate(requisitionId, templateId, mode, lockVersion),
    onSuccess: async (requisition) => {
      await queryClient.invalidateQueries({ queryKey: ["requisitions"] });
      queryClient.setQueryData(["requisition", requisition.id], requisition);
    },
  });
}
