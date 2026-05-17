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
    <section id="budget" className="rounded-md border p-4">
      <h2 className="text-base font-semibold">Budget summary</h2>
      <div className="mt-3 grid gap-3 sm:grid-cols-3">
        <Metric label="Budget amount" value={formatMoney(budget, currency)} />
        <Metric label="Linked requisition total" value={formatMoney(linked, currency)} />
        <Metric label="Remaining budget" value={formatMoney(remaining, currency)} />
      </div>
      {remaining < 0 ? (
        <p className="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
          Linked requisitions currently exceed the budget. This workspace does not enforce budget
          limits yet.
        </p>
      ) : null}
    </section>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border p-3">
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
