"use client";

import { WorkflowStateLayout } from "@/components/ui/workflow-state/record-workflow-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import { PurchaseOrderActions } from "../components/purchase-order-actions";
import { PurchaseOrderChangeOrderPanel } from "../components/purchase-order-change-order-panel";
import { PurchaseOrderApprovalPanel } from "../components/purchase-order-approval-panel";
import { PurchaseOrderDetailCard } from "../components/purchase-order-detail-card";
import { PurchaseOrderLinesTable } from "../components/purchase-order-lines-table";
import { PurchaseOrderSupplierIssuePanel } from "../components/purchase-order-supplier-issue-panel";
import { usePurchaseOrder } from "../hooks/use-purchase-order";

export function PurchaseOrderWorkspacePage({ purchaseOrderId }: { purchaseOrderId: string }) {
  const purchaseOrderQuery = usePurchaseOrder(purchaseOrderId);
  const purchaseOrder = purchaseOrderQuery.data;

  if (purchaseOrderQuery.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading purchase order workspace</div>;
  }

  if (purchaseOrderQuery.isError || !purchaseOrder) {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        Purchase order could not be loaded.
      </div>
    );
  }

  const canShowChangeOrders = ["issued", "acknowledged", "change_pending", "cancelled"].includes(purchaseOrder.status);

  return (
    <WorkflowStateLayout
      backHref="/purchase-orders"
      backLabel="Back to purchase orders"
      eyebrow={purchaseOrder.id}
      title={purchaseOrder.number}
      status={<span className="rounded-full border px-2 py-1 text-xs font-medium capitalize">{purchaseOrder.status.replaceAll("_", " ")}</span>}
      metadata={[
        { id: "currency", label: "Currency", value: purchaseOrder.currency },
        { id: "total", label: "Total", value: formatMoney(purchaseOrder.totalAmount, purchaseOrder.currency) },
        { id: "handoff", label: "Source handoff", value: purchaseOrder.source.handoffId },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "lines", label: "Lines" },
        ...(canShowChangeOrders ? [{ id: "change-orders", label: "Change orders" }] : []),
        { id: "draft-fields", label: "Draft fields" },
      ]}
      sidebar={
        <>
          <Card className="py-0">
            <CardHeader className="border-b bg-muted/30">
              <CardTitle>Vendor summary</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 py-4 text-sm">
              <p className="font-medium">{purchaseOrder.vendor.name ?? "Unknown vendor"}</p>
              <p className="text-muted-foreground">RFQ {purchaseOrder.source.rfqId}</p>
              <p className="text-muted-foreground">Recommendation {purchaseOrder.source.recommendationId}</p>
            </CardContent>
          </Card>
          <Card className="py-0">
            <CardHeader className="border-b bg-muted/30">
              <CardTitle>Operational totals</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 py-4 text-sm">
              <SidebarMetric label="Subtotal" value={formatMoney(purchaseOrder.subtotalAmount ?? "0", purchaseOrder.currency)} />
              <SidebarMetric label="Tax" value={formatMoney(purchaseOrder.taxAmount ?? "0", purchaseOrder.currency)} />
              <SidebarMetric label="Freight" value={formatMoney(purchaseOrder.freightAmount ?? "0", purchaseOrder.currency)} />
              <SidebarMetric label="Total" value={formatMoney(purchaseOrder.totalAmount, purchaseOrder.currency)} />
            </CardContent>
          </Card>
        </>
      }
    >
      <PurchaseOrderDetailCard purchaseOrder={purchaseOrder} />
      <PurchaseOrderLinesTable lines={purchaseOrder.lines} currency={purchaseOrder.currency} />
      {canShowChangeOrders ? <PurchaseOrderChangeOrderPanel purchaseOrder={purchaseOrder} /> : null}
      <PurchaseOrderApprovalPanel purchaseOrder={purchaseOrder} />
      <PurchaseOrderSupplierIssuePanel purchaseOrder={purchaseOrder} />
      <PurchaseOrderActions purchaseOrder={purchaseOrder} />
    </WorkflowStateLayout>
  );
}

function SidebarMetric({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-mono tabular-nums">{value}</span>
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
