"use client";

import { useEffect, useMemo, useState } from "react";
import { Button, NativeSelect, Textarea } from "@cognify/ui";
import type { QuotationLineItem, QuotationNormalization, QuotationNormalizationLineGroup } from "@cognify/api-client/schemas";

type DraftState = {
  quotationVersionLineItemId: string;
  rfqLineItemId: string;
  pricingMode: "bundle" | "per_line" | "included" | "unknown";
  description: string;
  bundleTotalAmount: string;
  buyerNote: string;
};

export function QuotationNormalizationLineMappingPanel({
  normalization,
  versionLines,
  canEdit,
  onSave,
}: {
  normalization: QuotationNormalization;
  versionLines: QuotationLineItem[];
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
    return [...ids];
  }, [normalization.lineGroups, versionLines]);

  const firstGroup = normalization.lineGroups[0] ?? null;
  const firstMapping = firstGroup?.mappings[0] ?? null;
  const [draft, setDraft] = useState<DraftState>({
    quotationVersionLineItemId: firstMapping?.quotationVersionLineItemId ?? versionLines[0]?.id ?? "",
    rfqLineItemId: firstMapping?.rfqLineItemId ?? rfqOptions[0] ?? "",
    pricingMode: (firstGroup?.pricingMode ?? "bundle") as DraftState["pricingMode"],
    description: firstGroup?.description ?? versionLines[0]?.description ?? "",
    bundleTotalAmount: firstGroup?.bundleTotalAmount ?? versionLines[0]?.totalAmount ?? "",
    buyerNote: firstMapping?.buyerNote ?? "",
  });

  useEffect(() => {
    setDraft({
      quotationVersionLineItemId: firstMapping?.quotationVersionLineItemId ?? versionLines[0]?.id ?? "",
      rfqLineItemId: firstMapping?.rfqLineItemId ?? rfqOptions[0] ?? "",
      pricingMode: (firstGroup?.pricingMode ?? "bundle") as DraftState["pricingMode"],
      description: firstGroup?.description ?? versionLines[0]?.description ?? "",
      bundleTotalAmount: firstGroup?.bundleTotalAmount ?? versionLines[0]?.totalAmount ?? "",
      buyerNote: firstMapping?.buyerNote ?? "",
    });
  }, [firstGroup, firstMapping, rfqOptions, versionLines]);

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
              onChange={(event) => setDraft((current) => ({ ...current, quotationVersionLineItemId: event.target.value }))}
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
              onChange={(event) => setDraft((current) => ({ ...current, rfqLineItemId: event.target.value }))}
            >
              {rfqOptions.map((rfqLineId) => (
                <option key={rfqLineId} value={rfqLineId}>
                  {rfqLineId}
                </option>
              ))}
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
            Bundle description
            <input
              className="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
              value={draft.description}
              onChange={(event) => setDraft((current) => ({ ...current, description: event.target.value }))}
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
          <div className="lg:col-span-2">
            <Button
              type="button"
              disabled={!draft.quotationVersionLineItemId || !draft.rfqLineItemId || !draft.description}
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
