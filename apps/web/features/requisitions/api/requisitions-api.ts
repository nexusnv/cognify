import {
  createRequisition,
  getRequisition as getRequisitionEndpoint,
  listRequisitionActivity,
  listRequisitions as listRequisitionsEndpoint,
  submitRequisition as submitRequisitionEndpoint,
  updateRequisition,
} from "@cognify/api-client/endpoints";
import type { ListRequisitionActivity200, ListRequisitionsParams } from "@cognify/api-client/schemas";
import type {
  Requisition,
  RequisitionFormValues,
  RequisitionListResponse,
} from "../types/requisition-view-model";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";

type RequisitionQuery = {
  search?: string;
  status?: string;
  owner?: string;
  neededByFrom?: string;
  neededByTo?: string;
};

export async function listRequisitions(query: RequisitionQuery = {}) {
  const response = await listRequisitionsEndpoint(query as ListRequisitionsParams, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data as RequisitionListResponse;
}

export async function getRequisition(requisitionId: string) {
  const response = await getRequisitionEndpoint(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as Requisition;
}

export async function getRequisitionActivity(requisitionId: string) {
  const response = await listRequisitionActivity(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data satisfies ListRequisitionActivity200;
}

export async function createRequisitionDraft(values: RequisitionFormValues) {
  const response = await createRequisition(values, withActiveTenantHeader());
  if (response.status !== 201) throw response.data;
  return response.data.data as Requisition;
}

export async function updateRequisitionDraft(requisitionId: string, values: RequisitionFormValues) {
  const response = await updateRequisition(requisitionId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as Requisition;
}

export async function submitRequisition(requisitionId: string) {
  const response = await submitRequisitionEndpoint(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data as { data: Requisition };
}

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}
