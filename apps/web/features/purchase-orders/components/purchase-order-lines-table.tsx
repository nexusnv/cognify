import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";
import type { PurchaseOrderLine } from "@cognify/api-client/schemas";

export function PurchaseOrderLinesTable({ lines, currency }: { lines: PurchaseOrderLine[]; currency: string }) {
  return (
    <section id="lines" className="rounded-md border">
      <Table aria-label="Purchase order lines">
        <TableCaption className="sr-only">Purchase order lines</TableCaption>
        <TableHeader className="bg-muted/30">
          <TableRow>
            <TableHead className="w-16">Line</TableHead>
            <TableHead>Description</TableHead>
            <TableHead className="w-24">Unit</TableHead>
            <TableHead className="w-28 text-right">Quantity</TableHead>
            <TableHead className="w-36 text-right">Unit price</TableHead>
            <TableHead className="w-36 text-right">Total</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {lines.map((line) => (
            <TableRow key={line.id}>
              <TableCell>{line.lineNumber}</TableCell>
              <TableCell className="font-medium">{line.description}</TableCell>
              <TableCell>{line.unit}</TableCell>
              <TableCell className="text-right font-mono tabular-nums">{line.quantity}</TableCell>
              <TableCell className="text-right font-mono tabular-nums">
                {formatMoney(line.unitPrice, currency)}
              </TableCell>
              <TableCell className="text-right font-mono tabular-nums">
                {formatMoney(line.totalAmount, currency)}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </section>
  );
}

function formatMoney(amount: string, currency: string) {
  const numericAmount = Number(amount);
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(numericAmount) ? numericAmount : 0);
}
