import { RfqDraftWorkspace } from "@/features/sourcing/workflows/rfq-draft-workspace";

export default async function RfqWorkspacePage({
  params,
}: {
  params: Promise<{ rfqId: string }>;
}) {
  const { rfqId } = await params;

  return <RfqDraftWorkspace rfqId={rfqId} />;
}
