import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";

interface InvoiceResolvedPaymentSummaryProps {
  exceptions: SupplierInvoiceException[];
  invoiceTotal: string;
}

export function InvoiceResolvedPaymentSummary({
  exceptions,
  invoiceTotal,
}: InvoiceResolvedPaymentSummaryProps) {
  const adjustments = exceptions.filter(
    (e) => e.status === "resolved" && e.resolutionType === "value_adjustment" && e.resolutionData?.adjusted_value,
  );
  const adjustmentTotal = adjustments.reduce((sum, e) => {
    return sum + parseFloat(e.resolutionData!.adjusted_value as string);
  }, 0);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Payment summary</CardTitle>
      </CardHeader>
      <CardContent className="space-y-2 text-sm">
        <div className="flex justify-between">
          <span>Invoice total</span>
          <span>{invoiceTotal}</span>
        </div>
        {adjustments.length > 0 && (
          <div className="flex justify-between text-muted-foreground">
            <span>Adjustments ({adjustments.length})</span>
            <span>{adjustmentTotal.toFixed(4)}</span>
          </div>
        )}
        <div className="flex justify-between font-medium border-t pt-2">
          <span>Proposed payment</span>
          <span>{(parseFloat(invoiceTotal) + adjustmentTotal).toFixed(4)}</span>
        </div>
      </CardContent>
    </Card>
  );
}
