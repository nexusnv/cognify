import {
  listNotifications as listNotificationsEndpoint,
  markAllNotificationsRead as markAllNotificationsReadEndpoint,
  markNotificationRead as markNotificationReadEndpoint,
} from "@cognify/api-client/endpoints";
import type { ListNotificationsParams } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export async function listNotifications(params: ListNotificationsParams = {}) {
  const response = await listNotificationsEndpoint(params, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
}

export async function markNotificationRead(notificationId: string) {
  const response = await markNotificationReadEndpoint(notificationId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
}

export async function markAllNotificationsRead() {
  const response = await markAllNotificationsReadEndpoint(withActiveTenantHeader());
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
