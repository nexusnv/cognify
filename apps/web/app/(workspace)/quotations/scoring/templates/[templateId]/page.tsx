import { QuotationScoringTemplateFormPage } from "@/features/quotations/workflows/quotation-scoring-template-form-page";

export default async function ScoringTemplateFormRoute({
  params,
}: {
  params: Promise<{ templateId: string }>;
}) {
  const { templateId } = await params;

  return <QuotationScoringTemplateFormPage templateId={templateId} />;
}
