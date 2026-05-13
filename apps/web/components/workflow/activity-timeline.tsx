import { FileClock } from "lucide-react";
import type { LucideIcon } from "lucide-react";

export type ActivityTimelineActor = {
  id?: string;
  name?: string | null;
  email?: string | null;
};

export type ActivityTimelineEvent = {
  id: string;
  action: string;
  message: string;
  occurredAt: string;
  actor?: ActivityTimelineActor | null;
  targetDisplay?: string | null;
  metadata?: Record<string, unknown> | null;
};

export type ActivityTimelineActionIcons = Partial<Record<string, LucideIcon>> & {
  default?: LucideIcon;
};

export function ActivityTimeline({
  events,
  emptyMessage = "No activity has been recorded yet.",
  actionIcons = {},
}: {
  events: ActivityTimelineEvent[];
  emptyMessage?: string;
  actionIcons?: ActivityTimelineActionIcons;
}) {
  if (events.length === 0) {
    return <p className="text-sm text-muted-foreground">{emptyMessage}</p>;
  }

  return (
    <ol className="space-y-3">
      {events.map((event) => {
        const Icon = actionIcons[event.action] ?? actionIcons.default ?? FileClock;
        const actorName = event.actor?.name ?? "System";
        const formattedTime = new Date(event.occurredAt).toLocaleString();

        return (
          <li key={event.id} className="flex gap-3 rounded-md border p-3">
            <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md border bg-card">
              <Icon className="h-4 w-4" aria-hidden="true" />
            </span>
            <div className="min-w-0">
              <p className="text-sm font-medium">{event.message}</p>
              <p className="text-sm text-muted-foreground">
                {actorName} · {formattedTime}
              </p>
              {event.targetDisplay ? (
                <p className="mt-1 text-xs text-muted-foreground">{event.targetDisplay}</p>
              ) : null}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
