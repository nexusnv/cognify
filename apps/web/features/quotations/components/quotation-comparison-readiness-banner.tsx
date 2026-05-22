import type { QuotationComparisonReadiness } from "@cognify/api-client/schemas";

export function QuotationComparisonReadinessBanner({
  readiness,
}: {
  readiness: QuotationComparisonReadiness;
}) {
  return (
    <section id="overview" className="rounded-md border bg-muted/30 p-4">
      <h2 className="text-base font-semibold">Comparison readiness</h2>
      <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <Metric label="Responses" value={String(readiness.responseCount)} />
        <Metric label="Approved normalization" value={String(readiness.approvedNormalizationCount)} />
        <Metric label="Pending normalization" value={`${readiness.pendingNormalizationCount} pending normalization`} />
        <Metric label="Missing responses" value={String(readiness.missingResponseCount)} />
      </dl>
      {readiness.mixedCurrency ? (
        <div className="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
          <strong>Mixed currencies</strong>
          <p className="mt-1">Values are shown as quoted. No conversion has been applied in this workspace.</p>
        </div>
      ) : null}
    </section>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium">{value}</dd>
    </div>
  );
}
