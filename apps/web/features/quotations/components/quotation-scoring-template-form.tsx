"use client";

import { Button, NativeSelect, Textarea } from "@cognify/ui";
import type { QuotationScoringTemplate, SaveQuotationScoringTemplateRequest } from "@cognify/api-client/schemas";
import { useMemo, useState } from "react";

type CriterionDraft = SaveQuotationScoringTemplateRequest["criteria"][number];

const categories = ["cost", "delivery", "quality", "compliance", "risk", "sustainability", "past_performance", "other"] as const;

export function QuotationScoringTemplateForm({
  template,
  isSaving = false,
  onSave,
}: {
  template?: QuotationScoringTemplate | null;
  isSaving?: boolean;
  onSave: (input: SaveQuotationScoringTemplateRequest & { id?: string }) => Promise<void> | void;
}) {
  const [name, setName] = useState(template?.name ?? "");
  const [description, setDescription] = useState(template?.description ?? "");
  const [criteria, setCriteria] = useState<CriterionDraft[]>(
    template?.criteria.map((criterion) => ({
      category: criterion.category,
      label: criterion.label,
      guidance: criterion.guidance ?? "",
      weight: criterion.weight,
      maxScore: criterion.maxScore,
      required: criterion.required,
      displayOrder: criterion.displayOrder,
    })) ?? [blankCriterion(1)],
  );
  const [error, setError] = useState<string | null>(null);

  const totalWeight = useMemo(
    () => criteria.reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0).toFixed(2),
    [criteria],
  );

  async function submit() {
    const validationError = validate(name, criteria);
    if (validationError) {
      setError(validationError);
      return;
    }

    setError(null);
    await onSave({
      id: template?.id,
      name: name.trim(),
      description: description.trim() || null,
      criteria: criteria.map((criterion, index) => ({
        ...criterion,
        label: criterion.label.trim(),
        guidance: typeof criterion.guidance === "string" && criterion.guidance.trim() !== "" ? criterion.guidance.trim() : null,
        displayOrder: index + 1,
      })),
    });
  }

  return (
    <form
      className="space-y-6"
      onSubmit={(event) => {
        event.preventDefault();
        void submit();
      }}
    >
      {error ? (
        <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {error}
        </div>
      ) : null}

      <section className="grid gap-4">
        <label className="grid gap-2 text-sm font-medium">
          Template name
          <input
            className="min-h-11 rounded-md border px-3 text-sm"
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
        </label>
        <label className="grid gap-2 text-sm font-medium">
          Description
          <Textarea value={description ?? ""} onChange={(event) => setDescription(event.target.value)} />
        </label>
      </section>

      <section className="space-y-3" aria-label="Scoring criteria">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold">Criteria</h2>
            <p className="text-sm text-muted-foreground">Total weight {totalWeight}</p>
          </div>
          <Button
            type="button"
            variant="secondary"
            onClick={() => setCriteria((current) => [...current, blankCriterion(current.length + 1)])}
          >
            Add criterion
          </Button>
        </div>

        <div className="grid gap-3">
          {criteria.map((criterion, index) => (
            <div key={index} className="rounded-md border p-4" data-testid="criterion-row">
              <div className="grid gap-3 md:grid-cols-[minmax(120px,160px)_1fr_minmax(90px,120px)_minmax(90px,120px)]">
                <label className="grid gap-2 text-sm font-medium">
                  Category
                  <NativeSelect
                    value={criterion.category}
                    onChange={(event) => updateCriterion(index, { category: event.target.value as CriterionDraft["category"] })}
                  >
                    {categories.map((category) => (
                      <option key={category} value={category}>
                        {category.replace("_", " ")}
                      </option>
                    ))}
                  </NativeSelect>
                </label>
                <label className="grid gap-2 text-sm font-medium">
                  Label
                  <input
                    className="min-h-11 rounded-md border px-3 text-sm"
                    value={criterion.label}
                    onChange={(event) => updateCriterion(index, { label: event.target.value })}
                  />
                </label>
                <label className="grid gap-2 text-sm font-medium">
                  Weight
                  <input
                    className="min-h-11 rounded-md border px-3 text-sm"
                    inputMode="decimal"
                    value={String(criterion.weight)}
                    onChange={(event) => updateCriterion(index, { weight: event.target.value })}
                  />
                </label>
                <label className="grid gap-2 text-sm font-medium">
                  Max score
                  <input
                    className="min-h-11 rounded-md border px-3 text-sm"
                    inputMode="numeric"
                    value={criterion.maxScore}
                    onChange={(event) => updateCriterion(index, { maxScore: Number(event.target.value) })}
                  />
                </label>
              </div>
              <label className="mt-3 grid gap-2 text-sm font-medium">
                Guidance
                <Textarea value={criterion.guidance ?? ""} onChange={(event) => updateCriterion(index, { guidance: event.target.value })} />
              </label>
              <div className="mt-3 flex flex-wrap items-center gap-2">
                <label className="inline-flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={criterion.required}
                    onChange={(event) => updateCriterion(index, { required: event.target.checked })}
                  />
                  Required
                </label>
                <Button type="button" variant="outline" size="sm" disabled={index === 0} onClick={() => moveCriterion(index, -1)}>
                  Move up
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={index === criteria.length - 1}
                  onClick={() => moveCriterion(index, 1)}
                >
                  Move down
                </Button>
                <Button type="button" variant="destructive" size="sm" onClick={() => removeCriterion(index)}>
                  Remove criterion
                </Button>
              </div>
            </div>
          ))}
        </div>
      </section>

      <Button type="submit" disabled={isSaving}>
        Save scoring template
      </Button>
    </form>
  );

  function updateCriterion(index: number, patch: Partial<CriterionDraft>) {
    setCriteria((current) => current.map((criterion, itemIndex) => (itemIndex === index ? { ...criterion, ...patch } : criterion)));
  }

  function moveCriterion(index: number, direction: -1 | 1) {
    setCriteria((current) => {
      const next = [...current];
      const targetIndex = index + direction;
      [next[index], next[targetIndex]] = [next[targetIndex], next[index]];
      return next.map((criterion, itemIndex) => ({ ...criterion, displayOrder: itemIndex + 1 }));
    });
  }

  function removeCriterion(index: number) {
    setCriteria((current) => current.filter((_, itemIndex) => itemIndex !== index).map((criterion, itemIndex) => ({ ...criterion, displayOrder: itemIndex + 1 })));
  }
}

function blankCriterion(displayOrder: number): CriterionDraft {
  return {
    category: "cost",
    label: "",
    guidance: "",
    weight: "1.00",
    maxScore: 10,
    required: true,
    displayOrder,
  };
}

function validate(name: string, criteria: CriterionDraft[]): string | null {
  if (!name.trim()) return "Template name is required.";
  if (criteria.length === 0) return "Add at least one criterion.";
  if (criteria.some((criterion) => Number(criterion.weight) <= 0)) return "Weight must be greater than 0.";
  if (criteria.some((criterion) => Number(criterion.maxScore) < 1 || Number(criterion.maxScore) > 100)) {
    return "Max score must be between 1 and 100.";
  }
  if (criteria.some((criterion) => !criterion.label.trim())) return "Criterion label is required.";

  return null;
}
