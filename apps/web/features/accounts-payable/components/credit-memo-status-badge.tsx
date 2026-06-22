import { Badge } from "@cognify/ui";
import type { SupplierCreditMemoStatus } from "@cognify/api-client/schemas";

const statusStyles: Record<SupplierCreditMemoStatus, string> = {
  draft: "bg-slate-100 text-slate-800",
  pending_approval: "bg-amber-100 text-amber-800",
  approved: "bg-indigo-100 text-indigo-800",
  open: "bg-emerald-100 text-emerald-800",
  partially_applied: "bg-cyan-100 text-cyan-800",
  fully_applied: "bg-blue-100 text-blue-800",
  closed: "bg-gray-200 text-gray-700",
  voided: "bg-rose-100 text-rose-800",
};

const statusLabels: Record<SupplierCreditMemoStatus, string> = {
  draft: "Draft",
  pending_approval: "Pending approval",
  approved: "Approved",
  open: "Open",
  partially_applied: "Partially applied",
  fully_applied: "Fully applied",
  closed: "Closed",
  voided: "Voided",
};

export function CreditMemoStatusBadge({ status }: { status: SupplierCreditMemoStatus }) {
  return <Badge className={statusStyles[status] ?? "bg-gray-100"}>{statusLabels[status] ?? status}</Badge>;
}
