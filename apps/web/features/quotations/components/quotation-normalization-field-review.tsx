"use client";

import { useState } from "react";
import { Button, Textarea } from "@cognify/ui";
import type { QuotationNormalizationField, QuotationNormalizationIssue } from "@cognify/api-client/schemas";
import { QuotationNormalizationIssueBadge } from "./quotation-normalization-issue-badge";

export function QuotationNormalizationFieldReview({
  field,
  issues,
  canEdit,
  onSave,
}: {
  field: QuotationNormalizationField;
  issues: QuotationNormalizationIssue[];
  canEdit: boolean;
  onSave: (payload: {
    fieldPath: string;
    correctedValue: unknown;
    issueId?: string;
    correctionNote: string;
    resolutionNote?: string;
  }) => Promise<void>;
}) {
  const [correctedValue, setCorrectedValue] = useState(stringifyValue(field.normalizedValue ?? ""));
  const [correctionNote, setCorrectionNote] = useState("");

  const issue = issues[0];

  return (
    <section data-testid={`normalization-field-${field.fieldPath}`} className="rounded-md border p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold">{field.fieldPath}</h3>
          <p className="text-xs text-muted-foreground">{field.provenance?.sourceLabel ? String(field.provenance.sourceLabel) : "Normalized field"}</p>
        </div>
        {issue ? <QuotationNormalizationIssueBadge severity={issue.severity} status={issue.status} /> : null}
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <div className="rounded-md border bg-muted/30 p-3">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Source value</p>
          <p className="mt-2 text-sm">{stringifyValue(field.rawValue) || "No source value"}</p>
        </div>
        <div className="rounded-md border p-3">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Normalized value</p>
          <p className="mt-2 text-sm">{stringifyValue(field.normalizedValue) || "No normalized value"}</p>
        </div>
      </div>

      {canEdit ? (
        <div className="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
          <label className="text-sm font-medium">
            Corrected value
            <input
              className="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
              value={correctedValue}
              onChange={(event) => setCorrectedValue(event.target.value)}
            />
          </label>
          <label className="text-sm font-medium">
            Correction note
            <Textarea
              className="mt-1 min-h-11 text-sm"
              value={correctionNote}
              onChange={(event) => setCorrectionNote(event.target.value)}
            />
          </label>
          <div className="flex items-end">
            <Button
              type="button"
              disabled={!correctionNote.trim()}
              onClick={() =>
                void onSave({
                  fieldPath: field.fieldPath,
                  correctedValue,
                  issueId: issue?.id,
                  correctionNote,
                  resolutionNote: correctionNote,
                })
              }
            >
              Save correction
            </Button>
          </div>
        </div>
      ) : null}
    </section>
  );
}

function stringifyValue(value: unknown) {
  if (value === null || value === undefined || value === "") return "";
  if (typeof value === "string") return value;
  return JSON.stringify(value);
}
