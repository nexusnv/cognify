import {
  AtSign,
  CheckCircle2,
  CircleStop,
  CircleX,
  FileClock,
  MessageSquare,
  MessageSquareWarning,
  Send,
} from "lucide-react";
import type { AuditEvent } from "@cognify/api-client/schemas";
import { ActivityTimeline } from "@/components/ui/workflow-state/activity-timeline";

export function RequisitionActivityTimeline({ events }: { events: AuditEvent[] }) {
  return (
    <ActivityTimeline
      events={events}
      actionIcons={{
        "requisition.changes_requested": MessageSquareWarning,
        "requisition.resubmitted": Send,
        "requisition.submitted": Send,
        "requisition.updated": CheckCircle2,
        "requisition.withdrawn": CircleStop,
        "requisition.cancelled": CircleX,
        "collaboration.comment_created": MessageSquare,
        "collaboration.mentioned": AtSign,
        default: FileClock,
      }}
    />
  );
}
