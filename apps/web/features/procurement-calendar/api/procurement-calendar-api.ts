import { listProcurementCalendarEvents } from "@cognify/api-client/endpoints";
import type {
  ListProcurementCalendarEventsParams,
  ProcurementCalendarEventCollection,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

export async function fetchProcurementCalendarEvents(
  params: ListProcurementCalendarEventsParams,
): Promise<ProcurementCalendarEventCollection> {
  const response = await listProcurementCalendarEvents(params, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data;
}
