"use client";

import { useEffect, useMemo, useState } from "react";
import { Button, NativeSelect, Textarea } from "@cognify/ui";
import type { QuotationLineItem, QuotationNormalization, QuotationNormalizationLineGroup } from "@cognify/api-client/schemas";

type DraftState = {
  quotationVersionLineItemId: string;
  rfqLineItemId: string;
  pricingMode: "bundle" | "per_line" | "included" | "unknown";
  mappingType: "full" | "partial" | "bundled";
  description: string;
  currency: string;
  quantity: string;
  unit: string;
  bundleTotalAmount: string;
  buyerNote: string;
};

function extractRfqLineItemId(value: unknown): string | null {
  if (typeof value === "string") {
    return value.trim() || null;
  }

  if (value && typeof value === "object") {
    const maybeObject = value as { rfqLineItemId?: unknown; value?: unknown };

    if (typeof maybeObject.rfqLineItemId === "string" && maybeObject.rfqLineItemId.trim()) {
      return maybeObject.rfqLineItemId.trim();
    }

    if (typeof maybeObject.value === "string" && maybeObject.value.trim()) {
      return maybeObject.value.trim();
    }

    if (maybeObject.value && typeof maybeObject.value === "object") {
      const nestedValue = maybeObject.value as { rfqLineItemId?: unknown };
      if (typeof nestedValue.rfqLineItemId === "string" && nestedValue.rfqLineItemId.trim()) {
        return nestedValue.rfqLineItemId.trim();
      }
    }
  }

  return null;
}

function getDraftState(
  normalization: QuotationNormalization,
  versionLines: QuotationLineItem[],
  rfqOptions: string[],
  quotationCurrency: string | null | undefined,
): DraftState {
  const firstGroup = normalization.lineGroups[0] ?? null;
  const firstMapping = firstGroup?.mappings[0] ?? null;
  const selectedLine =
    versionLines.find((line) => line.id === firstMapping?.quotationVersionLineItemId) ?? versionLines[0] ?? null;

  return {
    quotationVersionLineItemId: firstMapping?.quotationVersionLineItemId ?? selectedLine?.id ?? "",
    rfqLineItemId: firstMapping?.rfqLineItemId ?? selectedLine?.rfqLineItemId ?? rfqOptions[0] ?? "",
    pricingMode: (firstGroup?.pricingMode ?? "bundle") as DraftState["pricingMode"],
    mappingType: (firstMapping?.mappingType ?? "bundled") as DraftState["mappingType"],
    description: firstGroup?.description ?? selectedLine?.description ?? "",
    currency: firstGroup?.currency ?? quotationCurrency ?? "",
    quantity: firstMapping?.quantity ?? selectedLine?.quantity ?? "",
    unit: firstMapping?.unit ?? selectedLine?.unit ?? "",
    bundleTotalAmount: firstGroup?.bundleTotalAmount ?? selectedLine?.totalAmount ?? "",
    buyerNote: firstMapping?.buyerNote ?? "",
  };
}

export function QuotationNormalizationLineMappingPanel({
  normalization,
  versionLines,
  quotationCurrency,
  canEdit,
  onSave,
}: {
  normalization: QuotationNormalization;
  versionLines: QuotationLineItem[];
  quotationCurrency?: string | null;
  canEdit: boolean;
  onSave: (group: DraftState) => Promise<void>;
}) {
  const rfqOptions = useMemo(() => {
    const ids = new Set<string>();
    for (const line of versionLines) {
      if (line.rfqLineItemId) ids.add(line.rfqLineItemId);
    }
    for (const group of normalization.lineGroups) {
      for (const mapping of group.mappings) {
        if (mapping.rfqLineItemId) ids.add(mapping.rfqLineItemId);
      }
    }
    for (const issue of normalization.issues) {
      const suggestedRfqLineItemId = extractRfqLineItemId(issue.suggestedValue);
      if (suggestedRfqLineItemId) ids.add(suggestedRfqLineItemId);
    }
    return [...ids];
  }, [normalization.issues, normalization.lineGroups, versionLines]);

  const [draft, setDraft] = useState<DraftState>(() => getDraftState(normalization, versionLines, rfqOptions, quotationCurrency));

  useEffect(() => {
    setDraft(getDraftState(normalization, versionLines, rfqOptions, quotationCurrency));
  }, [normalization, quotationCurrency, rfqOptions, versionLines]);

  function updateSelectedQuotationLine(lineId: string) {
    const nextLine = versionLines.find((line) => line.id === lineId) ?? null;

    setDraft((current) => ({
      ...current,
      quotationVersionLineItemId: lineId,
      rfqLineItemId: nextLine?.rfqLineItemId ?? current.rfqLineItemId ?? "",
      description: nextLine?.description ?? current.description,
      quantity: nextLine?.quantity ?? current.quantity,
      unit: nextLine?.unit ?? current.unit,
      bundleTotalAmount: nextLine?.totalAmount ?? current.bundleTotalAmount,
    }));
  }

  const hasSelectableRfqLines = rfqOptions.length > 0;

  return (
    <section id="line-mappings" data-testid="normalization-line-mappings" className="rounded-md border p-4">
      <div className="space-y-1">
        <h2 className="text-base font-semibold">Line mappings</h2>
        <p className="text-sm text-muted-foreground">Review how the current quotation version maps into comparable RFQ line bundles.</p>
      </div>

      {normalization.lineGroups.length > 0 ? (
        <div className="mt-4 space-y-3">
          {normalization.lineGroups.map((group: QuotationNormalizationLineGroup) => (
            <div key={group.id} className="rounded-md border bg-muted/20 p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">{group.description}</p>
                <p className="text-xs text-muted-foreground">{group.pricingMode}</p>
              </div>
              <p className="mt-1 text-sm text-muted-foreground">
                {group.currency ?? "No currency"} {group.bundleTotalAmount ?? "No total"}
              </p>
            </div>
          ))}
        </div>
      ) : (
        <p className="mt-4 text-sm text-muted-foreground">No buyer-reviewed mapping has been saved yet.</p>
      )}

      {canEdit ? (
        <div className="mt-4 grid gap-3 lg:grid-cols-2">
          <label className="text-sm font-medium">
            Quotation version line
            <NativeSelect
              className="mt-1 min-h-11 w-full"
              value={draft.quotationVersionLineItemId}
              onChange={(event) => updateSelectedQuotationLine(event.target.value)}
            >
              {versionLines.map((line) => (
                <option key={line.id} value={line.id}>
                  {line.description}
                </option>
              ))}
            </NativeSelect>
          </label>
          <label className="text-sm font-medium">
            RFQ line
            <NativeSelect
              className="mt-1 min-h-11 w-full"
              value={draft.rfqLineItemId}
              disabled={!hasSelectableRfqLines}
              aria-describedby={!hasSelectableRfqLines ? "rfq-line-unavailable" : undefined}
              onChange={(event) => setDraft((current) => ({ ...current, rfqLineItemId: event.target.value }))}
            >
              {hasSelectableRfqLines ? (
                rfqOptions.map((rfqLineId) => (
                  <option key={rfqLineId} value={rfqLineId}>
                    {rfqLineId}
                  </option>
                ))
              ) : (
                <option value="">No selectable RFQ lines</option>
              )}
            </NativeSelect>
          </label>
          <label className="text-sm font-medium">
            Pricing mode
            <NativeSelect
              className="mt-1 min-h-11 w-full"
              value={draft.pricingMode}
              onChange={(event) =>
                setDraft((current) => ({ ...current, pricingMode: event.target.value as DraftState["pricingMode"] }))
              }
            >
              <option value="bundle">bundle</option>
              <option value="per_line">per line</option>
              <option value="included">included</option>
              <option value="unknown">unknown</option>
            </NativeSelect>
          </label>
          <label className="text-sm font-medium">
            Mapping type
            <NativeSelect
              className="mt-1 min-h-11 w-full"
              value={draft.mappingType}
              onChange={(event) =>
                setDraft((current) => ({ ...current, mappingType: event.target.value as DraftState["mappingType"] }))
              }
            >
              <option value="bundled">bundled</option>
              <option value="partial">partial</option>
              <option value="full">full</option>
            </NativeSelect>
          </label>
          <label className="text-sm font-medium">
            Currency
            <input
              className="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
              value={draft.currency}
              onChange={(event) => setDraft((current) => ({ ...current, currency: event.target.value }))}
            />
          </label>
          <label className="text-sm font-medium">
            Bundle description
            <input
              className="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
              value={draft.description}
              onChange={(event) => setDraft((current) => ({ ...current, description: event.target.value }))}
            />
          </label>
          <label className="text-sm font-medium">
            Quantity
            <input
              className="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
              value={draft.quantity}
              onChange={(event) => setDraft((current) => ({ ...current, quantity: event.target.value }))}
            />
          </label>
          <label className="text-sm font-medium">
            Unit
            <input
              className="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
              value={draft.unit}
              onChange={(event) => setDraft((current) => ({ ...current, unit: event.target.value }))}
            />
          </label>
          <label className="text-sm font-medium">
            Bundle total
            <input
              className="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
              value={draft.bundleTotalAmount}
              onChange={(event) => setDraft((current) => ({ ...current, bundleTotalAmount: event.target.value }))}
            />
          </label>
          <label className="text-sm font-medium lg:col-span-2">
            Buyer note
            <Textarea
              className="mt-1 min-h-24 text-sm"
              value={draft.buyerNote}
              onChange={(event) => setDraft((current) => ({ ...current, buyerNote: event.target.value }))}
            />
          </label>
          {!hasSelectableRfqLines ? (
            <p id="rfq-line-unavailable" className="text-sm text-amber-700 lg:col-span-2">
              No RFQ line items are available to map this quotation version.
            </p>
          ) : null}
          <div className="lg:col-span-2">
            <Button
              type="button"
              disabled={
                !draft.quotationVersionLineItemId ||
                !draft.rfqLineItemId ||
                !draft.description.trim() ||
                !draft.currency.trim() ||
                !draft.quantity.trim() ||
                !draft.unit.trim() ||
                !hasSelectableRfqLines
              }
              onClick={() => void onSave(draft)}
            >
              Save line mapping
            </Button>
          </div>
        </div>
      ) : null}
    </section>
  );
}
