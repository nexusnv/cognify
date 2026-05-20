"use client";

import { useEffect, useState } from "react";
import { getApiErrorMessage } from "@cognify/api-client";
import type {
  QuotationVendorPortal,
  SaveQuotationManualEntryRequestForVendor,
} from "@cognify/api-client/schemas";
import { Button, Textarea } from "@cognify/ui";
import { useVendorQuotationManualEntry } from "../hooks/use-vendor-quotation";
import {
  formValuesFromQuotation,
  payloadFromFormValues,
  quotationManualEntrySchema,
  type QuotationManualEntryFormValues,
} from "../../sourcing/schemas/quotation-manual-entry-schema";
import { VendorQuotationLineItemsEditor } from "./vendor-quotation-line-items-editor";

export function VendorQuotationManualEntryPanel({
  token,
  quotation,
}: {
  token: string;
  quotation: QuotationVendorPortal | null;
}) {
  const saveMutation = useVendorQuotationManualEntry(token);
  const [values, setValues] = useState<QuotationManualEntryFormValues>(() => formValuesFromQuotation(quotation));
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [validationMessage, setValidationMessage] = useState<string | null>(null);

  const canSave = quotation?.permissions.canEditManualEntry !== false;
  const completeness = quotation?.completeness?.isComplete ?? false;
  const statusLabel = completeness ? "Ready for evaluation" : "Incomplete quotation data";
  const errorMessage = validationMessage ?? (saveMutation.isError ? getApiErrorMessage(saveMutation.error) : null);

  useEffect(() => {
    setValues(formValuesFromQuotation(quotation));
  }, [quotation]);

  function updateValue<K extends keyof QuotationManualEntryFormValues>(
    key: K,
    nextValue: QuotationManualEntryFormValues[K],
  ) {
    setSuccessMessage(null);
    setValidationMessage(null);
    setValues((current) => ({ ...current, [key]: nextValue }));
  }

  async function handleSave() {
    setSuccessMessage(null);
    setValidationMessage(null);

    const result = quotationManualEntrySchema.safeParse({
      ...values,
      buyerNotes: null,
    });
    if (!result.success) {
      setValidationMessage(result.error.issues[0]?.message ?? "Enter valid quotation details.");
      return;
    }

    try {
      const { buyerNotes: _buyerNotes, ...payload } = payloadFromFormValues(result.data);
      await saveMutation.mutateAsync(payload satisfies SaveQuotationManualEntryRequestForVendor);
      setSuccessMessage("Quotation details saved.");
    } catch {
      return;
    }
  }

  return (
    <section className="rounded-md border p-4">
      <div className="space-y-3">
        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <h3 className="text-base font-semibold">Structured quotation response</h3>
            <p className={completeness ? "text-sm text-emerald-700" : "text-sm text-muted-foreground"}>{statusLabel}</p>
          </div>
        </div>

        <div className="grid gap-3 lg:grid-cols-3">
          <label className="block text-sm font-medium">
            Quotation reference
            <input
              aria-label="Quotation reference"
              className="mt-1 min-h-11 w-full rounded-md border border-input bg-background px-3 text-base font-normal outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring"
              value={values.quotationReference ?? ""}
              disabled={saveMutation.isPending}
              onChange={(event) => updateValue("quotationReference", event.target.value)}
            />
          </label>
          <label className="block text-sm font-medium">
            Currency
            <input
              aria-label="Currency"
              className="mt-1 min-h-11 w-full rounded-md border border-input bg-background px-3 text-base font-normal uppercase outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring"
              value={values.currency ?? ""}
              disabled={saveMutation.isPending}
              maxLength={3}
              onChange={(event) => updateValue("currency", event.target.value.toUpperCase())}
            />
          </label>
          <label className="block text-sm font-medium">
            Total amount
            <input
              aria-label="Total amount"
              className="mt-1 min-h-11 w-full rounded-md border border-input bg-background px-3 text-base font-normal outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring"
              value={values.totalAmount ?? ""}
              disabled={saveMutation.isPending}
              onChange={(event) => updateValue("totalAmount", event.target.value)}
            />
          </label>
        </div>

        <label className="block text-sm font-medium">
          Vendor notes
          <Textarea
            aria-label="Vendor notes"
            className="mt-1 min-h-24"
            value={values.vendorNotes ?? ""}
            disabled={saveMutation.isPending}
            onChange={(event) => updateValue("vendorNotes", event.target.value)}
          />
        </label>

        <VendorQuotationLineItemsEditor
          lineItems={values.lineItems}
          onChange={(lineItems) => updateValue("lineItems", lineItems)}
          disabled={saveMutation.isPending}
        />

        <div className="flex flex-wrap items-center gap-3">
          <Button
            type="button"
            onClick={() => void handleSave()}
            disabled={!canSave || saveMutation.isPending}
          >
            Save quotation details
          </Button>
          {successMessage ? <p className="text-sm text-emerald-700">{successMessage}</p> : null}
          {!canSave ? (
            <p className="text-sm text-muted-foreground">Structured quotation entry is not available for this link.</p>
          ) : null}
        </div>

        {errorMessage ? (
          <p role="alert" className="text-sm text-red-700">
            {errorMessage}
          </p>
        ) : null}
      </div>
    </section>
  );
}
