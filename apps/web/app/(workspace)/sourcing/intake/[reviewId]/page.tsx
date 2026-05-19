import { SourcingIntakeDetailPage } from "@/features/sourcing/workflows/sourcing-intake-detail-page";

export default function Page({ params }: { params: { reviewId: string } }) {
  return <SourcingIntakeDetailPage reviewId={params.reviewId} />;
}
