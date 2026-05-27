import { Badge } from "@cognify/ui";
import type { ProcurementCalendarSummary } from "@cognify/api-client/schemas";

export function ProcurementCalendarSummaryStrip({ summary }: { summary: ProcurementCalendarSummary }) {
  const items = [
    { label: "Total", value: summary.total },
    { label: "Overdue", value: summary.byStatus.overdue },
    { label: "Due soon", value: summary.byStatus.dueSoon },
    { label: "Scheduled", value: summary.byStatus.scheduled },
    { label: "Completed", value: summary.byStatus.completed },
  ];

  return (
    <section aria-label="Calendar summary" className="grid gap-3 border-y py-3 md:grid-cols-5">
      {items.map((item) => (
        <div key={item.label} className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
          <span className="text-sm text-muted-foreground">{item.label}</span>
          <Badge variant={item.value > 0 ? "secondary" : "outline"}>{item.value}</Badge>
        </div>
      ))}
    </section>
  );
}
