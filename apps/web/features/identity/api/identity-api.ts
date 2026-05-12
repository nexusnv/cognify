import type { CurrentUserResponse } from "../types/identity-view-model";
import type { LoginFormValues } from "../schemas/login-schema";
import type { ProfileFormValues } from "../schemas/profile-schema";

const ACTIVE_TENANT_KEY = "cognify.activeTenantId";

export function getStoredActiveTenantId() {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(ACTIVE_TENANT_KEY);
}

export function storeActiveTenantId(tenantId: string) {
  if (typeof window !== "undefined") {
    window.localStorage.setItem(ACTIVE_TENANT_KEY, tenantId);
  }
}

export async function login(values: LoginFormValues) {
  await fetchJson<void>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify(values),
  });
}

export async function logout() {
  await fetchJson<void>("/api/auth/logout", { method: "POST" });
}

export async function getCurrentUser() {
  return fetchJson<CurrentUserResponse>("/api/me");
}

export async function updateCurrentUserProfile(values: ProfileFormValues) {
  return fetchJson<CurrentUserResponse>("/api/me/profile", {
    method: "PATCH",
    body: JSON.stringify({ ...values, avatarUrl: values.avatarUrl || null }),
  });
}

export async function setCurrentTenant(tenantId: string) {
  storeActiveTenantId(tenantId);
  return fetchJson<CurrentUserResponse>("/api/tenants/current", {
    method: "POST",
    body: JSON.stringify({ tenantId }),
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

  if (response.status === 204) {
    return undefined as T;
  }

  const payload = (await response.json()) as T;
  if (!response.ok) throw payload;
  return payload;
}