import { Badge } from "@cognify/ui";
import type { ApprovalPolicyStatus, ApprovalPolicyVersionStatus } from "../types/approval-view-model";

export function ApprovalStatusBadge({
  status,
}: {
  status: ApprovalPolicyStatus | ApprovalPolicyVersionStatus;
}) {
  return <Badge variant={status === "active" || status === "published" ? "default" : "secondary"}>{status}</Badge>;
}
