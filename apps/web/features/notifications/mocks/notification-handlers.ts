import { http, HttpResponse } from "msw";
import type { Notification } from "@cognify/api-client/schemas";
import { buildNotificationListResponse, notificationFixtures } from "./notification-fixtures";

let notifications: Notification[] = structuredClone(notificationFixtures);

export function resetNotificationMockState() {
  notifications = structuredClone(notificationFixtures);
}

export const notificationHandlers = [
  http.get("/api/notifications", ({ request }) => {
    const url = new URL(request.url);
    const status = parseStatus(url.searchParams.get("status"));
    const limit = parseLimit(url.searchParams.get("limit"));
    const response = buildNotificationListResponse(notifications, status);
    const limited = response.data.slice(0, limit);

    return HttpResponse.json({
      data: limited,
      meta: {
        unreadCount: response.meta.unreadCount,
        returned: limited.length,
        status: response.meta.status,
      },
    });
  }),

  http.post("/api/notifications/:notification/read", ({ params }) => {
    const notificationId = String(params.notification);
    const index = notifications.findIndex((notification) => notification.id === notificationId);

    if (index === -1) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Not found." } },
        { status: 404 },
      );
    }

    const notification = notifications[index];
    notifications[index] = {
      ...notification,
      readAt: notification.readAt ?? "2026-05-14T11:00:00Z",
    };

    return HttpResponse.json({ data: notifications[index] });
  }),

  http.post("/api/notifications/read-all", () => {
    let marked = 0;

    notifications = notifications.map((notification) => {
      if (notification.readAt !== null) return notification;
      marked += 1;
      return {
        ...notification,
        readAt: "2026-05-14T11:00:00Z",
      };
    });

    return HttpResponse.json({
      data: { marked },
      meta: { unreadCount: 0 },
    });
  }),
];

function parseStatus(value: string | null) {
  if (value === "unread" || value === "read") return value;
  return "all";
}

function parseLimit(value: string | null) {
  const parsed = Number(value);
  if (!Number.isFinite(parsed) || parsed < 1) return 20;
  return Math.floor(parsed);
}
