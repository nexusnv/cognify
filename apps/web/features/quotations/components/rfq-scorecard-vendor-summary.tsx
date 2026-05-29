import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import type { RfqScorecard } from "@cognify/api-client/schemas";

export function RfqScorecardVendorSummary({ scorecard }: { scorecard: RfqScorecard }) {
  return (
    <section className="grid gap-3 md:grid-cols-2" aria-label="Vendor summaries">
      {scorecard.vendors.map((vendor) => (
        <Card key={vendor.vendorId}>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">{vendor.vendorName}</CardTitle>
          </CardHeader>
          <CardContent>
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <dt className="text-muted-foreground">Weighted total</dt>
              <dd className="text-lg font-semibold">{vendor.weightedTotal}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Missing required</dt>
              <dd>{vendor.missingRequiredCount}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Total amount</dt>
              <dd>{vendor.currency && vendor.totalAmount != null ? `${vendor.currency} ${vendor.totalAmount}` : "No total"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Lead time</dt>
              <dd>{vendor.leadTimeDays != null ? `${vendor.leadTimeDays} days` : "No lead time"}</dd>
            </div>
          </dl>
          </CardContent>
        </Card>
      ))}
    </section>
  );
}
