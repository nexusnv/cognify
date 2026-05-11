import { CheckCircle2, CircleDot, Clock3 } from "lucide-react";
import type { RequisitionStatus } from "../types/requisition-view-model";

const statusConfig = {
  draft: {
    label: "Draft",
    className: "border-amber-300 bg-amber-50 text-amber-900",
    icon: CircleDot,
  },
  submitted: {
    label: "Submitted",
    className: "border-emerald-300 bg-emerald-50 text-emerald-900",
    icon: CheckCircle2,
  },
  pending_approval: {
    label: "Pending approval",
    className: "border-blue-300 bg-blue-50 text-blue-900",
    icon: Clock3,
  },
} satisfies Record<RequisitionStatus, { label: string; className: string; icon: typeof CircleDot }>;

export function RequisitionStatusBadge({ status }: { status: RequisitionStatus }) {
  const config = statusConfig[status];
  const Icon = config.icon;

  return (
    <span
      className={`inline-flex min-h-7 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium ${config.className}`}
    >
      <Icon className="h-3.5 w-3.5" aria-hidden="true" />
      {config.label}
    </span>
  );
}
