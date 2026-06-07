import type { SystemStatusState } from "@cognify/api-client/schemas";
import { Badge } from "@cognify/ui";

const badgeStyles: Record<SystemStatusState, string> = {
  ok: "border-emerald-300 bg-emerald-50 text-emerald-950",
  warning: "border-amber-300 bg-amber-50 text-amber-950",
  error: "border-red-300 bg-red-50 text-red-950",
};

const badgeLabels: Record<SystemStatusState, string> = {
  ok: "Healthy",
  warning: "Warning",
  error: "Needs attention",
};

export function SystemStatusBadge({ status }: { status: SystemStatusState }) {
  return <Badge className={badgeStyles[status]}>{badgeLabels[status]}</Badge>;
}
