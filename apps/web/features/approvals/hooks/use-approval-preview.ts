"use client";

import { useMemo } from "react";
import type { ApprovalPolicyFormValues } from "../types/approval-view-model";

export function useApprovalPreview(values: ApprovalPolicyFormValues) {
  return useMemo(
    () => ({
      createsTasks: false,
      stages: values.routeTemplate.stages,
      slaRules: values.slaRules,
      warnings: values.routeTemplate.stages.length === 0 ? ["No approval stages configured"] : [],
    }),
    [values],
  );
}
