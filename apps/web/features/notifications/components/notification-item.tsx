"use client";

import { useRouter } from "next/navigation";
import type { Notification } from "@cognify/api-client/schemas";
import { useMarkNotificationRead } from "../hooks/use-notifications";

function formatCreatedAt(value: string) {
  return new Intl.DateTimeFormat("en", {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}

export function NotificationItem({ notification }: { notification: Notification }) {
  const router = useRouter();
  const markRead = useMarkNotificationRead();
  const unread = notification.readAt === null;

  const openNotification = async () => {
    if (unread) {
      await markRead.mutateAsync(notification.id);
    }

    if (notification.href) {
      router.push(notification.href);
    }
  };

  return (
    <li className="border-b last:border-b-0">
      <button
        type="button"
        onClick={openNotification}
        className="grid w-full gap-1 px-4 py-3 text-left hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        <span className="flex items-center gap-2">
          {unread && <span className="h-2 w-2 rounded-full bg-primary" aria-label="Unread" />}
          <span className="text-sm font-medium">{notification.title}</span>
        </span>
        {notification.body && <span className="text-sm text-muted-foreground">{notification.body}</span>}
        <span className="text-xs text-muted-foreground">
          {notification.actor?.name ? `${notification.actor.name} · ` : ""}
          {notification.subject?.label ? `${notification.subject.label} · ` : ""}
          {formatCreatedAt(notification.createdAt)}
        </span>
      </button>
    </li>
  );
}
