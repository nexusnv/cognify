import { Badge } from "@cognify/ui";
import type { ApprovalPolicyStatus, ApprovalPolicyVersionStatus } from "../types/approval-view-model";

export function ApprovalStatusBadge({
  status,
}: {
  status: ApprovalPolicyStatus | ApprovalPolicyVersionStatus | string;
}) {
  const positive = ["active", "published", "approved"].includes(status);
  const destructive = ["rejected", "cancelled", "changes_requested"].includes(status);

  return (
    <Badge variant={positive ? "default" : destructive ? "destructive" : "secondary"}>
      {status.replaceAll("_", " ")}
    </Badge>
  );
}
