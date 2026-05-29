import { FileClock } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { Badge, Card, CardContent, CardHeader, CardTitle, ScrollArea, Separator } from "@cognify/ui";

const metadataValueMaxLength = 200;

type FormattedMetadataValue = {
  displayValue: string;
  fullValue?: string;
};

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
    return (
      <Card>
        <CardContent className="py-6 text-sm text-muted-foreground">{emptyMessage}</CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader className="space-y-2">
        <div className="flex items-center justify-between gap-3">
          <CardTitle className="text-base">Activity timeline</CardTitle>
          <Badge variant="secondary">{events.length} events</Badge>
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        <ScrollArea className="max-h-96 pr-3">
          <ol className="space-y-4">
            {events.map((event, index) => {
              const Icon = actionIcons[event.action] ?? actionIcons.default ?? FileClock;
              const actorName = event.actor?.name ?? "System";
              const formattedTime = formatOccurredAt(event.occurredAt);
              const metadataEntries = event.metadata
                ? Object.entries(event.metadata).filter(([, value]) => value !== undefined && value !== null)
                : [];

              return (
                <li key={event.id} className="space-y-4">
                  <div className="flex gap-3">
                    <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md border bg-background">
                      <Icon className="h-4 w-4" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1 space-y-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-medium">{event.message}</p>
                        <Badge variant="outline" className="capitalize">
                          {formatActionLabel(event.action)}
                        </Badge>
                      </div>
                      <p className="text-sm text-muted-foreground">
                        {actorName} · {formattedTime}
                      </p>
                      {event.targetDisplay ? (
                        <p className="text-xs text-muted-foreground">{event.targetDisplay}</p>
                      ) : null}
                      {metadataEntries.length > 0 ? (
                        <dl className="grid gap-x-3 gap-y-1 text-xs sm:grid-cols-[auto_minmax(0,1fr)]">
                          {metadataEntries.map(([key, value]) => {
                            const formattedValue = formatMetadataValue(value);

                            return (
                              <div key={key} className="contents">
                                <dt className="font-medium text-muted-foreground">{key}</dt>
                                <dd className="min-w-0 break-words" title={formattedValue.fullValue}>
                                  {formattedValue.displayValue}
                                </dd>
                              </div>
                            );
                          })}
                        </dl>
                      ) : null}
                    </div>
                  </div>
                  {index < events.length - 1 ? <Separator /> : null}
                </li>
              );
            })}
          </ol>
        </ScrollArea>
      </CardContent>
    </Card>
  );
}

function formatOccurredAt(occurredAt: string) {
  const parsedDate = new Date(occurredAt);

  return Number.isNaN(parsedDate.getTime()) ? "Unknown date" : parsedDate.toLocaleString();
}

function formatActionLabel(action: string) {
  return action.replaceAll(".", " ").replaceAll("_", " ");
}

function formatMetadataValue(value: unknown): FormattedMetadataValue {
  if (
    value === null ||
    typeof value === "string" ||
    typeof value === "number" ||
    typeof value === "boolean" ||
    typeof value === "bigint"
  ) {
    return { displayValue: String(value) };
  }

  try {
    return truncateMetadataValue(JSON.stringify(value));
  } catch {
    return truncateMetadataValue(String(value));
  }
}

function truncateMetadataValue(value: string): FormattedMetadataValue {
  if (value.length <= metadataValueMaxLength) {
    return { displayValue: value };
  }

  return {
    displayValue: `${value.slice(0, metadataValueMaxLength - 3)}...`,
    fullValue: value,
  };
}
