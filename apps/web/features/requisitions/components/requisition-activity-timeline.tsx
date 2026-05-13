import { CheckCircle2, FileClock, Send } from "lucide-react";
import type { AuditEvent } from "@cognify/api-client/schemas";
import { ActivityTimeline } from "@/components/workflow/activity-timeline";

export function RequisitionActivityTimeline({ events }: { events: AuditEvent[] }) {
  return (
    <ActivityTimeline
      events={events}
      actionIcons={{
        "requisition.submitted": Send,
        "requisition.updated": CheckCircle2,
        default: FileClock,
      }}
    />
  );
}
