import type { ProjectActivity } from "../types/project-view-model";

export function ProjectActivityTimeline({ events }: { events: ProjectActivity[] }) {
  if (events.length === 0) {
    return <p className="text-sm text-muted-foreground">No project activity yet.</p>;
  }

  return (
    <ol className="space-y-3">
      {events.map((event) => (
        <li key={event.id} className="rounded-md border p-3">
          <div className="flex items-start justify-between gap-3">
            <p className="font-medium">{event.type}</p>
            <time className="text-xs text-muted-foreground">{formatDate(event.occurredAt)}</time>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            {event.actor?.name ?? "System"}
            {event.actor?.email ? ` (${event.actor.email})` : ""}
          </p>
        </li>
      ))}
    </ol>
  );
}

function formatDate(value: string | null) {
  if (!value) return "Unknown time";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}
