import { Badge } from "@cognify/ui";
import type { RfqInvitationStatus } from "@cognify/api-client/schemas";

const variantByStatus: Record<RfqInvitationStatus, "default" | "secondary" | "outline" | "destructive"> = {
  pending: "secondary",
  sent: "default",
  acknowledged: "default",
  declined: "destructive",
  expired: "outline",
  cancelled: "destructive",
};

export function RfqInvitationStatusBadge({
  status,
  size = "default",
}: {
  status: RfqInvitationStatus;
  size?: "default" | "compact";
}) {
  return (
    <Badge
      variant={variantByStatus[status]}
      className={size === "compact" ? "min-h-6 px-2 py-0.5 text-[0.75rem]" : "min-h-7 px-2.5 py-0.5 text-xs"}
    >
      {status.replaceAll("_", " ")}
    </Badge>
  );
}
