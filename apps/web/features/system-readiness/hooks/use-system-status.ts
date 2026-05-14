"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchSystemStatus } from "../api/system-readiness-api";

export function useSystemStatus(tenantId: string | null, enabled = true) {
  return useQuery({
    queryKey: ["system-status", tenantId],
    queryFn: () => fetchSystemStatus(tenantId),
    enabled: enabled && tenantId !== null,
    retry: false,
  });
}
