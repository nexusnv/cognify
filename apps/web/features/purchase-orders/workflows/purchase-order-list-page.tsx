"use client";

import { usePurchaseOrders } from "../hooks/use-purchase-order";
import { PurchaseOrderListTable } from "../tables/purchase-order-list-table";

export function PurchaseOrderListPage() {
  const purchaseOrdersQuery = usePurchaseOrders();
  const purchaseOrders = purchaseOrdersQuery.data?.data ?? [];
  const state = purchaseOrdersQuery.isLoading
    ? "loading"
    : purchaseOrdersQuery.isError
      ? "error"
      : purchaseOrders.length === 0
        ? "empty"
        : "idle";

  return (
    <section className="space-y-5">
      <div className="border-b pb-4">
        <h1 className="text-2xl font-semibold">Purchase orders</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Review draft purchase orders created from approved sourcing outcomes.
        </p>
      </div>

      <PurchaseOrderListTable
        purchaseOrders={purchaseOrders}
        state={state}
        onRetry={() => purchaseOrdersQuery.refetch()}
      />
    </section>
  );
}
