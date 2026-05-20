"use client";

import { useState } from "react";
import { getApiErrorMessage } from "@cognify/api-client";
import type { Quotation } from "@cognify/api-client/schemas";
import { Button, Textarea } from "@cognify/ui";
import { useSaveQuotationManualEntry } from "../hooks/use-quotation-manual-entry";
import {
  formValuesFromQuotation,
  payloadFromFormValues,
  quotationManualEntrySchema,
  type QuotationManualEntryFormValues,
} from "../schemas/quotation-manual-entry-schema";
import { QuotationLineItemsEditor } from "./quotation-line-items-editor";

const editableInvitationStatuses = new Set(["sent", "acknowledged"]);

export function QuotationManualEntryPanel({
  invitationId,
  invitationStatus,
  quotation,
}: {
  invitationId: string;
  invitationStatus: string;
  quotation: Quotation | null;
}) {
  const saveMutation = useSaveQuotationManualEntry(invitationId, quotation?.id);
  const [values, setValues] = useState<QuotationManualEntryFormValues>(() => formValuesFromQuotation(quotation));
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [validationMessage, setValidationMessage] = useState<string | null>(null);

  const canSave = editableInvitationStatuses.has(invitationStatus);
  const completeness = quotation?.completeness?.isComplete ?? false;
  const statusLabel = completeness ? "Ready for evaluation" : "Incomplete quotation data";
  const errorMessage = validationMessage ?? (saveMutation.isError ? getApiErrorMessage(saveMutation.error) : null);

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

    const result = quotationManualEntrySchema.safeParse(values);
    if (!result.success) {
      setValidationMessage(result.error.issues[0]?.message ?? "Enter valid quotation details.");
      return;
    }

    try {
      await saveMutation.mutateAsync(payloadFromFormValues(result.data));
      setSuccessMessage("Structured quotation saved.");
    } catch {
      return;
    }
  }

  return (
    <section className="mt-3 rounded-md border p-3">
      <div className="space-y-3">
        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <h4 className="text-sm font-semibold">Structured quotation entry</h4>
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

        <div className="grid gap-3 lg:grid-cols-2">
          <label className="block text-sm font-medium">
            Buyer notes
            <Textarea
              aria-label="Buyer notes"
              className="mt-1 min-h-24"
              value={values.buyerNotes ?? ""}
              disabled={saveMutation.isPending}
              onChange={(event) => updateValue("buyerNotes", event.target.value)}
            />
          </label>
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
        </div>

        <QuotationLineItemsEditor
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
            Save structured quotation
          </Button>
          {successMessage ? <p className="text-sm text-emerald-700">{successMessage}</p> : null}
          {!canSave ? (
            <p className="text-sm text-muted-foreground">Structured entry is available only for sent or acknowledged invitations.</p>
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
