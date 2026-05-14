"use client";

import * as Popover from "@radix-ui/react-popover";
import type { ReactNode } from "react";
import { NotificationItem } from "./notification-item";
import { useMarkAllNotificationsRead, useUnreadNotifications } from "../hooks/use-notifications";

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
        <Popover.Content
          align="end"
          sideOffset={8}
          className="z-50 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-xl border bg-background shadow-xl"
        >
          <div className="flex items-center justify-between border-b px-4 py-3">
            <h2 className="text-sm font-semibold">Notifications</h2>
            <button
              type="button"
              onClick={() => markAllRead.mutate()}
              disabled={!notifications.data?.meta.unreadCount || markAllRead.isPending}
              className="text-xs font-medium text-primary disabled:text-muted-foreground"
            >
              Mark all read
            </button>
          </div>

          {notifications.isLoading && (
            <div className="px-4 py-6 text-sm text-muted-foreground">Loading notifications...</div>
          )}

          {notifications.isError && (
            <div className="grid gap-3 px-4 py-6">
              <p className="text-sm text-destructive">Failed to load notifications.</p>
              <button
                type="button"
                onClick={() => void notifications.refetch()}
                className="justify-self-start rounded-md border px-3 py-1.5 text-sm"
              >
                Retry
              </button>
            </div>
          )}

          {notifications.data && notifications.data.data.length === 0 && (
            <div className="px-4 py-8 text-sm text-muted-foreground">No notifications for this view.</div>
          )}

          {notifications.data && notifications.data.data.length > 0 && (
            <ul className="max-h-96 overflow-y-auto">
              {notifications.data.data.map((notification) => (
                <NotificationItem key={notification.id} notification={notification} />
              ))}
            </ul>
          )}
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  );
}
