"use client";

import { useState } from "react";
import type { FormEvent } from "react";
import { getApiErrorCode, getApiErrorMessage, getApiValidationErrors } from "@cognify/api-client";
import { Alert, AlertDescription, Button, Card, CardContent, CardHeader, CardTitle, Input, Textarea } from "@cognify/ui";
import { FormErrorSummary, type FormSummaryError } from "@/components/forms/form-error-summary";
import { FormField } from "@/components/forms/form-field";
import { RfqStatusBadge } from "./rfq-status-badge";
import {
  RfqLineItemsTable,
  type RfqLineItemEditorValue,
} from "./rfq-line-items-table";
import {
  RfqRequiredDocumentsEditor,
  type RfqRequiredDocumentEditorValue,
} from "./rfq-required-documents-editor";
import { rfqCancelSchema, rfqDraftFormSchema } from "../schemas/rfq-draft-schema";
import type { RfqCancelValues, RfqDraftFormValues } from "../schemas/rfq-draft-schema";
import type { RfqDraft } from "../types/rfq-view-model";
import type { ZodIssue } from "zod";

type FormState = {
  title: string;
  scopeSummary: string;
  responseDueAt: string;
  responseInstructions: string;
  requiredDocuments: RfqRequiredDocumentEditorValue[];
  lineItems: RfqLineItemEditorValue[];
  evaluationNotes: string;
  internalNotes: string;
  cancelReason: string;
};

type FieldErrors = Record<string, string[]>;

export function RfqDraftForm({
  rfq,
  canUpdate,
  canCancel,
  isSaving,
  isCancelling,
  onSave,
  onCancel,
}: {
  rfq: RfqDraft;
  canUpdate: boolean;
  canCancel: boolean;
  isSaving: boolean;
  isCancelling: boolean;
  onSave: (values: RfqDraftFormValues) => Promise<void>;
  onCancel: (values: RfqCancelValues) => Promise<void>;
}) {
  const [values, setValues] = useState<FormState>(() => buildFormState(rfq));
  const [saveError, setSaveError] = useState<string | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [cancelFieldError, setCancelFieldError] = useState<string | null>(null);

  const readOnly = !canUpdate || rfq.status === "cancelled";
  const canSubmit = canUpdate && rfq.status !== "cancelled";

  function updateValue<K extends keyof FormState>(key: K, value: FormState[K]) {
    setValues((current) => ({ ...current, [key]: value }));
  }

  async function handleSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaveError(null);
    setFieldErrors({});

    const responseDueAt = parseResponseDueAt(values.responseDueAt);
    if (!responseDueAt.ok) {
      setFieldErrors({ responseDueAt: [responseDueAt.error] });
      setSaveError("Resolve the highlighted RFQ fields before saving.");
      return;
    }

    const payload = buildSavePayload(values, responseDueAt.value);
    const parsed = rfqDraftFormSchema.safeParse(payload);
    if (!parsed.success) {
      setFieldErrors(mapZodIssues(parsed.error.issues));
      setSaveError("Resolve the highlighted RFQ fields before saving.");
      return;
    }

    try {
      await onSave(parsed.data);
    } catch (error) {
      const validationErrors = getApiValidationErrors(error);
      if (Object.keys(validationErrors).length > 0) {
        setFieldErrors(validationErrors);
        setSaveError("Resolve the highlighted RFQ fields before saving.");
        return;
      }

      const code = getApiErrorCode(error);
      if (code === "forbidden" || code === "unauthenticated") {
        setSaveError("You do not have permission to save this RFQ draft.");
        return;
      }

      if (code === "not_found") {
        setSaveError("This RFQ could not be found.");
        return;
      }

      if (code === "conflict" || code === "draft_conflict") {
        setSaveError("This RFQ changed while you were editing it. Reload and try again.");
        return;
      }

      if (code === "server_error" || code === "too_many_requests") {
        setSaveError("Unable to save this RFQ draft right now. Try again.");
        return;
      }

      setSaveError(getApiErrorMessage(error));
    }
  }

  async function handleCancel() {
    setCancelError(null);
    setCancelFieldError(null);

    const parsed = rfqCancelSchema.safeParse({ cancelReason: values.cancelReason });
    if (!parsed.success) {
      setCancelFieldError(parsed.error.issues[0]?.message ?? "Cancel reason is required.");
      setCancelError("Resolve the highlighted cancellation field before continuing.");
      return;
    }

    try {
      await onCancel(parsed.data);
    } catch (error) {
      const validationErrors = getApiValidationErrors(error);
      if (validationErrors.cancelReason?.[0]) {
        setCancelFieldError(validationErrors.cancelReason[0]);
        setCancelError("Resolve the highlighted cancellation field before continuing.");
        return;
      }

      const code = getApiErrorCode(error);
      if (code === "forbidden" || code === "unauthenticated") {
        setCancelError("You do not have permission to cancel this RFQ draft.");
        return;
      }

      if (code === "not_found") {
        setCancelError("This RFQ could not be found.");
        return;
      }

      if (code === "conflict" || code === "draft_conflict") {
        setCancelError("This RFQ changed while you were cancelling it. Reload and try again.");
        return;
      }

      setCancelError(getApiErrorMessage(error));
    }
  }

  const saveSummaryErrors = buildSummaryErrors(fieldErrors);
  const activeCancelError = cancelFieldError ? [cancelFieldError] : [];

  return (
    <form className="space-y-5" onSubmit={handleSave} noValidate>
      {saveError ? (
        <Alert variant="destructive">
          <AlertDescription>{saveError}</AlertDescription>
        </Alert>
      ) : null}

      <FormErrorSummary title="Resolve the highlighted RFQ fields before saving." errors={saveSummaryErrors} />

      <Card id="overview">
        <CardHeader>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="space-y-1">
            <h2 className="text-base font-semibold">Draft details</h2>
            <p className="text-sm text-muted-foreground">
              Shape the RFQ package before vendor invitations are introduced.
            </p>
          </div>
          <RfqStatusBadge status={rfq.status} size="compact" />
        </div>
        </CardHeader>
        <CardContent>
          {rfq.status === "cancelled" ? (
            <Alert variant="destructive">
              <AlertDescription>
                <p className="font-medium">This RFQ draft is cancelled.</p>
                <p className="mt-1">{rfq.cancelReason ?? "No cancellation reason was recorded."}</p>
              </AlertDescription>
            </Alert>
          ) : null}

          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <FormField htmlFor="title" label="Title" error={fieldErrors.title?.[0]} required>
              <Input
                id="title"
                value={values.title}
                disabled={readOnly}
                onChange={(event) => updateValue("title", event.target.value)}
              />
            </FormField>

            <FormField
              htmlFor="responseDueAt"
              label="Response due at"
              description="Leave blank if the due date is not yet set."
              error={fieldErrors.responseDueAt?.[0]}
            >
              <Input
                id="responseDueAt"
                type="datetime-local"
                value={values.responseDueAt}
                disabled={readOnly}
                onChange={(event) => updateValue("responseDueAt", event.target.value)}
              />
            </FormField>

            <div className="lg:col-span-2">
              <FormField
                htmlFor="scopeSummary"
                label="Scope summary"
                error={fieldErrors.scopeSummary?.[0]}
              >
                <Textarea
                  id="scopeSummary"
                  value={values.scopeSummary}
                  disabled={readOnly}
                  className="min-h-32 text-sm"
                  onChange={(event) => updateValue("scopeSummary", event.target.value)}
                />
              </FormField>
            </div>

            <div className="lg:col-span-2">
              <FormField
                htmlFor="responseInstructions"
                label="Response instructions"
                error={fieldErrors.responseInstructions?.[0]}
              >
                <Textarea
                  id="responseInstructions"
                  value={values.responseInstructions}
                  disabled={readOnly}
                  className="min-h-32 text-sm"
                  onChange={(event) => updateValue("responseInstructions", event.target.value)}
                />
              </FormField>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card id="line-items">
        <CardHeader>
          <CardTitle className="text-base">Line items</CardTitle>
          <p className="text-sm text-muted-foreground">
            Copy the source requisition lines and refine them for the RFQ package.
          </p>
        </CardHeader>
        <CardContent>
          <RfqLineItemsTable
            items={values.lineItems}
            errors={fieldErrors}
            disabled={readOnly}
            onChange={(lineItems) => updateValue("lineItems", lineItems)}
          />
        </CardContent>
      </Card>

      <Card id="documents">
        <CardHeader>
          <CardTitle className="text-base">Required documents</CardTitle>
          <p className="text-sm text-muted-foreground">
            Define the declarations and files vendors must include with their response.
          </p>
        </CardHeader>
        <CardContent>
          <RfqRequiredDocumentsEditor
            items={values.requiredDocuments}
            errors={fieldErrors}
            disabled={readOnly}
            onChange={(requiredDocuments) => updateValue("requiredDocuments", requiredDocuments)}
          />
        </CardContent>
      </Card>

      <Card id="notes">
        <CardHeader>
          <CardTitle className="text-base">Notes</CardTitle>
          <p className="text-sm text-muted-foreground">
            Keep evaluation and internal buyer notes in the draft workspace.
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 lg:grid-cols-2">
            <FormField
              htmlFor="evaluationNotes"
              label="Evaluation notes"
              error={fieldErrors.evaluationNotes?.[0]}
            >
              <Textarea
                id="evaluationNotes"
                value={values.evaluationNotes}
                disabled={readOnly}
                className="min-h-36 text-sm"
                onChange={(event) => updateValue("evaluationNotes", event.target.value)}
              />
            </FormField>

            <FormField
              htmlFor="internalNotes"
              label="Internal notes"
              error={fieldErrors.internalNotes?.[0]}
            >
              <Textarea
                id="internalNotes"
                value={values.internalNotes}
                disabled={readOnly}
                className="min-h-36 text-sm"
                onChange={(event) => updateValue("internalNotes", event.target.value)}
              />
            </FormField>
          </div>
        </CardContent>
      </Card>

      {canSubmit ? (
        <div className="flex flex-col gap-3 rounded-lg bg-muted/30 p-4 sm:flex-row sm:items-start sm:justify-between">
          <p className="text-sm text-muted-foreground">
            Save the current RFQ draft after you finish editing the sourcing package.
          </p>
          <Button type="submit" disabled={isSaving || isCancelling}>
            {isSaving ? "Saving" : "Save changes"}
          </Button>
        </div>
      ) : (
        <p className="rounded-lg bg-muted/30 p-4 text-sm text-muted-foreground">This RFQ draft is read-only.</p>
      )}

      {canCancel ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base text-red-950">Cancel draft</CardTitle>
            <p className="text-sm text-red-900">
              Cancellation is terminal and prevents further editing.
            </p>
          </CardHeader>
          <CardContent className="space-y-4">
            {cancelError ? (
              <Alert variant="destructive">
                <AlertDescription>{cancelError}</AlertDescription>
              </Alert>
            ) : null}

            <div className="max-w-2xl space-y-4">
              <FormField
                htmlFor="cancelReason"
                label="Cancel reason"
                error={activeCancelError[0]}
                required
              >
                <Textarea
                  id="cancelReason"
                  value={values.cancelReason}
                  disabled={isCancelling || readOnly}
                  className="min-h-28 text-sm"
                  onChange={(event) => updateValue("cancelReason", event.target.value)}
                />
              </FormField>
              <Button
                type="button"
                variant="destructive"
                onClick={() => void handleCancel()}
                disabled={isCancelling || readOnly}
              >
                {isCancelling ? "Cancelling" : "Cancel draft"}
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}
    </form>
  );
}

function buildFormState(rfq: RfqDraft): FormState {
  return {
    title: rfq.title,
    scopeSummary: rfq.scopeSummary ?? "",
    responseDueAt: toDateTimeLocalValue(rfq.responseDueAt),
    responseInstructions: rfq.responseInstructions ?? "",
    requiredDocuments: (rfq.requiredDocuments ?? []).map((document, index) => ({
      id: `document-${index}`,
      key: document.key,
      label: document.label,
      required: document.required,
    })),
    lineItems: (rfq.lineItems ?? []).map((item, index) => ({
      id: `line-item-${index}`,
      name: item.name ?? "",
      description: item.description,
      quantity: String(item.quantity),
      unit: item.unit,
      notes: item.notes ?? "",
      estimatedUnitPrice: item.estimatedUnitPrice,
      currency: item.currency,
    })),
    evaluationNotes: rfq.evaluationNotes ?? "",
    internalNotes: rfq.internalNotes ?? "",
    cancelReason: rfq.cancelReason ?? "",
  };
}

function buildSavePayload(values: FormState, responseDueAt: string | null): RfqDraftFormValues {
  return {
    title: values.title,
    scopeSummary: emptyStringToNull(values.scopeSummary),
    responseDueAt,
    responseInstructions: emptyStringToNull(values.responseInstructions),
    requiredDocuments: values.requiredDocuments.map((document) => ({
      key: document.key,
      label: document.label,
      required: document.required,
    })),
    lineItems: values.lineItems.map((item) => ({
      description: item.description,
      quantity: Number(item.quantity),
      unit: item.unit,
      notes: emptyStringToNull(item.notes),
    })),
    evaluationNotes: emptyStringToNull(values.evaluationNotes),
    internalNotes: emptyStringToNull(values.internalNotes),
  };
}

function buildSummaryErrors(errors: FieldErrors): FormSummaryError[] {
  return Object.entries(errors).flatMap(([field, messages]) =>
    (messages ?? []).map((message) => ({
      field,
      fieldId: toFieldId(field),
      message,
    })),
  );
}

function mapZodIssues(issues: ZodIssue[]): FieldErrors {
  const errors: FieldErrors = {};
  for (const issue of issues) {
    const field = issue.path.map((segment) => String(segment)).join(".");
    if (!errors[field]) {
      errors[field] = [];
    }
    errors[field]!.push(issue.message);
  }

  return errors;
}

function emptyStringToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

function toDateTimeLocalValue(value: string | null | undefined): string {
  if (!value) return "";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";

  const offsetMs = date.getTimezoneOffset() * 60_000;
  const localDate = new Date(date.getTime() - offsetMs);
  return localDate.toISOString().slice(0, 16);
}

function parseResponseDueAt(value: string): { ok: true; value: string | null } | { ok: false; error: string } {
  if (!value.trim()) {
    return { ok: true, value: null };
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return { ok: false, error: "Enter a valid response due date and time." };
  }

  return { ok: true, value: date.toISOString() };
}

function toFieldId(field: string) {
  if (field === "lineItems") {
    return "line-items";
  }

  if (field === "requiredDocuments") {
    return "documents";
  }

  if (field.startsWith("lineItems.")) {
    return `line-items-${field.slice("lineItems.".length).replaceAll(".", "-")}`;
  }

  if (field.startsWith("requiredDocuments.")) {
    return `required-documents-${field.slice("requiredDocuments.".length).replaceAll(".", "-")}`;
  }

  return field;
}
