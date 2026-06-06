import Link from "next/link";
import { Button, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import type { RfqScorecard } from "@cognify/api-client/schemas";

export function RfqScorecardComparisonContext({ scorecard }: { scorecard: RfqScorecard }) {
  return (
    <Card aria-label="Comparison context">
      <CardHeader>
        <CardTitle className="text-base">Comparison context</CardTitle>
      </CardHeader>
      <CardContent>
      <dl className="grid gap-3 text-sm">
        <div>
          <dt className="text-muted-foreground">Responses</dt>
          <dd>{scorecard.comparisonContext.readiness?.responseCount ?? 0}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Approved normalizations</dt>
          <dd>{scorecard.comparisonContext.readiness?.approvedNormalizationCount ?? 0}</dd>
        </div>
      </dl>
      <Button asChild variant="outline" className="mt-4">
        <Link href={scorecard.comparisonContext.comparisonPath}>View comparison context</Link>
      </Button>
      </CardContent>
    </Card>
  );
}
