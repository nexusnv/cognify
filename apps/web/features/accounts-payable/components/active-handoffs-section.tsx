"use client";

import { Badge, Card, CardContent, CardHeader, CardTitle, Skeleton } from "@cognify/ui";
import type { ApPaymentHandoff } from "@cognify/api-client/schemas";
import { useApPaymentHandoffs } from "../hooks/use-payment-handoffs";

const statusLabels: Record<string, string> = {
  draft: "Draft",
  ready: "Ready",
  exported: "Exported",
};

const statusVariants: Record<string, "secondary" | "default" | "outline"> = {
  draft: "secondary",
  ready: "default",
  exported: "outline",
};

type HandoffWithNumber = ApPaymentHandoff & { number?: string };

function formatCurrency(value: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value);
  } catch {
    return `${currency} ${value.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }
}

export function ActiveHandoffsSection() {
  const { data, isLoading, isError } = useApPaymentHandoffs();
  const handoffs = (data?.handoffs as HandoffWithNumber[] | undefined) ?? [];

  const activeHandoffs = handoffs.filter((h) => h.status !== "cancelled");

  if (isLoading) {
    return (
      <div className="space-y-4">
        <h2 className="text-lg font-semibold">Active handoffs</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <Card key={i}>
              <CardHeader className="pb-2">
                <Skeleton className="h-5 w-32" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-4 w-20 mt-2" />
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="space-y-4">
        <h2 className="text-lg font-semibold">Active handoffs</h2>
        <Card>
          <CardContent className="py-6 text-center text-sm text-muted-foreground">
            Failed to load active handoffs.
          </CardContent>
        </Card>
      </div>
    );
  }

  if (activeHandoffs.length === 0) {
    return (
      <div className="space-y-4">
        <h2 className="text-lg font-semibold">Active handoffs</h2>
        <Card>
          <CardContent className="py-6 text-center text-sm text-muted-foreground">
            No active payment handoffs.
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold">Active handoffs</h2>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {activeHandoffs.map((handoff) => (
          <Card key={handoff.id}>
            <CardHeader className="pb-2">
              <div className="flex items-center justify-between gap-2">
                <CardTitle className="text-sm font-mono truncate">
                  {handoff.number ?? handoff.id}
                </CardTitle>
                <Badge variant={statusVariants[handoff.status] ?? "secondary"}>
                  {statusLabels[handoff.status] ?? handoff.status}
                </Badge>
              </div>
            </CardHeader>
            <CardContent>
              <div className="space-y-1 text-sm">
                <p className="text-muted-foreground">
                  {handoff.invoiceCount} invoice
                  {handoff.invoiceCount !== 1 ? "s" : ""}
                </p>
                <p className="font-medium">
                  {formatCurrency(
                    Number.parseFloat(handoff.totalAmount),
                    handoff.currency,
                  )}
                </p>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
