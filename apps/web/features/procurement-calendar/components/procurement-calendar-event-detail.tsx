"use client";

import Link from "next/link";
import type { ProcurementCalendarEventViewModel } from "../types/procurement-calendar-view-model";
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  Separator,
} from "@cognify/ui";

export function ProcurementCalendarEventDetail({
  event,
  open,
  onOpenChange,
}: {
  event: ProcurementCalendarEventViewModel | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  return (
    <>
      <Card aria-label="Selected event summary">
        <CardHeader className="gap-2">
          <CardTitle className="text-base">Selected event</CardTitle>
          <CardDescription>
            {event ? "Open the event details to inspect source metadata." : "Select an event to inspect its source metadata."}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {event ? (
            <>
              <div className="space-y-2">
                <p className="text-sm font-medium">{event.title}</p>
                <div className="flex flex-wrap gap-2">
                  <Badge variant="secondary">{event.statusLabel}</Badge>
                  <Badge variant="outline">{event.sourceTypeLabel}</Badge>
                </div>
              </div>
              <Separator />
              <dl className="grid gap-2 text-sm">
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-muted-foreground">Date</dt>
                  <dd>{event.dateKey}</dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-muted-foreground">Time</dt>
                  <dd>{event.timeLabel}</dd>
                </div>
              </dl>
              <Button type="button" className="w-full" onClick={() => onOpenChange(true)}>
                View details
              </Button>
            </>
          ) : (
            <p className="text-sm text-muted-foreground">
              No event is selected.
            </p>
          )}
        </CardContent>
      </Card>

      <Dialog open={open && event !== null} onOpenChange={onOpenChange}>
        <DialogContent className="max-h-[85vh] overflow-hidden sm:max-w-2xl">
          {event ? (
            <>
              <DialogHeader className="space-y-3 text-left">
                <DialogTitle>{event.title}</DialogTitle>
                <DialogDescription>
                  {event.dateKey} · {event.timeLabel} · {event.sourceTypeLabel}
                </DialogDescription>
                <div className="flex flex-wrap gap-2">
                  <Badge variant="secondary">{event.statusLabel}</Badge>
                  <Badge variant="outline">{event.sourceLabel}</Badge>
                </div>
              </DialogHeader>

              <div className="space-y-4 overflow-y-auto pr-1">
                {event.description ? (
                  <Card>
                    <CardContent className="pt-6">
                      <p className="text-sm text-muted-foreground">{event.description}</p>
                    </CardContent>
                  </Card>
                ) : null}

                <Card>
                  <CardContent className="pt-6">
                    <dl className="grid gap-3 text-sm">
                      <DetailRow label="Date" value={event.dateKey} />
                      <DetailRow label="Time" value={event.timeLabel} />
                      <DetailRow label="Source" value={event.sourceTypeLabel} />
                      <DetailRow label="Source record ID" value={event.sourceId} />
                      <DetailRow label="Source label" value={event.sourceLabel} />
                      {event.record ? (
                        <>
                          <DetailRow label="Record type" value={event.record.type} />
                          <DetailRow label="Record label" value={event.record.label} />
                        </>
                      ) : null}
                    </dl>
                  </CardContent>
                </Card>
              </div>

              {event.record ? (
                <Button asChild className="w-full">
                  <Link href={event.record.href}>Open source</Link>
                </Button>
              ) : null}
            </>
          ) : null}
        </DialogContent>
      </Dialog>
    </>
  );
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="grid gap-1">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="break-words">{value}</dd>
    </div>
  );
}
