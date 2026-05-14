"use client";

import { listGlobalSearch } from "@cognify/api-client";
import type { ListGlobalSearchParams, SearchResponse } from "@cognify/api-client";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export async function searchRecords(
  params: ListGlobalSearchParams,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SearchResponse> {
  const activeTenantId = tenantId ?? getStoredActiveTenantId();
  const response = await listGlobalSearch(
    {
      ...params,
      query: params.query.trim(),
    },
    {
      headers: activeTenantId ? { "X-Tenant-Id": activeTenantId } : undefined,
    },
  );

  if (response.status !== 200) {
    throw response;
  }

  return response.data;
}

export function getSearchErrorMessage(error: unknown): string {
  if (typeof error !== "object" || error === null) {
    return "Search failed.";
  }

  const message = getNestedMessage(error as Record<string, unknown>);
  if (message) {
    return message;
  }

  if ("message" in error && typeof error.message === "string") {
    return error.message;
  }

  return "Search failed.";
}

function getNestedMessage(error: Record<string, unknown>): string | null {
  const nestedError = error.data;
  if (typeof nestedError === "object" && nestedError !== null) {
    const message = (nestedError as Record<string, unknown>).error;
    if (typeof message === "object" && message !== null) {
      const nestedMessage = (message as Record<string, unknown>).message;
      if (typeof nestedMessage === "string") {
        return nestedMessage;
      }
    }
  }

  const topLevelError = error.error;
  if (typeof topLevelError === "object" && topLevelError !== null) {
    const message = (topLevelError as Record<string, unknown>).message;
    if (typeof message === "string") {
      return message;
    }
  }

  return null;
}
