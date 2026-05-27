"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { fetchProcurementCalendarEvents } from "../api/procurement-calendar-api";
import type { ListProcurementCalendarEventsParams } from "@cognify/api-client/schemas";

export const procurementCalendarKeys = {
  all: (tenantId: string | null = getStoredActiveTenantId()) =>
    ["procurement-calendar", tenantId ?? "no-tenant"] as const,
  events: (params: ListProcurementCalendarEventsParams, tenantId: string | null = getStoredActiveTenantId()) =>
    [...procurementCalendarKeys.all(tenantId), "events", params] as const,
};

export function useProcurementCalendarEvents(params: ListProcurementCalendarEventsParams) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: procurementCalendarKeys.events(params, tenantId),
    queryFn: () => fetchProcurementCalendarEvents(params),
    enabled: Boolean(params.from && params.to),
  });
}
