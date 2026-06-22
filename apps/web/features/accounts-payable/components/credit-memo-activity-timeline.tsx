import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";

export function CreditMemoActivityTimeline() {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Activity</CardTitle>
      </CardHeader>
      <CardContent className="text-sm text-muted-foreground">
        Activity timeline coming in P1-50.
      </CardContent>
    </Card>
  );
}
