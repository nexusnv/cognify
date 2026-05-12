import {
  getCurrentUser as getCurrentUserEndpoint,
  login as loginEndpoint,
  logout as logoutEndpoint,
  setCurrentTenant as setCurrentTenantEndpoint,
  updateCurrentUserProfile as updateCurrentUserProfileEndpoint,
  type CurrentUserResponse,
  type LoginRequest,
  type SetCurrentTenantRequest,
  type UpdateCurrentUserProfileRequest,
} from "@cognify/api-client";
import type { LoginFormValues } from "../schemas/login-schema";
import type { ProfileFormValues } from "../schemas/profile-schema";

const ACTIVE_TENANT_KEY = "cognify.activeTenantId";
type SuccessfulResponse<T> = { data: T };

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
  await loginEndpoint(values satisfies LoginRequest);
}

export async function logout() {
  await logoutEndpoint();
}

export async function getCurrentUser() {
  const response = (await getCurrentUserEndpoint(
    withActiveTenantHeader(),
  )) as SuccessfulResponse<CurrentUserResponse>;
  return response.data;
}

export async function updateCurrentUserProfile(values: ProfileFormValues) {
  const request = {
    ...values,
    avatarUrl: values.avatarUrl || null,
  } satisfies UpdateCurrentUserProfileRequest;
  const response = (await updateCurrentUserProfileEndpoint(
    request,
    withActiveTenantHeader(),
  )) as SuccessfulResponse<CurrentUserResponse>;
  return response.data;
}

export async function setCurrentTenant(tenantId: string) {
  const request = { tenantId } satisfies SetCurrentTenantRequest;
  const response = (await setCurrentTenantEndpoint(
    request,
  )) as SuccessfulResponse<CurrentUserResponse>;
  storeActiveTenantId(tenantId);
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
