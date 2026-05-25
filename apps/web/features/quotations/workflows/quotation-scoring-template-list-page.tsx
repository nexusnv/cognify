"use client";

import Link from "next/link";
import { Badge } from "@cognify/ui";
import { getApiErrorMessage } from "@cognify/api-client";
import {
  useDeactivateQuotationScoringTemplate,
  useQuotationScoringTemplates,
} from "../hooks/use-quotation-scoring-templates";

export function QuotationScoringTemplateListPage() {
  const templatesQuery = useQuotationScoringTemplates();
  const deactivate = useDeactivateQuotationScoringTemplate();
  const templates = templatesQuery.data ?? [];

  if (templatesQuery.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading scoring templates</div>;
  }

  if (templatesQuery.isError) {
    return (
      <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        {getApiErrorMessage(templatesQuery.error)}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Scoring templates</h1>
          <p className="text-sm text-muted-foreground">Maintain reusable RFQ scoring criteria for buyer evaluations.</p>
        </div>
        <Link
          className="inline-flex min-h-11 items-center rounded-md bg-foreground px-4 text-sm font-medium text-background hover:bg-foreground/90"
          href="/quotations/scoring/templates/new"
        >
          Create template
        </Link>
      </header>

      <div className="overflow-x-auto rounded-md border">
        <table className="w-full min-w-[760px] text-left text-sm">
          <thead className="bg-muted/40 text-xs uppercase text-muted-foreground">
            <tr>
              <th className="px-4 py-3">Name</th>
              <th className="px-4 py-3">State</th>
              <th className="px-4 py-3">Criteria</th>
              <th className="px-4 py-3">Total weight</th>
              <th className="px-4 py-3">Usage</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            {templates.map((template) => (
              <tr key={template.id} className="border-t">
                <td className="px-4 py-3 font-medium">{template.name}</td>
                <td className="px-4 py-3">
                  <Badge variant={template.active ? "default" : "secondary"}>{template.active ? "Active" : "Inactive"}</Badge>
                </td>
                <td className="px-4 py-3">{template.criteria.length}</td>
                <td className="px-4 py-3">
                  {template.criteria.reduce((sum, criterion) => sum + Number(criterion.weight), 0).toFixed(2)}
                </td>
                <td className="px-4 py-3">{template.usageCount}</td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap gap-2">
                    {template.permissions?.canUpdate ? (
                      <Link className="text-sm font-medium underline-offset-4 hover:underline" href={`/quotations/scoring/templates/${template.id}`}>
                        Edit
                      </Link>
                    ) : null}
                    {template.active && template.permissions?.canDeactivate ? (
                      <button
                        className="text-sm font-medium text-red-700 underline-offset-4 hover:underline"
                        type="button"
                        onClick={() => deactivate.mutate(template.id)}
                      >
                        Deactivate
                      </button>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
