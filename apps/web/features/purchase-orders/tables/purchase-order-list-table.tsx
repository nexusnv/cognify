import Link from "next/link";
import { Button, Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";
import type { PurchaseOrder } from "@cognify/api-client/schemas";

export function PurchaseOrderListTable({
  purchaseOrders,
  state,
  onRetry,
}: {
  purchaseOrders: PurchaseOrder[];
  state: "idle" | "loading" | "error" | "empty";
  onRetry?: () => void;
}) {
  if (state === "loading") {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading purchase orders</div>;
  }

  if (state === "error") {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        <p>Purchase orders could not be loaded.</p>
        {onRetry ? (
          <Button type="button" variant="outline" className="mt-3" onClick={onRetry}>
            Retry
          </Button>
        ) : null}
      </div>
    );
  }

  if (state === "empty") {
    return (
      <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
        Purchase orders created from approved handoffs will appear here.
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-md border">
      <Table>
        <TableCaption className="sr-only">Purchase orders</TableCaption>
        <TableHeader className="bg-muted/30">
          <TableRow>
            <TableHead className="w-48">Purchase order</TableHead>
            <TableHead>Vendor</TableHead>
            <TableHead className="w-32">Status</TableHead>
            <TableHead className="w-40 text-right">Total</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {purchaseOrders.map((purchaseOrder) => (
            <TableRow key={purchaseOrder.id}>
              <TableCell>
                <Link
                  href={`/purchase-orders/${purchaseOrder.id}`}
                  className="font-mono text-xs tabular-nums underline-offset-4 hover:underline"
                >
                  {purchaseOrder.number}
                </Link>
              </TableCell>
              <TableCell className="font-medium">{purchaseOrder.vendor.name ?? "Unknown vendor"}</TableCell>
              <TableCell className="capitalize">{purchaseOrder.status.replaceAll("_", " ")}</TableCell>
              <TableCell className="text-right font-mono tabular-nums">
                {formatMoney(purchaseOrder.totalAmount, purchaseOrder.currency)}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
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
