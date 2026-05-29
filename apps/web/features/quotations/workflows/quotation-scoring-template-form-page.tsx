"use client";

import Link from "next/link";
import { getApiErrorMessage } from "@cognify/api-client";
import { Alert, AlertDescription, Card, CardContent } from "@cognify/ui";
import { QuotationScoringTemplateForm } from "../components/quotation-scoring-template-form";
import {
  useQuotationScoringTemplate,
  useSaveQuotationScoringTemplate,
} from "../hooks/use-quotation-scoring-templates";

export function QuotationScoringTemplateFormPage({ templateId }: { templateId?: string }) {
  const isNew = !templateId || templateId === "new";
  const templateQuery = useQuotationScoringTemplate(templateId);
  const saveTemplate = useSaveQuotationScoringTemplate();
  const template = templateQuery.data;

  if (!isNew && templateQuery.isLoading) {
    return <Card><CardContent className="py-4 text-sm text-muted-foreground">Loading scoring template</CardContent></Card>;
  }

  if (!isNew && (templateQuery.isError || !template)) {
    return (
      <Alert variant="destructive"><AlertDescription>{getApiErrorMessage(templateQuery.error)}</AlertDescription></Alert>
    );
  }

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <Link className="text-sm font-medium underline-offset-4 hover:underline" href="/quotations/scoring/templates">
          Back to scoring templates
        </Link>
        <h1 className="text-2xl font-semibold">{isNew ? "Create scoring template" : "Edit scoring template"}</h1>
      </header>

      {saveTemplate.isError ? (
        <Alert variant="destructive"><AlertDescription>{getApiErrorMessage(saveTemplate.error)}</AlertDescription></Alert>
      ) : null}

      <QuotationScoringTemplateForm
        template={template}
        isSaving={saveTemplate.isPending}
        onSave={async (input) => {
          await saveTemplate.mutateAsync(input);
        }}
      />
    </div>
  );
}
