import { AlertTriangle, Ban, CheckCircle2, CircleDot, Clock3, CircleX } from "lucide-react";
import { StatusBadge } from "@/components/workflow/status-badge";
import type { WorkflowStateConfig } from "@/components/workflow/workflow-state";
import type { RequisitionStatus } from "../types/requisition-view-model";

const requisitionStatusConfig = {
  draft: {
    label: "Draft",
    description: "The requester can still edit and submit this requisition.",
    tone: "draft",
    icon: CircleDot,
  },
  submitted: {
    label: "Submitted",
    description: "The requisition has been submitted for procurement review.",
    tone: "success",
    icon: CheckCircle2,
  },
  pending_approval: {
    label: "Pending approval",
    description: "The requisition is waiting for an approval decision.",
    tone: "info",
    icon: Clock3,
  },
  changes_requested: {
    label: "Changes requested",
    description: "The requisition needs requester updates before it can move forward.",
    tone: "warning",
    icon: AlertTriangle,
  },
  withdrawn: {
    label: "Withdrawn",
    description: "The requester stopped this requisition.",
    tone: "locked",
    icon: Ban,
  },
  cancelled: {
    label: "Cancelled",
    description: "An administrator cancelled this requisition.",
    tone: "danger",
    icon: CircleX,
  },
} satisfies WorkflowStateConfig<RequisitionStatus>;

export function RequisitionStatusBadge({
  status,
  size,
}: {
  status: RequisitionStatus;
  size?: "default" | "compact";
}) {
  return <StatusBadge status={status} config={requisitionStatusConfig} size={size} />;
}
