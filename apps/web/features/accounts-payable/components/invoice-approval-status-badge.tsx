import { Badge } from "@cognify/ui";
import type { ApprovalStatus } from "../hooks/use-invoice-approval";

const labels: Record<NonNullable<ApprovalStatus>, string> = {
  stp_approved: "STP approved",
  approved: "approved",
  rejected: "rejected",
  changes_requested: "changes requested",
  in_approval: "in approval",
};

const variants: Record<NonNullable<ApprovalStatus>, "default" | "secondary" | "destructive" | "outline"> = {
  stp_approved: "default",
  approved: "default",
  rejected: "destructive",
  changes_requested: "secondary",
  in_approval: "outline",
};

export function InvoiceApprovalStatusBadge({ status }: { status: ApprovalStatus }) {
  if (!status) {
    return null;
  }

  return (
    <Badge variant={variants[status]}>
      {labels[status]}
    </Badge>
  );
}
