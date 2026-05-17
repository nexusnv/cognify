"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  createApprovalPolicyVersionDraft,
  createApprovalPolicyDraft,
  listApprovalPolicies,
  publishPolicyVersion,
  retirePolicyVersion,
  updateApprovalPolicyDraft,
} from "../api/approvals-api";
import type { StoreApprovalPolicyVersionRequest } from "@cognify/api-client/schemas";
import type { ApprovalPolicyFormValues } from "../types/approval-view-model";

export const approvalPolicyKeys = {
  all: ["approval-policies"] as const,
  lists: () => [...approvalPolicyKeys.all, "list"] as const,
  detail: (policyId: string) => [...approvalPolicyKeys.all, "detail", policyId] as const,
};

export function useApprovalPolicies() {
  return useQuery({
    queryKey: approvalPolicyKeys.lists(),
    queryFn: listApprovalPolicies,
  });
}

export function useCreateApprovalPolicy() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: ApprovalPolicyFormValues) => createApprovalPolicyDraft(values),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.lists() }),
  });
}

export function useUpdateApprovalPolicy(policyId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: Partial<ApprovalPolicyFormValues>) =>
      updateApprovalPolicyDraft(policyId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.lists() });
      queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.detail(policyId) });
    },
  });
}

export function usePublishApprovalPolicyVersion(policyId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (versionId: string) => publishPolicyVersion(versionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.lists() });
      queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.detail(policyId) });
    },
  });
}

export function useCreateApprovalPolicyVersion(policyId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: StoreApprovalPolicyVersionRequest) =>
      createApprovalPolicyVersionDraft(policyId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.lists() });
      queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.detail(policyId) });
    },
  });
}

export function useRetireApprovalPolicyVersion(policyId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (versionId: string) => retirePolicyVersion(versionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.lists() });
      queryClient.invalidateQueries({ queryKey: approvalPolicyKeys.detail(policyId) });
    },
  });
}
