import { CheckCircle2, CircleAlert } from "lucide-react";
import type { RequisitionFormValues } from "../types/requisition-view-model";
import { buildSubmissionChecklist, calculateEstimatedTotal, formatMoney } from "../utils/requisition-totals";

export function SubmissionChecklist({ values }: { values: RequisitionFormValues }) {
  const checklist = buildSubmissionChecklist(values);
  const totals = calculateEstimatedTotal(values.lineItems);

  return (
    <aside className="space-y-4 rounded-md border bg-card p-4">
      <div>
        <h2 className="text-sm font-semibold">Submission checklist</h2>
        <p className="mt-1 text-sm text-muted-foreground">Complete these items before sending for review.</p>
      </div>
      <ul className="space-y-2">
        {checklist.map((item) => {
          const Icon = item.complete ? CheckCircle2 : CircleAlert;

          return (
            <li key={item.id} className="flex items-start gap-2 text-sm">
              <Icon className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
              <span>{item.label}</span>
            </li>
          );
        })}
      </ul>
      <div className="border-t pt-3">
        <p className="text-xs font-medium uppercase text-muted-foreground">Estimated total</p>
        <p className="mt-1 font-mono text-xl font-semibold tabular-nums">
          {formatMoney(totals.estimatedTotal, totals.currency)}
        </p>
      </div>
    </aside>
  );
}
