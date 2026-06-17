import { Badge } from "@cognify/ui";

interface InvoiceMatchingStatusBadgeProps {
  matchingStatus: "pending" | "matched" | "mismatch" | null | undefined;
}

export function InvoiceMatchingStatusBadge({ matchingStatus }: InvoiceMatchingStatusBadgeProps) {
  if (!matchingStatus || matchingStatus === "pending") {
    return (
      <Badge variant="outline" className="text-muted-foreground">
        Pending
      </Badge>
    );
  }

  if (matchingStatus === "matched") {
    return (
      <Badge className="bg-green-100 text-green-800 hover:bg-green-100">
        Matched
      </Badge>
    );
  }

  if (matchingStatus === "mismatch") {
    return (
      <Badge className="bg-red-100 text-red-800 hover:bg-red-100">
        Mismatch
      </Badge>
    );
  }

  return null;
}
