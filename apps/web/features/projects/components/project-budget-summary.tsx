import type { ReactNode } from "react";
import { Alert, AlertDescription, AlertTitle, Badge, Card, CardContent, CardHeader, CardTitle, Progress } from "@cognify/ui";
import type { ProjectSummary } from "../types/project-view-model";

export function ProjectBudgetSummary({
  budgetAmount,
  currency,
  summary,
}: {
  budgetAmount: number | null | undefined;
  currency: string;
  summary: ProjectSummary;
}) {
  const budget = budgetAmount ?? 0;
  const linked = summary.estimatedRequisitionTotal;
  const remaining = budget - linked;

  const linkedPercent = budget > 0 ? Math.min((linked / budget) * 100, 100) : 0;

  return (
    <Card id="budget">
      <CardHeader>
        <CardTitle>Budget summary</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-3">
          <Metric label="Budget amount" value={formatMoney(budget, currency)} />
          <Metric label="Linked requisition total" value={formatMoney(linked, currency)} />
          <Metric
            label="Remaining budget"
            value={
              <span className={remaining < 0 ? "text-destructive" : undefined}>
                {formatMoney(remaining, currency)}
              </span>
            }
          />
        </div>
        <div className="space-y-2">
          <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
            <span>Linked spend</span>
            <Badge variant={remaining < 0 ? "destructive" : "secondary"}>
              {Math.round(linkedPercent)}%
            </Badge>
          </div>
          <Progress value={linkedPercent} />
        </div>
        {remaining < 0 ? (
          <Alert variant="destructive">
            <AlertTitle>Budget exceeded</AlertTitle>
            <AlertDescription>
              Linked requisitions currently exceed the budget. This workspace does not enforce
              budget limits yet.
            </AlertDescription>
          </Alert>
        ) : null}
      </CardContent>
    </Card>
  );
}

function Metric({ label, value }: { label: string; value: string | ReactNode }) {
  return (
    <Card>
      <CardContent className="space-y-1 p-3">
        <p className="text-xs uppercase text-muted-foreground">{label}</p>
        <p className="font-mono text-sm font-medium tabular-nums">{value}</p>
      </CardContent>
    </Card>
  );
}

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(amount);
}
