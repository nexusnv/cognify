"use client";

import { useRef, useState } from "react";
import { Alert, AlertDescription, Button, Checkbox, Input, NativeSelect, Textarea } from "@cognify/ui";
import { useSaveSourcingIntakeReview } from "../hooks/use-sourcing-intake-actions";
import { sourcingIntakeReviewFormSchema } from "../schemas/sourcing-intake-schema";
import type { SourcingIntakeChecklistItem, SourcingIntakeReview } from "../types/sourcing-view-model";

const defaultChecklist: SourcingIntakeChecklistItem[] = [
  { key: "specification_complete", label: "Specification complete", complete: false },
  { key: "budget_clear", label: "Budget clear", complete: false },
  { key: "line_items_complete", label: "Line items complete", complete: false },
  { key: "needed_by_feasible", label: "Needed-by date feasible", complete: false },
  { key: "evidence_sufficient", label: "Evidence sufficient", complete: false },
];

export function SourcingIntakeReviewForm({ review }: { review: SourcingIntakeReview }) {
  const mutation = useSaveSourcingIntakeReview(review.id);
  const [error, setError] = useState<string | null>(null);
  const checklistCheckboxRefs = useRef<Record<string, HTMLButtonElement | null>>({});
  const [values, setValues] = useState(() => ({
    category: review.category ?? "",
    subcategory: review.subcategory ?? "",
    urgency: review.urgency ?? "",
    complexity: review.complexity ?? "",
    targetDecisionDate: review.targetDecisionDate ?? "",
    checklist: review.checklist.length > 0 ? review.checklist : defaultChecklist,
    internalNotes: review.internalNotes ?? "",
  }));
  const disabled = !review.permissions.canUpdate || mutation.isPending;

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const payload = {
      category: values.category || null,
      subcategory: values.subcategory || null,
      urgency: values.urgency === "" ? null : values.urgency,
      complexity: values.complexity === "" ? null : values.complexity,
      targetDecisionDate: values.targetDecisionDate || null,
      checklist: values.checklist,
      internalNotes: values.internalNotes || null,
    };
    const parsed = sourcingIntakeReviewFormSchema.safeParse(payload);
    if (!parsed.success) {
      setError("Resolve the highlighted sourcing fields before saving.");
      return;
    }

    setError(null);
    try {
      await mutation.mutateAsync(parsed.data);
    } catch {
      setError("Sourcing intake could not be saved. Refresh and try again.");
    }
  }

  return (
    <form className="space-y-4 rounded-md border p-4" onSubmit={handleSubmit}>
      <div>
        <h2 className="text-base font-semibold">Buyer review</h2>
        <p className="mt-1 text-sm text-muted-foreground">Classify the request and complete intake checks.</p>
      </div>
      {error ? (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      ) : null}
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="space-y-1.5 text-sm font-medium">
          Category
          <Input
            className="h-11 px-3 text-base font-normal"
            value={values.category}
            disabled={disabled}
            onChange={(event) =>
              setValues((current) => ({ ...current, category: event.target.value }))
            }
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Subcategory
          <Input
            className="h-11 px-3 text-base font-normal"
            value={values.subcategory}
            disabled={disabled}
            onChange={(event) =>
              setValues((current) => ({ ...current, subcategory: event.target.value }))
            }
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Urgency
          <NativeSelect value={values.urgency} disabled={disabled} onChange={(event) => setValues((current) => ({ ...current, urgency: event.target.value }))}>
            <option value="">Not set</option>
            <option value="low">Low</option>
            <option value="standard">Standard</option>
            <option value="urgent">Urgent</option>
          </NativeSelect>
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Complexity
          <NativeSelect value={values.complexity} disabled={disabled} onChange={(event) => setValues((current) => ({ ...current, complexity: event.target.value }))}>
            <option value="">Not set</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </NativeSelect>
        </label>
      </div>
      <label className="block space-y-1.5 text-sm font-medium">
        Target decision date
        <Input
          type="date"
          className="h-11 px-3 text-base font-normal"
          value={values.targetDecisionDate}
          disabled={disabled}
          onChange={(event) =>
            setValues((current) => ({ ...current, targetDecisionDate: event.target.value }))
          }
        />
      </label>
      <fieldset className="space-y-2">
        <legend className="text-sm font-medium">Checklist</legend>
        {values.checklist.map((item) => (
          <div key={item.key} className="flex min-h-10 items-center gap-3 rounded-md border px-3 text-sm">
            <Checkbox
              id={item.key}
              name={item.key}
              ref={(element) => {
                checklistCheckboxRefs.current[item.key] = element;
              }}
              checked={item.complete}
              disabled={disabled}
              onCheckedChange={(checked) =>
                setValues((current) => ({
                  ...current,
                  checklist: current.checklist.map((entry) =>
                    entry.key === item.key ? { ...entry, complete: checked === true } : entry,
                  ),
                }))
              }
            />
            <label htmlFor={item.key} className="cursor-pointer">
              {item.label}
            </label>
          </div>
        ))}
      </fieldset>
      <label className="block space-y-1.5 text-sm font-medium">
        Internal notes
        <Textarea value={values.internalNotes} disabled={disabled} onChange={(event) => setValues((current) => ({ ...current, internalNotes: event.target.value }))} />
      </label>
      <Button type="submit" disabled={disabled}>{mutation.isPending ? "Saving" : "Save review"}</Button>
    </form>
  );
}
