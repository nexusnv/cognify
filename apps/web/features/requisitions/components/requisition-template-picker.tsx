"use client";

import { Button, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
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
    <Card className="py-0">
      <CardHeader className="border-b bg-muted/30">
        <CardTitle>Start from a template</CardTitle>
      </CardHeader>
      <CardContent className="grid gap-3 py-4">
      <div className="grid gap-3">
        {templates.map((template) => (
          <div key={template.id} className="rounded-md border p-3">
            <p className="font-medium">{template.name}</p>
            {template.description ? (
              <p className="mt-1 text-sm text-muted-foreground">{template.description}</p>
            ) : null}
            <div className="mt-3 flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                disabled={disabled}
                aria-label={`Fill empty fields from ${template.name}`}
                onClick={() => onApply(template, "fill-empty")}
              >
                Fill empty fields
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={disabled}
                aria-label={`Replace draft fields with ${template.name}`}
                onClick={() => onApply(template, "replace")}
              >
                Replace draft fields
              </Button>
            </div>
          </div>
        ))}
      </div>
      </CardContent>
    </Card>
  );
}
