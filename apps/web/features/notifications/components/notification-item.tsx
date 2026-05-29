"use client";

import { useRouter } from "next/navigation";
import type { Notification } from "@cognify/api-client/schemas";
import { Badge, Card, CardContent, CardDescription, CardHeader, CardTitle } from "@cognify/ui";
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
    <Card
      className={unread ? "border-primary/40" : undefined}
      role="button"
      tabIndex={0}
      onClick={openNotification}
      onKeyDown={(event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          void openNotification();
        }
      }}
    >
      <CardHeader className="gap-2 pb-3">
        <div className="flex items-center justify-between gap-3">
          <CardTitle className="text-sm">{notification.title}</CardTitle>
          {unread ? <Badge variant="secondary">Unread</Badge> : <Badge variant="outline">Read</Badge>}
        </div>
        <CardDescription className="text-xs">
          {notification.actor?.name ? `${notification.actor.name} · ` : ""}
          {notification.subject?.label ? `${notification.subject.label} · ` : ""}
          {formatCreatedAt(notification.createdAt)}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-3 pt-0">
        {notification.body ? <p className="text-sm text-muted-foreground">{notification.body}</p> : null}
        <p className="text-sm font-medium text-primary">Open</p>
      </CardContent>
    </Card>
  );
}
