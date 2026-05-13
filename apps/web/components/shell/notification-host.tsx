"use client";

import { Bell } from "lucide-react";

export function NotificationHost() {
  // Notification behavior is intentionally deferred to the Notification Foundation epic.
  return (
    <button
      type="button"
      className="inline-flex min-h-10 w-10 items-center justify-center rounded-md border text-muted-foreground"
      aria-label="Open notifications"
      disabled
    >
      <Bell className="h-4 w-4" aria-hidden="true" />
    </button>
  );
}
