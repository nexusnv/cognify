import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import type { PurchaseOrder } from "@cognify/api-client/schemas";

export function PurchaseOrderDetailCard({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  return (
    <section id="overview">
      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>Purchase order summary</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 py-4 md:grid-cols-2">
          <SummaryItem label="Status" value={purchaseOrder.status.replaceAll("_", " ")} />
          <SummaryItem label="Requested PO date" value={purchaseOrder.requestedPoDate ?? "Not set"} />
          <SummaryItem
            label="Expected delivery"
            value={purchaseOrder.expectedDeliveryDate ?? "Not set"}
          />
          <SummaryItem label="Payment terms" value={purchaseOrder.paymentTerms ?? "Not set"} />
          <SummaryItem label="Delivery terms" value={purchaseOrder.deliveryTerms ?? "Not set"} />
          <SummaryItem label="Billing" value={purchaseOrder.billingName ?? "Not set"} />
          <SummaryItem label="Shipping" value={purchaseOrder.shippingName ?? "Not set"} />
          <SummaryItem
            label="Delivery attention"
            value={purchaseOrder.deliveryAttention ?? "Not set"}
          />
          <SummaryItem
            label="Source handoff"
            value={purchaseOrder.source.handoffId}
          />
        </CardContent>
      </Card>
    </section>
  );
}

function SummaryItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="space-y-1 text-sm">
      <p className="text-muted-foreground">{label}</p>
      <p className="font-medium">{value}</p>
    </div>
  );
}
