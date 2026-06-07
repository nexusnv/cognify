import Link from "next/link";
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@cognify/ui";
import type { ProcurementCalendarEventViewModel } from "../types/procurement-calendar-view-model";

export function ProcurementCalendarEventDetail({
  event,
}: {
  event: ProcurementCalendarEventViewModel | null;
}) {
  if (!event) {
    return (
      <Card aria-label="Event detail">
        <CardHeader>
          <CardTitle role="heading" aria-level={2}>
            Event detail
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            Select an event to inspect its source metadata.
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Dialog>
      <Card aria-label="Event detail">
        <EventDetailContent event={event} />
      </Card>
      <DialogContent aria-label="Selected event detail">
        <DialogHeader>
          <DialogTitle>{event.title}</DialogTitle>
          {event.description ? <DialogDescription>{event.description}</DialogDescription> : null}
        </DialogHeader>
        <EventMetadata event={event} />
        {event.record ? (
          <Button asChild variant="outline">
            <Link href={event.record.href}>Open source</Link>
          </Button>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

function EventDetailContent({ event }: { event: ProcurementCalendarEventViewModel }) {
  return (
    <>
      <CardHeader className="border-b">
        <CardTitle role="heading" aria-level={2}>
          {event.title}
        </CardTitle>
        <div className="flex flex-wrap gap-2">
          <Badge variant="secondary">{event.statusLabel}</Badge>
          <Badge variant="outline">{event.sourceTypeLabel}</Badge>
        </div>
        {event.description ? (
          <p className="text-sm text-muted-foreground">{event.description}</p>
        ) : null}
      </CardHeader>
      <CardContent className="space-y-4">
        <EventMetadata event={event} />
        <div className="flex flex-wrap gap-2">
          <DialogTrigger asChild>
            <Button variant="secondary">Open details</Button>
          </DialogTrigger>
          {event.record ? (
            <Button asChild variant="outline">
              <Link href={event.record.href}>Open source</Link>
            </Button>
          ) : null}
        </div>
      </CardContent>
    </>
  );
}

function EventMetadata({ event }: { event: ProcurementCalendarEventViewModel }) {
  return (
    <dl className="grid gap-3 text-sm">
      <div className="grid gap-1">
        <dt className="text-muted-foreground">Date</dt>
        <dd>{event.dateKey}</dd>
      </div>
      <div className="grid gap-1">
        <dt className="text-muted-foreground">Time</dt>
        <dd>{event.timeLabel}</dd>
      </div>
      <div className="grid gap-1">
        <dt className="text-muted-foreground">Source</dt>
        <dd>{event.sourceTypeLabel}</dd>
      </div>
      <div className="grid gap-1">
        <dt className="text-muted-foreground">Source record ID</dt>
        <dd>{event.sourceId}</dd>
      </div>
      <div className="grid gap-1">
        <dt className="text-muted-foreground">Source label</dt>
        <dd>{event.sourceLabel}</dd>
      </div>
      {event.record ? (
        <>
          <div className="grid gap-1">
            <dt className="text-muted-foreground">Record type</dt>
            <dd>{event.record.type}</dd>
          </div>
          <div className="grid gap-1">
            <dt className="text-muted-foreground">Record label</dt>
            <dd>{event.record.label}</dd>
          </div>
        </>
      ) : null}
    </dl>
  );
}
