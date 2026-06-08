import { RfqAwardRecommendationWorkspace } from "@/features/quotations/workflows/rfq-award-recommendation-workspace";

export default async function RfqAwardRecommendationPage({
  params,
}: {
  params: Promise<{ rfqId: string }>;
}) {
  const { rfqId } = await params;

  return <RfqAwardRecommendationWorkspace rfqId={rfqId} />;
}
