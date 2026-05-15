"use client";

import type { RequisitionTemplate, RequisitionTemplateMode } from "../types/requisition-view-model";

export function RequisitionTemplatePicker({
  templates,
  disabled,
  onApply,
}: {
  templates: RequisitionTemplate[];
  disabled?: boolean;
  onApply: (template: RequisitionTemplate, mode: RequisitionTemplateMode) => void;
}) {
  if (templates.length === 0) return null;

  return (
    <section className="space-y-3 rounded-md border p-4">
      <h2 className="text-base font-semibold">Start from a template</h2>
      <div className="grid gap-3">
        {templates.map((template) => (
          <div key={template.id} className="rounded-md border p-3">
            <p className="font-medium">{template.name}</p>
            {template.description ? (
              <p className="mt-1 text-sm text-muted-foreground">{template.description}</p>
            ) : null}
            <div className="mt-3 flex flex-wrap gap-2">
              <button
                type="button"
                className="min-h-10 rounded-md border px-3 text-sm font-medium"
                disabled={disabled}
                aria-label={`Fill empty fields from ${template.name}`}
                onClick={() => onApply(template, "fill-empty")}
              >
                Fill empty fields
              </button>
              <button
                type="button"
                className="min-h-10 rounded-md border px-3 text-sm font-medium"
                disabled={disabled}
                aria-label={`Replace draft fields with ${template.name}`}
                onClick={() => onApply(template, "replace")}
              >
                Replace draft fields
              </button>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
