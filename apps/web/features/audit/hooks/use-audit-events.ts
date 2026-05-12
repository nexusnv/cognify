"use client";

import { useQuery } from "@tanstack/react-query";
import type { ListAuditEventsParams } from "@cognify/api-client/schemas";

import { fetchAuditEvents } from "../api/audit-api";

export function useAuditEvents(params: ListAuditEventsParams = {}) {
  return useQuery({
    queryKey: ["audit-events", params],
    queryFn: () => fetchAuditEvents(params),
  });
}
