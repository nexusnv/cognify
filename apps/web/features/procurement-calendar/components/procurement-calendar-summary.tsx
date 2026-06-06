import { Badge, Card, CardHeader, CardTitle, Progress } from "@cognify/ui";
import type { ProcurementCalendarSummary } from "@cognify/api-client/schemas";
import { StatusCard } from "@/components/ui/status-card";

export function ProcurementCalendarSummaryStrip({ summary }: { summary: ProcurementCalendarSummary }) {
  const items = [
    { label: "Total", value: summary.total },
    { label: "Overdue", value: summary.byStatus.overdue },
    { label: "Due soon", value: summary.byStatus.dueSoon },
    { label: "Scheduled", value: summary.byStatus.scheduled },
    { label: "Completed", value: summary.byStatus.completed },
  ];

  return (
    <section aria-label="Calendar summary" className="space-y-4">
      <Card>
        <CardHeader className="gap-2">
          <CardTitle className="text-base">Calendar summary</CardTitle>
          <div className="space-y-2">
            <div className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
              <span>Completion</span>
              <Badge variant="secondary">{completionLabel(summary)}</Badge>
            </div>
            <Progress value={completionValue(summary)} />
          </div>
        </CardHeader>
      </Card>
      <div className="grid gap-3 md:grid-cols-5">
        {items.map((item) => (
          <StatusCard
            key={item.label}
            label={item.label}
            value={item.value}
            badge={item.value > 0 ? "Active" : "None"}
          />
        ))}
      </div>
    </section>
  );
}

function completionValue(summary: ProcurementCalendarSummary) {
  if (summary.total === 0) return 0;
  return Math.round((summary.byStatus.completed / summary.total) * 100);
}

function completionLabel(summary: ProcurementCalendarSummary) {
  return `${completionValue(summary)}%`;
}
