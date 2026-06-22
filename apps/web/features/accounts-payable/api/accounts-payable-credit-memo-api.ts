import {
  listSupplierCreditMemos,
  createSupplierCreditMemo,
  showSupplierCreditMemo,
  updateSupplierCreditMemo,
  submitSupplierCreditMemoForApproval,
  postSupplierCreditMemo,
  voidSupplierCreditMemo,
  addSupplierCreditMemoLine,
  updateSupplierCreditMemoLine,
  removeSupplierCreditMemoLine,
} from "@cognify/api-client/endpoints";
import type {
  SupplierCreditMemo,
  ListSupplierCreditMemosParams,
  CreateSupplierCreditMemoRequest,
  UpdateSupplierCreditMemoRequest,
  SubmitSupplierCreditMemoForApprovalRequest,
  PostSupplierCreditMemoRequest,
  VoidSupplierCreditMemoRequest,
  AddSupplierCreditMemoLineRequest,
  UpdateSupplierCreditMemoLineRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData, unwrapData } from "./api-helpers";

function unwrapResource<T>(
  response: { status: number; data?: { data?: T } | unknown },
  success = 200,
): T {
  if (response.status !== success) {
    throw response.data ?? response;
  }

  if (typeof response.data !== "object" || response.data === null || !("data" in response.data)) {
    throw new Error(`Unexpected response shape: expected nested data envelope, got ${typeof response.data}`);
  }

  return (response.data as { data: T }).data;
}

export async function listCreditMemos(
  filters: ListSupplierCreditMemosParams = {},
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<{ data: SupplierCreditMemo[]; total: number }> {
  const response = await listSupplierCreditMemos(filters, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  const data = unwrapData<SupplierCreditMemo[]>(response);
  return {
    data,
    total: (response.data as { meta?: { total?: number } })?.meta?.total ?? data.length,
  };
}

export async function createCreditMemo(
  payload: CreateSupplierCreditMemoRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await createSupplierCreditMemo(payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapResource<SupplierCreditMemo>(response, 201);
}

export async function showCreditMemo(
  id: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await showSupplierCreditMemo(id, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function updateCreditMemo(
  id: string,
  payload: UpdateSupplierCreditMemoRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await updateSupplierCreditMemo(id, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function submitCreditMemoForApproval(
  id: string,
  payload: SubmitSupplierCreditMemoForApprovalRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await submitSupplierCreditMemoForApproval(id, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function postCreditMemo(
  id: string,
  payload: PostSupplierCreditMemoRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await postSupplierCreditMemo(id, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function voidCreditMemo(
  id: string,
  payload: VoidSupplierCreditMemoRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await voidSupplierCreditMemo(id, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function addCreditMemoLine(
  creditMemoId: string,
  payload: AddSupplierCreditMemoLineRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await addSupplierCreditMemoLine(creditMemoId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapResource<SupplierCreditMemo>(response, 201);
}

export async function updateCreditMemoLine(
  creditMemoId: string,
  lineId: string,
  payload: UpdateSupplierCreditMemoLineRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await updateSupplierCreditMemoLine(creditMemoId, lineId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function removeCreditMemoLine(
  creditMemoId: string,
  lineId: string,
  _lockVersion: number,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<void> {
  await removeSupplierCreditMemoLine(creditMemoId, lineId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
}
