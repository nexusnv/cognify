import {
  createApprovalPolicy,
  createApprovalPolicyVersion,
  createApprovalDelegation,
  approveApprovalTask as approveApprovalTaskEndpoint,
  delegateApprovalTask as delegateApprovalTaskEndpoint,
  getApprovalTask,
  getApprovalPolicy,
  getApprovalSlaSummary,
  getRequisitionApprovalSummary,
  listApprovalDelegations as listApprovalDelegationsEndpoint,
  listApprovalTasks as listApprovalTasksEndpoint,
  listApprovalPolicies as listApprovalPoliciesEndpoint,
  previewApprovalPolicy,
  publishApprovalPolicyVersion,
  rejectApprovalTask as rejectApprovalTaskEndpoint,
  requestApprovalChanges,
  routeRequisitionForApproval,
  retireApprovalPolicyVersion,
  updateApprovalPolicy,
  viewApprovalTask,
} from "@cognify/api-client/endpoints";
import type {
  ApprovalSummary as ApiApprovalSummary,
  ApprovalDelegation,
  ApprovalSlaSummary,
  ApprovalTask as ApiApprovalTask,
  ApprovalTaskActionRequest,
  ApprovalTaskQueueResponse,
  ApprovalPolicy as ApiApprovalPolicy,
  ApprovalPolicyListResponse,
  ApprovalPolicyVersion as ApiApprovalPolicyVersion,
  ApprovalPreview as ApiApprovalPreview,
  DelegateApprovalTaskRequest,
  ListApprovalTasksParams,
  PreviewApprovalPolicyRequest,
  RejectApprovalTaskRequest,
  RequestApprovalChangesRequest,
  StoreApprovalPolicyRequest,
  StoreApprovalDelegationRequest,
  StoreApprovalPolicyVersionRequest,
  UpdateApprovalPolicyRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import type {
  ApprovalPolicy,
  ApprovalSummary,
  ApprovalTaskFilters,
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
  if (response.status !== 201) throw response.data;
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

export async function previewApprovalPolicyRoute(values: PreviewApprovalPolicyRequest) {
  const response = await previewApprovalPolicy(values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as ApiApprovalPreview;
}

export async function listApprovalTasks(filters: ApprovalTaskFilters = {}) {
  const response = await listApprovalTasksEndpoint(
    filters as ListApprovalTasksParams,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return response.data as ApprovalTaskQueueResponse;
}

export async function fetchApprovalTask(taskId: string) {
  const response = await getApprovalTask(taskId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as ApiApprovalTask;
}

export async function markApprovalTaskViewed(taskId: string) {
  const response = await viewApprovalTask(taskId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as ApiApprovalTask;
}

export async function approveApprovalTask(taskId: string, values: ApprovalTaskActionRequest) {
  const response = await approveApprovalTaskEndpoint(taskId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as ApiApprovalTask;
}

export async function listApprovalDelegations(): Promise<ApprovalDelegation[]> {
  const response = await listApprovalDelegationsEndpoint(withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data;
}

export async function fetchApprovalSlaSummary(): Promise<ApprovalSlaSummary> {
  const response = await getApprovalSlaSummary(withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data;
}

export async function createTaskDelegation(values: StoreApprovalDelegationRequest) {
  const response = await createApprovalDelegation(values, withActiveTenantHeader());
  if (response.status !== 201) throw response.data;
  return response.data.data as ApprovalDelegation;
}

export async function delegateApprovalTask(taskId: string, values: DelegateApprovalTaskRequest) {
  const response = await delegateApprovalTaskEndpoint(taskId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as ApiApprovalTask;
}

export async function rejectApprovalTask(taskId: string, values: RejectApprovalTaskRequest) {
  const response = await rejectApprovalTaskEndpoint(taskId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as ApiApprovalTask;
}

export async function requestApprovalTaskChanges(
  taskId: string,
  values: RequestApprovalChangesRequest,
) {
  const response = await requestApprovalChanges(taskId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as ApiApprovalTask;
}

export async function routeRequisitionApproval(requisitionId: string) {
  const response = await routeRequisitionForApproval(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data;
}

export async function fetchRequisitionApprovalSummary(
  requisitionId: string,
): Promise<ApprovalSummary | null> {
  const response = await getRequisitionApprovalSummary(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as ApiApprovalSummary | null;
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
