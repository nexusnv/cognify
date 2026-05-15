import { RequisitionCreatePage } from "@/features/requisitions/workflows/requisition-create-page";

export default async function EditRequisitionPage({
  params,
}: {
  params: Promise<{ requisitionId: string }>;
}) {
  const { requisitionId } = await params;

  return <RequisitionCreatePage requisitionId={requisitionId} />;
}
