import { Badge } from "@cognify/ui";
import type { SourcingIntakeStatus } from "../types/sourcing-view-model";

const labels: Record<SourcingIntakeStatus, string> = {
  open: "Open",
  in_review: "In review",
  clarification_requested: "Clarification requested",
  ready_for_rfq: "Ready for RFQ",
  direct_award_recorded: "Direct award recorded",
  closed: "Closed",
};

export function SourcingIntakeStatusBadge({
  status,
  size = "default",
}: {
  status: SourcingIntakeStatus;
  size?: "default" | "compact";
}) {
  const variant = status === "clarification_requested"
    ? "outline"
    : status === "ready_for_rfq"
      ? "default"
      : status === "closed"
        ? "secondary"
        : "secondary";

  return (
    <Badge variant={variant} className={size === "compact" ? "px-2 py-0.5 text-xs" : undefined}>
      {labels[status]}
    </Badge>
  );
}
