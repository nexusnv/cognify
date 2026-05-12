import type {
  ApiValidationError,
  Requisition,
  RequisitionActivityEvent,
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
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value) params.set(key, value);
  }

  return fetchJson<RequisitionListResponse>(`/api/requisitions?${params.toString()}`);
}

export async function getRequisition(requisitionId: string) {
  const response = await fetchJson<{ data: Requisition }>(`/api/requisitions/${requisitionId}`);

  return response.data;
}

export async function getRequisitionActivity(requisitionId: string) {
  return fetchJson<{ data: RequisitionActivityEvent[] }>(`/api/requisitions/${requisitionId}/activity`);
}

export async function createRequisitionDraft(values: RequisitionFormValues) {
  const response = await fetchJson<{ data: Requisition }>("/api/requisitions", {
    method: "POST",
    body: JSON.stringify(values),
  });

  return response.data;
}

export async function updateRequisitionDraft(requisitionId: string, values: RequisitionFormValues) {
  const response = await fetchJson<{ data: Requisition }>(`/api/requisitions/${requisitionId}`, {
    method: "PATCH",
    body: JSON.stringify(values),
  });

  return response.data;
}

export async function submitRequisition(requisitionId: string) {
  return fetchJson<{ data: Requisition }>(`/api/requisitions/${requisitionId}/submit`, {
    method: "POST",
  });
}

async function fetchJson<T>(url: string, init?: RequestInit): Promise<T> {
  const headers = new Headers(init?.headers);
  headers.set("Content-Type", "application/json");
  const tenantId = getStoredActiveTenantId();
  if (tenantId) headers.set("X-Tenant-Id", tenantId);

  const response = await fetch(url, {
    ...init,
    credentials: "include",
    headers,
  });

  const payload = (await response.json()) as T | ApiValidationError;

  if (!response.ok) {
    throw payload;
  }

  return payload as T;
}
