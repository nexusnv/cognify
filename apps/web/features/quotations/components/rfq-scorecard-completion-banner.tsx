import { Badge, Card, CardContent } from "@cognify/ui";
import type { RfqScorecard } from "@cognify/api-client/schemas";

export function RfqScorecardCompletionBanner({ scorecard }: { scorecard: RfqScorecard }) {
  const completed = scorecard.scorecard.status === "completed";
  const missing = scorecard.completion.missingRequiredScoreCount;
  const ready = missing === 0;

  return (
    <Card aria-label="Scoring completion">
      <CardContent className="pt-6">
      <div className="flex flex-wrap items-center gap-2">
        <Badge variant={completed ? "default" : ready ? "secondary" : "outline"}>
          {completed ? "Completed" : ready ? "Ready to complete" : "Incomplete"}
        </Badge>
        <span className="text-sm text-muted-foreground">
          {scorecard.completion.completedRequiredScoreCount} of {scorecard.completion.requiredScoreCount} required scores complete
        </span>
      </div>
      {missing > 0 ? (
        <p className="mt-2 text-sm text-red-700">{missing} missing required scores remain.</p>
      ) : (
        <p className="mt-2 text-sm text-muted-foreground">Required scoring is complete for scoreable vendors.</p>
      )}
      </CardContent>
    </Card>
  );
}
