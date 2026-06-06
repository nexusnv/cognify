import { Badge, Card, CardContent, CardHeader, CardTitle, ScrollArea, Separator } from "@cognify/ui";
import type { ProjectActivity } from "../types/project-view-model";

export function ProjectActivityTimeline({ events }: { events: ProjectActivity[] }) {
  if (events.length === 0) {
    return <p className="text-sm text-muted-foreground">No project activity yet.</p>;
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Activity</CardTitle>
      </CardHeader>
      <CardContent>
        <ScrollArea className="max-h-[28rem] pr-4">
          <ol className="space-y-4">
            {events.map((event, index) => (
              <li key={event.id}>
                <div className="flex items-start justify-between gap-3">
                  <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="font-medium">{event.type}</p>
                      <Badge variant="secondary">Event</Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      {event.actor?.name ?? "System"}
                      {event.actor?.email ? ` (${event.actor.email})` : ""}
                    </p>
                  </div>
                  <time className="text-xs text-muted-foreground">{formatDate(event.occurredAt)}</time>
                </div>
                {index < events.length - 1 ? <Separator className="mt-4" /> : null}
              </li>
            ))}
          </ol>
        </ScrollArea>
      </CardContent>
    </Card>
  );
}

function formatDate(value: string | null) {
  if (!value) return "Unknown time";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}
