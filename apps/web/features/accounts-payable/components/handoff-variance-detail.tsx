"use client";

import { useMemo } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import type { ApPaymentHandoff } from "@cognify/api-client/schemas";

type HandoffWithNumber = ApPaymentHandoff & { number?: string };

interface HandoffVarianceDetailProps {
  handoff: HandoffWithNumber;
}

export function HandoffVarianceDetail({ handoff }: HandoffVarianceDetailProps) {
  const varianceAmount = (handoff as unknown as { varianceAmount?: string }).varianceAmount;
  const expectedTotal = (handoff as unknown as { expectedTotal?: string }).expectedTotal;
  const allocatedTotal = (handoff as unknown as { allocatedTotal?: string }).allocatedTotal;

  const hasVariance = useMemo(() => {
    if (varianceAmount) {
      const v = Number.parseFloat(varianceAmount);
      return !Number.isNaN(v) && v !== 0;
    }
    if (expectedTotal && allocatedTotal) {
      const exp = Number.parseFloat(expectedTotal);
      const alloc = Number.parseFloat(allocatedTotal);
      return !Number.isNaN(exp) && !Number.isNaN(alloc) && exp !== alloc;
    }
    return false;
  }, [varianceAmount, expectedTotal, allocatedTotal]);

  if (!hasVariance) {
    return null;
  }

  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-sm">Variance summary</CardTitle>
      </CardHeader>
      <CardContent className="space-y-2 text-sm">
        {varianceAmount && (
          <div className="flex justify-between">
            <span className="text-muted-foreground">Variance amount</span>
            <span className="font-mono font-medium">
              {formatCurrency(Number.parseFloat(varianceAmount), handoff.currency)}
            </span>
          </div>
        )}
        {expectedTotal && (
          <div className="flex justify-between">
            <span className="text-muted-foreground">Expected total</span>
            <span className="font-mono">
              {formatCurrency(Number.parseFloat(expectedTotal), handoff.currency)}
            </span>
          </div>
        )}
        {allocatedTotal && (
          <div className="flex justify-between">
            <span className="text-muted-foreground">Allocated total</span>
            <span className="font-mono">
              {formatCurrency(Number.parseFloat(allocatedTotal), handoff.currency)}
            </span>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

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
