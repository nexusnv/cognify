import type { Notification, NotificationListResponse } from "@cognify/api-client/schemas";

export const notificationFixtures: Notification[] = [
  {
    id: "101",
    type: "requisition.submitted",
    title: "Requisition submitted",
    body: "REQ-2026-000042 is ready for procurement review.",
    href: "/requisitions/42",
    priority: "normal",
    readAt: null,
    createdAt: "2026-05-14T10:30:00Z",
    actor: { id: "7", name: "Maya Tan" },
    subject: { type: "requisition", id: "42", label: "REQ-2026-000042" },
    metadata: { number: "REQ-2026-000042" },
  },
  {
    id: "102",
    type: "attachment.uploaded",
    title: "Evidence uploaded",
    body: "supplier-quote.pdf was added to REQ-2026-000040.",
    href: "/requisitions/40",
    priority: "normal",
    readAt: null,
    createdAt: "2026-05-14T09:10:00Z",
    actor: { id: "8", name: "Nora Buyer" },
    subject: { type: "requisition", id: "40", label: "REQ-2026-000040" },
    metadata: { filename: "supplier-quote.pdf", number: "REQ-2026-000040" },
  },
  {
    id: "103",
    type: "system.announcement",
    title: "Maintenance window scheduled",
    body: "Cognify demo data will refresh tonight at 23:00.",
    href: null,
    priority: "normal",
    readAt: "2026-05-14T08:00:00Z",
    createdAt: "2026-05-13T16:00:00Z",
    actor: null,
    subject: null,
    metadata: {},
  },
];

export function buildNotificationListResponse(
  notifications: Notification[],
  status: "all" | "unread" | "read" = "all",
): NotificationListResponse {
  const filtered = notifications.filter((notification) => {
    if (status === "unread") return notification.readAt === null;
    if (status === "read") return notification.readAt !== null;
    return true;
  });

  return {
    data: filtered,
    meta: {
      unreadCount: notifications.filter((notification) => notification.readAt === null).length,
      returned: filtered.length,
      status,
    },
  };
}
