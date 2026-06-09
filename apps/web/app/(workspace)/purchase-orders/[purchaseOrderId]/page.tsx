import { PurchaseOrderWorkspacePage } from "@/features/purchase-orders/workflows/purchase-order-workspace-page";

export default async function Page({
  params,
}: {
  params: Promise<{ purchaseOrderId: string }>;
}) {
  const { purchaseOrderId } = await params;

  return <PurchaseOrderWorkspacePage purchaseOrderId={purchaseOrderId} />;
}
