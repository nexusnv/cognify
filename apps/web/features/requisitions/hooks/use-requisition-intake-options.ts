"use client";

import { useQuery } from "@tanstack/react-query";
import { getRequisitionIntakeOptions } from "../api/requisitions-api";

export function useRequisitionIntakeOptions() {
  return useQuery({
    queryKey: ["requisition-intake-options"],
    queryFn: getRequisitionIntakeOptions,
  });
}
