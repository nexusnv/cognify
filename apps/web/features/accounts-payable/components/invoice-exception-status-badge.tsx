"use client";

import { Badge } from "@cognify/ui";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";

interface InvoiceExceptionStatusBadgeProps {
  status: SupplierInvoiceException["status"];
}

const variantMap: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
  open: "default",
  resolved: "secondary",
  escalated: "destructive",
};

const labelMap: Record<string, string> = {
  open: "Open",
  resolved: "Resolved",
  escalated: "Escalated",
};

export function InvoiceExceptionStatusBadge({ status }: InvoiceExceptionStatusBadgeProps) {
  return (
    <Badge variant={variantMap[status] ?? "outline"}>
      {labelMap[status] ?? status}
    </Badge>
  );
}
