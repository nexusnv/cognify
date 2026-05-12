import { listAuditEvents } from "@cognify/api-client/endpoints";
import type { AuditEventListResponse, ListAuditEventsParams } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";

export async function fetchAuditEvents(params: ListAuditEventsParams = {}): Promise<AuditEventListResponse> {
  const response = await listAuditEvents(params, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
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
