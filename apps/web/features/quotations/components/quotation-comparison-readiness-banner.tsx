import type { QuotationComparisonReadiness } from "@cognify/api-client/schemas";
import { Alert, AlertDescription, AlertTitle, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";

export function QuotationComparisonReadinessBanner({
  readiness,
}: {
  readiness: QuotationComparisonReadiness;
}) {
  return (
    <Card id="overview">
      <CardHeader>
        <CardTitle className="text-base">Comparison readiness</CardTitle>
      </CardHeader>
      <CardContent>
      <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <Metric label="Responses" value={String(readiness.responseCount)} />
        <Metric label="Approved normalization" value={String(readiness.approvedNormalizationCount)} />
        <Metric label="Pending normalization" value={`${readiness.pendingNormalizationCount} pending normalization`} />
        <Metric label="Missing responses" value={String(readiness.missingResponseCount)} />
      </dl>
      {readiness.mixedCurrency ? (
        <Alert className="mt-4 border-amber-300 bg-amber-50 text-amber-950">
          <AlertTitle>Mixed currencies</AlertTitle>
          <AlertDescription>Values are shown as quoted. No conversion has been applied in this workspace.</AlertDescription>
        </Alert>
      ) : null}
      </CardContent>
    </Card>
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
