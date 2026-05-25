"use client";

import Link from "next/link";
import { getApiErrorMessage } from "@cognify/api-client";
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
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading scoring template</div>;
  }

  if (!isNew && (templateQuery.isError || !template)) {
    return (
      <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        {getApiErrorMessage(templateQuery.error)}
      </div>
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
        <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
          {getApiErrorMessage(saveTemplate.error)}
        </div>
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
