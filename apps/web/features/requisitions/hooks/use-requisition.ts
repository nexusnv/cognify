"use client";

import { useQuery } from "@tanstack/react-query";
import { getRequisition, getRequisitionActivity } from "../api/requisitions-api";

export function useRequisition(requisitionId: string) {
  return useQuery({
    queryKey: ["requisition", requisitionId],
    queryFn: () => getRequisition(requisitionId),
  });
}

export function useRequisitionActivity(requisitionId: string) {
  return useQuery({
    queryKey: ["requisition", requisitionId, "activity"],
    queryFn: () => getRequisitionActivity(requisitionId),
  });
}
