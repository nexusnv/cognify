import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit {
  if (!tenantId) {
    throw new Error("Missing active tenant context");
  }

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

export function unwrapData<T>(response: { status: number; data?: unknown }, success = 200): T {
  if (response.status !== success) {
    throw response.data ?? response;
  }

  if (typeof response.data !== 'object' || response.data === null || !('data' in response.data)) {
    throw new Error(`Unexpected response shape: expected nested data envelope, got ${typeof response.data}`);
  }

  return (response.data as { data: T }).data;
}

export function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw (error as { data: unknown }).data;
  }

  throw error;
}
