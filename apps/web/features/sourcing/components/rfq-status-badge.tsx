import { CircleCheck, CircleDot, CircleX } from "lucide-react";
import { StatusBadge } from "@/components/ui/workflow-state/status-badge";
import type { WorkflowStateConfig } from "@/components/ui/workflow-state/workflow-state";
import type { RfqStatus } from "../types/rfq-view-model";

const rfqStatusConfig = {
  draft: {
    label: "Draft",
    description: "The RFQ draft can still be edited by an authorized buyer or admin.",
    tone: "draft",
    icon: CircleDot,
  },
  open: {
    label: "Open",
    description: "The RFQ is active and open for response handling.",
    tone: "success",
    icon: CircleCheck,
  },
  cancelled: {
    label: "Cancelled",
    description: "The RFQ draft is terminal and can no longer be edited.",
    tone: "danger",
    icon: CircleX,
  },
} satisfies WorkflowStateConfig<RfqStatus>;

export function RfqStatusBadge({
  status,
  size = "default",
}: {
  status: RfqStatus;
  size?: "default" | "compact";
}) {
  return <StatusBadge status={status} config={rfqStatusConfig} size={size} />;
}
