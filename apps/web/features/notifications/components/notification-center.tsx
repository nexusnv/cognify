"use client";

import * as Popover from "@radix-ui/react-popover";
import type { ReactNode } from "react";
import {
  AlertCircle,
  RefreshCw,
  ShieldAlert,
} from "lucide-react";
import { NotificationItem } from "./notification-item";
import { useMarkAllNotificationsRead, useUnreadNotifications } from "../hooks/use-notifications";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  ScrollArea,
} from "@cognify/ui";

export function NotificationCenter({
  open,
  onOpenChange,
  children,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  children?: ReactNode;
}) {
  const notifications = useUnreadNotifications();
  const markAllRead = useMarkAllNotificationsRead();

  return (
    <Popover.Root open={open} onOpenChange={onOpenChange}>
      {children && <Popover.Trigger asChild>{children}</Popover.Trigger>}
      <Popover.Portal>
        <Popover.Content align="end" sideOffset={8} className="z-50 w-[min(28rem,calc(100vw-2rem))]">
          <Card className="overflow-hidden py-0 shadow-xl">
            <CardHeader className="flex-row items-start justify-between gap-3 border-b px-4 py-4">
              <div className="space-y-1">
                <CardTitle className="text-sm">Notifications</CardTitle>
                <p className="text-xs text-muted-foreground">
                  {notifications.data?.meta.unreadCount ? (
                    <span className="inline-flex items-center gap-2">
                      <Badge variant="secondary">{notifications.data.meta.unreadCount} unread</Badge>
                      <span>Newest workflow activity</span>
                    </span>
                  ) : (
                    "No unread notifications right now."
                  )}
                </p>
              </div>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => markAllRead.mutate()}
                disabled={!notifications.data?.meta.unreadCount || markAllRead.isPending}
              >
                <ShieldAlert className="h-4 w-4" aria-hidden="true" />
                Mark all read
              </Button>
            </CardHeader>

            <CardContent className="p-0">
              {notifications.isLoading ? (
                <div className="flex items-center gap-2 px-4 py-6 text-sm text-muted-foreground">
                  <RefreshCw className="h-4 w-4 animate-spin" aria-hidden="true" />
                  Loading notifications...
                </div>
              ) : null}

              {notifications.isError ? (
                <Alert className="m-4" role="alert">
                  <AlertCircle className="h-4 w-4" aria-hidden="true" />
                  <AlertTitle>Failed to load notifications</AlertTitle>
                  <AlertDescription className="space-y-3">
                    <span>Failed to load notifications.</span>
                    <Button type="button" variant="outline" size="sm" onClick={() => void notifications.refetch()}>
                      Retry
                    </Button>
                  </AlertDescription>
                </Alert>
              ) : null}

              {notifications.data && notifications.data.data.length === 0 ? (
                <div className="px-4 py-8 text-sm text-muted-foreground">No notifications for this view.</div>
              ) : null}

              {notifications.data && notifications.data.data.length > 0 ? (
                <ScrollArea className="max-h-96">
                  <div className="grid gap-2 p-3">
                    {notifications.data.data.map((notification) => (
                      <NotificationItem key={notification.id} notification={notification} />
                    ))}
                  </div>
                </ScrollArea>
              ) : null}
            </CardContent>
          </Card>
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  );
}
