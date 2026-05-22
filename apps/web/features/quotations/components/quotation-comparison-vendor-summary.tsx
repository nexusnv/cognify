import { Badge } from "@cognify/ui";
import type { QuotationComparisonVendor } from "@cognify/api-client/schemas";

export function QuotationComparisonVendorSummary({
  vendors,
}: {
  vendors: QuotationComparisonVendor[];
}) {
  return (
    <section id="vendors" className="space-y-3">
      <div>
        <h2 className="text-base font-semibold">Vendor summaries</h2>
        <p className="text-sm text-muted-foreground">Readiness and commercial highlights from approved normalization data.</p>
      </div>
      <div className="grid gap-3 lg:grid-cols-3">
        {vendors.map((vendor) => (
          <article key={vendor.vendorId} className="rounded-md border p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="font-semibold">{vendor.vendorName}</h3>
                <p className="text-xs text-muted-foreground">{vendor.quotationNumber}</p>
              </div>
              <Badge variant={vendor.readiness === "ready" ? "default" : "secondary"}>
                {vendor.readiness === "ready" ? "Ready" : "Normalization required"}
              </Badge>
            </div>
            <dl className="mt-4 grid gap-2 text-sm">
              <SummaryItem label="Total" value={formatMoney(vendor.currency, vendor.totalAmount)} />
              <SummaryItem label="Lead time" value={vendor.leadTimeDays != null ? `${vendor.leadTimeDays} days` : "Not available"} />
              <SummaryItem label="Payment" value={vendor.paymentTerms ?? "Not available"} />
              <SummaryItem label="Delivery" value={vendor.deliveryTerms ?? "Not available"} />
              <SummaryItem label="Notes" value={String(vendor.noteCount)} />
              <SummaryItem
                label="Issues"
                value={`${vendor.issueCounts.blocking} blocking, ${vendor.issueCounts.warning} warning, ${vendor.issueCounts.info} info`}
              />
            </dl>
          </article>
        ))}
      </div>
    </section>
  );
}

function SummaryItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="text-right font-medium">{value}</dd>
    </div>
  );
}

function formatMoney(currency?: string | null, value?: string | null) {
  if (!value) return "Not available";

  return currency ? `${currency} ${value}` : value;
}
