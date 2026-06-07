import { Alert, AlertDescription, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
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

  return (
    <Card className="py-0">
      <CardHeader className="border-b bg-muted/30">
        <CardTitle>Budget summary</CardTitle>
      </CardHeader>
      <CardContent className="grid gap-4 py-4">
        <div className="grid gap-3 sm:grid-cols-3">
          <Metric label="Budget amount" value={formatMoney(budget, currency)} />
          <Metric label="Linked requisition total" value={formatMoney(linked, currency)} />
          <Metric label="Remaining budget" value={formatMoney(remaining, currency)} />
        </div>
        {remaining < 0 ? (
          <Alert>
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

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border bg-background p-3">
      <p className="text-xs uppercase text-muted-foreground">{label}</p>
      <p className="mt-1 font-mono text-sm font-medium tabular-nums">{value}</p>
    </div>
  );
}

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(amount);
}
