import { Badge } from "@cognify/ui";
import { cn } from "@cognify/ui/lib/utils";

interface InvoiceMatchingStatusBadgeProps {
  matchingStatus: string | null | undefined;
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
      <Badge className={cn("bg-green-100 text-green-800 hover:bg-green-100")}>
        Matched
      </Badge>
    );
  }

  if (matchingStatus === "mismatch") {
    return (
      <Badge className={cn("bg-red-100 text-red-800 hover:bg-red-100")}>
        Mismatch
      </Badge>
    );
  }

  return null;
}
