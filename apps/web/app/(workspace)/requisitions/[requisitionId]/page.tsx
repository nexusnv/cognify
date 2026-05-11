import { RequisitionDetailPage } from "@/features/requisitions/workflows/requisition-detail-page";

export default async function RequisitionWorkspacePage({
  params,
}: {
  params: Promise<{ requisitionId: string }>;
}) {
  const { requisitionId } = await params;

  return <RequisitionDetailPage requisitionId={requisitionId} />;
}
