import type {
  QuotationComparisonCommercialTerm,
  QuotationComparisonVendor,
} from "@cognify/api-client/schemas";

export function QuotationCommercialTermsTable({
  terms,
  vendors,
}: {
  terms: QuotationComparisonCommercialTerm[];
  vendors: QuotationComparisonVendor[];
}) {
  return (
    <section id="commercial-terms" className="rounded-md border">
      <div className="border-b p-4">
        <h2 className="text-base font-semibold">Commercial terms</h2>
        <p className="text-sm text-muted-foreground">Terms are sourced from approved normalization fields where available.</p>
      </div>
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y text-sm">
          <thead className="bg-muted/40 text-left">
            <tr>
              <th scope="col" className="min-w-48 px-4 py-3 font-medium">Term</th>
              {vendors.map((vendor) => (
                <th key={vendor.vendorId} scope="col" className="min-w-48 px-4 py-3 font-medium">
                  {vendor.vendorName}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y">
            {terms.map((term) => (
              <tr key={term.id}>
                <th scope="row" className="px-4 py-3 text-left font-medium">{term.label}</th>
                {vendors.map((vendor) => {
                  const value = term.vendorValues.find((entry) => entry.vendorId === vendor.vendorId);

                  return (
                    <td key={vendor.vendorId} className="px-4 py-3">
                      <span>{value?.value ?? "Not available"}</span>
                      {value?.readiness === "normalization_required" ? (
                        <span className="mt-1 block text-xs text-amber-700">Normalization required</span>
                      ) : null}
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
