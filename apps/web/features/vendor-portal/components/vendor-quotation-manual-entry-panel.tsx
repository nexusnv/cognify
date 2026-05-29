"use client";

import { useState } from "react";
import { getApiErrorMessage } from "@cognify/api-client";
import type {
  QuotationVendorPortal,
  SaveQuotationManualEntryRequestForVendor,
} from "@cognify/api-client/schemas";
import { Alert, AlertDescription, Button, Card, CardContent, CardHeader, CardTitle, Input, Textarea } from "@cognify/ui";
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
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [validationMessage, setValidationMessage] = useState<string | null>(null);

  return (
    <VendorQuotationManualEntryForm
      key={quotationFormKey(quotation)}
      token={token}
      quotation={quotation}
      successMessage={successMessage}
      validationMessage={validationMessage}
      setSuccessMessage={setSuccessMessage}
      setValidationMessage={setValidationMessage}
    />
  );
}

function VendorQuotationManualEntryForm({
  token,
  quotation,
  successMessage,
  validationMessage,
  setSuccessMessage,
  setValidationMessage,
}: {
  token: string;
  quotation: QuotationVendorPortal | null;
  successMessage: string | null;
  validationMessage: string | null;
  setSuccessMessage: (message: string | null) => void;
  setValidationMessage: (message: string | null) => void;
}) {
  const saveMutation = useVendorQuotationManualEntry(token);
  const [values, setValues] = useState<QuotationManualEntryFormValues>(() => formValuesFromQuotation(quotation));

  const canSave = quotation?.permissions.canEditManualEntry !== false;
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

    const result = quotationManualEntrySchema.safeParse({
      ...values,
      buyerNotes: null,
    });
    if (!result.success) {
      setValidationMessage(result.error.issues[0]?.message ?? "Enter valid quotation details.");
      return;
    }

    try {
      const payload = payloadFromFormValues(result.data);
      delete payload.buyerNotes;
      await saveMutation.mutateAsync(payload satisfies SaveQuotationManualEntryRequestForVendor);
      setSuccessMessage("Quotation details saved.");
    } catch {
      return;
    }
  }

  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="text-base">Structured quotation response</CardTitle>
      </CardHeader>
      <CardContent>
      <div className="space-y-3">
        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <p className={completeness ? "text-sm text-emerald-700" : "text-sm text-muted-foreground"}>{statusLabel}</p>
          </div>
        </div>

        <div className="grid gap-3 lg:grid-cols-3">
          <label className="block text-sm font-medium">
            Quotation reference
            <Input
              aria-label="Quotation reference"
              className="mt-1 min-h-11 w-full rounded-md border border-input bg-background px-3 text-base font-normal outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring"
              value={values.quotationReference ?? ""}
              disabled={saveMutation.isPending}
              onChange={(event) => updateValue("quotationReference", event.target.value)}
            />
          </label>
          <label className="block text-sm font-medium">
            Currency
            <Input
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
            <Input
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
          <Alert variant="destructive">
            <AlertDescription>{errorMessage}</AlertDescription>
          </Alert>
        ) : null}
      </div>
      </CardContent>
    </Card>
  );
}

function quotationFormKey(quotation: QuotationVendorPortal | null): string {
  if (!quotation) return "empty";

  return JSON.stringify({
    id: quotation.id,
    manualEntry: quotation.manualEntry,
    lineItems: quotation.lineItems,
  });
}
