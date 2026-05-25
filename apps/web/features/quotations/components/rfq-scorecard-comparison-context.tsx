import Link from "next/link";
import type { RfqScorecard } from "@cognify/api-client/schemas";

export function RfqScorecardComparisonContext({ scorecard }: { scorecard: RfqScorecard }) {
  return (
    <section className="rounded-md border p-4" aria-label="Comparison context">
      <h2 className="text-base font-semibold">Comparison context</h2>
      <dl className="mt-3 grid gap-3 text-sm">
        <div>
          <dt className="text-muted-foreground">Responses</dt>
          <dd>{scorecard.comparisonContext.readiness?.responseCount ?? 0}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Approved normalizations</dt>
          <dd>{scorecard.comparisonContext.readiness?.approvedNormalizationCount ?? 0}</dd>
        </div>
      </dl>
      <Link className="mt-4 inline-flex text-sm font-medium underline-offset-4 hover:underline" href={scorecard.comparisonContext.comparisonPath}>
        Back to comparison
      </Link>
    </section>
  );
}
