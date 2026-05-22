import type {
  QuotationComparisonLineRow,
  QuotationComparisonVendor,
} from "@cognify/api-client/schemas";

export function QuotationComparisonTable({
  rows,
  vendors,
}: {
  rows: QuotationComparisonLineRow[];
  vendors: QuotationComparisonVendor[];
}) {
  return (
    <section id="line-comparison" className="rounded-md border">
      <div className="border-b p-4">
        <h2 className="text-base font-semibold">Line comparison</h2>
        <p className="text-sm text-muted-foreground">RFQ lines are compared side by side without allocating bundle pricing.</p>
      </div>
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y text-sm">
          <thead className="bg-muted/40 text-left">
            <tr>
              <th scope="col" className="min-w-56 px-4 py-3 font-medium">RFQ line</th>
              {vendors.map((vendor) => (
                <th key={vendor.vendorId} scope="col" className="min-w-64 px-4 py-3 font-medium">
                  {vendor.vendorName}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y">
            {rows.map((row) => (
              <tr key={row.rfqLineItemId} className="align-top">
                <th scope="row" className="px-4 py-4 text-left font-medium">
                  <span>{row.name ?? row.description ?? row.rfqLineItemId}</span>
                  <span className="mt-1 block text-xs font-normal text-muted-foreground">
                    {row.quantity ?? "?"} {row.unit ?? "unit"}
                  </span>
                </th>
                {vendors.map((vendor) => {
                  const cell = row.vendorCells.find((entry) => entry.vendorId === vendor.vendorId);

                  return (
                    <td key={vendor.vendorId} className="px-4 py-4">
                      {cell ? (
                        <div className="space-y-1">
                          <p className="font-medium">{cell.description ?? "No mapped quotation line"}</p>
                          <p>{formatCellValue(cell.currency, cell.lineTotal ?? cell.value)}</p>
                          {cell.bundleTotalAmount ? (
                            <p className="text-xs text-muted-foreground">Bundle total {formatCellValue(cell.currency, cell.bundleTotalAmount)}</p>
                          ) : null}
                          <p className="text-xs text-muted-foreground">{readinessLabel(cell.readiness)}</p>
                          {cell.buyerNote ? <p className="text-xs">{cell.buyerNote}</p> : null}
                        </div>
                      ) : (
                        <span className="text-muted-foreground">No response</span>
                      )}
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

function readinessLabel(readiness: string) {
  if (readiness === "normalization_required") return "Normalization required";
  if (readiness === "unmapped") return "Unmapped";

  return "Ready";
}

function formatCellValue(currency?: string | null, value?: string | null) {
  if (!value) return "Not available";

  return currency ? `${currency} ${value}` : value;
}
