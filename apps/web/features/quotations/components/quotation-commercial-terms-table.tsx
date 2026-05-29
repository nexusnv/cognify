import type {
  QuotationComparisonCommercialTerm,
  QuotationComparisonVendor,
} from "@cognify/api-client/schemas";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";

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
        <Table className="min-w-full text-sm">
          <TableHeader className="bg-muted/40 text-left">
            <TableRow>
              <TableHead className="min-w-48 px-4 py-3 font-medium">Term</TableHead>
              {vendors.map((vendor) => (
                <TableHead key={vendor.vendorId} className="min-w-48 px-4 py-3 font-medium">
                  {vendor.vendorName}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {terms.map((term) => (
              <TableRow key={term.id}>
                <TableCell className="px-4 py-3 text-left font-medium">{term.label}</TableCell>
                {vendors.map((vendor) => {
                  const value = term.vendorValues.find((entry) => entry.vendorId === vendor.vendorId);

                  return (
                    <TableCell key={vendor.vendorId} className="px-4 py-3">
                      <span>{value?.value ?? "Not available"}</span>
                      {value?.readiness === "normalization_required" ? (
                        <span className="mt-1 block text-xs text-amber-700">Normalization required</span>
                      ) : null}
                    </TableCell>
                  );
                })}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </section>
  );
}
