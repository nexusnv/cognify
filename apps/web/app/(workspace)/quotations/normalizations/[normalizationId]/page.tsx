import { QuotationNormalizationWorkspace } from "@/features/quotations/workflows/quotation-normalization-workspace";

export default async function QuotationNormalizationWorkspacePage({
  params,
}: {
  params: Promise<{ normalizationId: string }>;
}) {
  const { normalizationId } = await params;

  return <QuotationNormalizationWorkspace normalizationId={normalizationId} />;
}
