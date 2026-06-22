import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import type { SupplierCreditMemoLine } from "@cognify/api-client/schemas";

export function CreditMemoMathPreview({ lines }: { lines: SupplierCreditMemoLine[] }) {
  const subtotalSum = lines.reduce(
    (acc, line) => acc + Number(line.lineSubtotal),
    0,
  );
  const taxSum = lines.reduce((acc, line) => acc + Number(line.taxAmount ?? 0), 0);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Math preview</CardTitle>
      </CardHeader>
      <CardContent className="text-sm space-y-1">
        <div className="flex justify-between">
          <span>Lines subtotal</span>
          <span>{subtotalSum.toFixed(4)}</span>
        </div>
        <div className="flex justify-between">
          <span>Lines tax</span>
          <span>{taxSum.toFixed(4)}</span>
        </div>
        <div className="flex justify-between font-semibold">
          <span>Lines total</span>
          <span>{(subtotalSum + taxSum).toFixed(4)}</span>
        </div>
      </CardContent>
    </Card>
  );
}
