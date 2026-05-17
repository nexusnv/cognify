import {
  createApprovalPolicy,
  createApprovalPolicyVersion,
  getApprovalPolicy,
  listApprovalPolicies as listApprovalPoliciesEndpoint,
  publishApprovalPolicyVersion,
  retireApprovalPolicyVersion,
  updateApprovalPolicy,
} from "@cognify/api-client/endpoints";
import type {
  ApprovalPolicy as ApiApprovalPolicy,
  ApprovalPolicyListResponse,
  ApprovalPolicyVersion as ApiApprovalPolicyVersion,
  StoreApprovalPolicyRequest,
  StoreApprovalPolicyVersionRequest,
  UpdateApprovalPolicyRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import type {
  ApprovalPolicy,
  ApprovalPolicyFormValues,
  ApprovalPolicyVersion,
} from "../types/approval-view-model";

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;
  return { headers: { "X-Tenant-Id": tenantId } };
}

export async function listApprovalPolicies() {
  const response = await listApprovalPoliciesEndpoint(withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapApprovalPolicyListResponse(response.data);
}

export async function fetchApprovalPolicy(policyId: string) {
  const response = await getApprovalPolicy(policyId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapApprovalPolicy(response.data.data);
}

export async function createApprovalPolicyDraft(values: ApprovalPolicyFormValues) {
  const response = await createApprovalPolicy(
    values as StoreApprovalPolicyRequest,
    withActiveTenantHeader(),
  );
  if (response.status !== 201) throw response.data;
  return mapApprovalPolicy(response.data.data);
}

export async function updateApprovalPolicyDraft(
  policyId: string,
  values: Partial<ApprovalPolicyFormValues>,
) {
  const response = await updateApprovalPolicy(
    policyId,
    values as UpdateApprovalPolicyRequest,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return mapApprovalPolicy(response.data.data);
}

export async function createApprovalPolicyVersionDraft(
  policyId: string,
  values: StoreApprovalPolicyVersionRequest,
) {
  const response = await createApprovalPolicyVersion(policyId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapApprovalPolicyVersion(response.data.data);
}

export async function publishPolicyVersion(versionId: string) {
  const response = await publishApprovalPolicyVersion(versionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapApprovalPolicyVersion(response.data.data);
}

export async function retirePolicyVersion(versionId: string) {
  const response = await retireApprovalPolicyVersion(versionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapApprovalPolicyVersion(response.data.data);
}

export function mapApprovalPolicy(policy: ApiApprovalPolicy): ApprovalPolicy {
  return {
    ...policy,
    description: policy.description ?? "",
    versions: policy.versions.map(mapApprovalPolicyVersion),
  };
}

export function mapApprovalPolicyVersion(version: ApiApprovalPolicyVersion): ApprovalPolicyVersion {
  return {
    ...version,
    routeTemplate: version.routeTemplate as ApprovalPolicyVersion["routeTemplate"],
    rules: version.rules as ApprovalPolicyVersion["rules"],
    slaRules: version.slaRules as ApprovalPolicyVersion["slaRules"],
  };
}

export function mapApprovalPolicyListResponse(response: ApprovalPolicyListResponse) {
  return {
    data: response.data.map(mapApprovalPolicy),
  };
}
