"use client";

import { useQuery } from "@tanstack/react-query";
import { listRequisitions } from "../api/requisitions-api";
import type { RequisitionQueuePreset, RequisitionStatus } from "../types/requisition-view-model";

export type RequisitionQuery = {
  search?: string;
  status?: RequisitionStatus | "";
  requester?: string;
  owner?: string;
  department?: string;
  neededByFrom?: string;
  neededByTo?: string;
  amountMin?: string;
  amountMax?: string;
  updatedFrom?: string;
  updatedTo?: string;
  queuePreset?: RequisitionQueuePreset;
};

export function useRequisitions(query: RequisitionQuery = {}) {
  return useQuery({
    queryKey: ["requisitions", query],
    queryFn: () => listRequisitions(query),
  });
}
