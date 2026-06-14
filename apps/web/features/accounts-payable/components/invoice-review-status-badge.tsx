import { Badge } from "@cognify/ui";

const labels: Record<string, string> = {
  captured: "captured",
  in_review: "in_review",
  needs_information: "needs_information",
  reviewed: "reviewed",
};

export function InvoiceReviewStatusBadge({ status }: { status: string }) {
  return (
    <Badge variant={status === "needs_information" ? "destructive" : "secondary"}>
      {labels[status] ?? status}
    </Badge>
  );
}
