"use client";

import { useQuery } from "@tanstack/react-query";
import { previewApprovalPolicyRoute } from "../api/approvals-api";
import type { ApprovalPolicyFormValues } from "../types/approval-view-model";
import type { ApprovalPreviewContext } from "../types/approval-view-model";

export function approvalPreviewQueryKey(
  values?: ApprovalPolicyFormValues,
  context?: ApprovalPreviewContext,
) {
  return ["approval-preview", values, context] as const;
}

export function useApprovalPreview(values?: ApprovalPolicyFormValues, context?: ApprovalPreviewContext, enabled = true) {
  return useQuery({
    queryKey: approvalPreviewQueryKey(values, context),
    enabled: enabled && Boolean(values),
    queryFn: async () => {
      if (!values) {
        throw new Error("Approval preview values are required.");
      }

      return previewApprovalPolicyRoute({
        policyName: values.name || undefined,
        rules: values.rules,
        routeTemplate: values.routeTemplate,
        slaRules: values.slaRules,
        context,
      });
    },
  });
}
