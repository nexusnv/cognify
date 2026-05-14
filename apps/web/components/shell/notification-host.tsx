"use client";

import { useState } from "react";
import { Bell } from "lucide-react";
import { NotificationCenter } from "@/features/notifications/components/notification-center";
import { useUnreadNotifications } from "@/features/notifications/hooks/use-notifications";

export function NotificationHost() {
  const [open, setOpen] = useState(false);
  const notifications = useUnreadNotifications();
  const unreadCount = notifications.data?.meta.unreadCount ?? 0;
  const label =
    unreadCount > 0 ? `Open notifications, ${unreadCount} unread` : "Open notifications";

  return (
    <NotificationCenter open={open} onOpenChange={setOpen}>
      <button
        type="button"
        className="relative inline-flex min-h-10 w-10 items-center justify-center rounded-md border text-muted-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label={label}
      >
        <Bell className="h-4 w-4" aria-hidden="true" />
        {unreadCount > 0 && (
          <span className="absolute -right-1 -top-1 min-w-5 rounded-full bg-primary px-1.5 py-0.5 text-center text-[0.6875rem] font-semibold leading-none text-primary-foreground">
            {unreadCount}
          </span>
        )}
      </button>
    </NotificationCenter>
  );
}
