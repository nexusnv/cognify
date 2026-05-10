"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { submitRequisition } from "../api/requisitions-api";

export function useSubmitRequisition() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (requisitionId: string) => submitRequisition(requisitionId),
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ["requisitions"] });
      await queryClient.invalidateQueries({ queryKey: ["requisition", response.data.id, "activity"] });
      queryClient.setQueryData(["requisition", response.data.id], response.data);
    },
  });
}
