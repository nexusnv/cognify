import { RfqScoringWorkspace } from "@/features/quotations/workflows/rfq-scoring-workspace";

export default async function RfqScoringPage({
  params,
}: {
  params: Promise<{ rfqId: string }>;
}) {
  const { rfqId } = await params;

  return <RfqScoringWorkspace rfqId={rfqId} />;
}
