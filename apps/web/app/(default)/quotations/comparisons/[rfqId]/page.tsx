import { QuotationComparisonWorkspace } from "@/features/quotations/workflows/quotation-comparison-workspace";

export default async function QuotationComparisonPage({
  params,
}: {
  params: Promise<{ rfqId: string }>;
}) {
  const { rfqId } = await params;

  return <QuotationComparisonWorkspace rfqId={rfqId} />;
}
