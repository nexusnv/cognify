"use client";

import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Popover, PopoverContent, PopoverTrigger } from "@cognify/ui";
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
    <Card>
      <CardHeader>
        <CardTitle>Start from a template</CardTitle>
        <CardDescription>Apply defaults to speed up draft entry.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        {templates.map((template) => (
          <Card key={template.id}>
            <CardContent className="pt-4">
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
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    aria-label={`Replace draft fields with ${template.name}`}
                    onClick={() => onApply(template, "replace")}
                  >
                    Replace draft fields
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-64 text-sm" align="start">
                  Replace mode overwrites existing draft fields with template defaults.
                </PopoverContent>
                </Popover>
            </div>
            </CardContent>
          </Card>
        ))}
      </CardContent>
    </Card>
  );
}
