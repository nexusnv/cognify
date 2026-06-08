import { SourcingIntakeDetailPage } from "@/features/sourcing/workflows/sourcing-intake-detail-page";

export default async function Page({ params }: { params: Promise<{ reviewId: string }> }) {
  const { reviewId } = await params;

  return <SourcingIntakeDetailPage reviewId={reviewId} />;
}
