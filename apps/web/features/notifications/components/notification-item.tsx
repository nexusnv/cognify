"use client";

import { useRouter } from "next/navigation";
import type { Notification } from "@cognify/api-client/schemas";
import { Button } from "@cognify/ui";
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
      <Button
        type="button"
        variant="ghost"
        onClick={openNotification}
        className="grid h-auto w-full justify-start gap-1 rounded-none px-4 py-3 text-left whitespace-normal hover:bg-muted/60"
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
      </Button>
    </li>
  );
}
