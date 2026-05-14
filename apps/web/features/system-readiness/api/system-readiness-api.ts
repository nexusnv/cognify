"use client";

import { getSystemStatus } from "@cognify/api-client/endpoints";
import type { SystemStatusResponse } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export async function fetchSystemStatus(tenantId: string | null = getStoredActiveTenantId()): Promise<SystemStatusResponse> {
  const response = await getSystemStatus(withActiveTenantHeader(tenantId));

  if (response.status !== 200) {
    throw response.data;
  }

  return response.data;
}

function withActiveTenantHeader(tenantId: string | null): RequestInit | undefined {
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}
