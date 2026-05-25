import type { RfqScorecard } from "@cognify/api-client/schemas";

export function RfqScorecardVendorSummary({ scorecard }: { scorecard: RfqScorecard }) {
  return (
    <section className="grid gap-3 md:grid-cols-2" aria-label="Vendor summaries">
      {scorecard.vendors.map((vendor) => (
        <article key={vendor.vendorId} className="rounded-md border p-4">
          <h3 className="font-medium">{vendor.vendorName}</h3>
          <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
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
              <dd>{vendor.currency && vendor.totalAmount ? `${vendor.currency} ${vendor.totalAmount}` : "No total"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Lead time</dt>
              <dd>{vendor.leadTimeDays ? `${vendor.leadTimeDays} days` : "No lead time"}</dd>
            </div>
          </dl>
        </article>
      ))}
    </section>
  );
}
