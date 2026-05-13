"use client";

import { Plus, Trash2 } from "lucide-react";
import { useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { FormErrorSummary } from "@/components/forms/form-error-summary";
import { FormField } from "@/components/forms/form-field";
import {
  flattenZodFieldErrors,
  focusFirstInvalidField,
  withFieldIds,
} from "@/components/forms/validation-errors";
import { SubmitRequisitionDialog } from "../components/submit-requisition-dialog";
import { SubmissionChecklist } from "../components/submission-checklist";
import { useSaveRequisitionDraft } from "../hooks/use-save-requisition-draft";
import { useSubmitRequisition } from "../hooks/use-submit-requisition";
import { requisitionSubmitSchema } from "../schemas/requisition-form-schema";
import type { Requisition, RequisitionFormValues } from "../types/requisition-view-model";

const emptyLineItem = {
  name: "",
  description: "",
  quantity: 1,
  unit: "each",
  estimatedUnitPrice: 0,
  currency: "MYR",
};

const requisitionFieldIds: Record<string, string> = {
  title: "title",
  businessJustification: "business-justification",
  neededByDate: "needed-by",
  currency: "currency",
  lineItems: "line-items",
};

export function RequisitionForm({ initialRequisition }: { initialRequisition?: Requisition }) {
  const [values, setValues] = useState<RequisitionFormValues>({
    title: initialRequisition?.title ?? "",
    businessJustification: initialRequisition?.businessJustification ?? "",
    neededByDate: initialRequisition?.neededByDate ?? "",
    department: initialRequisition?.department ?? "",
    costCenter: initialRequisition?.costCenter ?? "",
    deliveryLocation: initialRequisition?.deliveryLocation ?? "",
    currency: initialRequisition?.currency ?? "MYR",
    lineItems: initialRequisition?.lineItems.length
      ? initialRequisition.lineItems
      : [{ ...emptyLineItem }],
  });
  const [requisitionId, setRequisitionId] = useState(initialRequisition?.id);
  const [status, setStatus] = useState(initialRequisition?.status ?? "draft");
  const [saveState, setSaveState] = useState<"unsaved" | "saving" | "saved" | "failed">("unsaved");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [submittedNotice, setSubmittedNotice] = useState(false);
  const formRef = useRef<HTMLFormElement>(null);
  const saveDraft = useSaveRequisitionDraft();
  const submitDraft = useSubmitRequisition();

  const errorSummary = useMemo(
    () => withFieldIds(flattenZodFieldErrors(errors), requisitionFieldIds),
    [errors],
  );
  const lineItemsErrorId = errors.lineItems?.[0] ? "line-items-error" : undefined;

  function updateValue(field: keyof RequisitionFormValues, value: string) {
    setSaveState("unsaved");
    setValues((current) => ({ ...current, [field]: value }));
  }

  function updateLineItem(
    index: number,
    field: keyof RequisitionFormValues["lineItems"][number],
    value: string,
  ) {
    setSaveState("unsaved");
    setValues((current) => ({
      ...current,
      lineItems: current.lineItems.map((item, itemIndex) =>
        itemIndex === index
          ? {
              ...item,
              [field]:
                field === "quantity" || field === "estimatedUnitPrice" ? Number(value) : value,
            }
          : item,
      ),
    }));
  }

  async function handleSaveDraft() {
    if (!values.title.trim()) {
      setErrors({ title: ["Title is required."] });
      window.setTimeout(() => focusFirstInvalidField(formRef.current ?? document), 0);
      return undefined;
    }

    setSaveState("saving");
    setErrors({});

    try {
      const requisition = await saveDraft.mutateAsync({ requisitionId, values });
      setRequisitionId(requisition.id);
      setStatus(requisition.status);
      setSaveState("saved");
      return requisition;
    } catch {
      setSaveState("failed");
      return undefined;
    }
  }

  async function handleSubmitAttempt() {
    const result = requisitionSubmitSchema.safeParse(values);

    if (!result.success) {
      setErrors(result.error.flatten().fieldErrors);
      window.setTimeout(() => focusFirstInvalidField(formRef.current ?? document), 0);
      return;
    }

    setErrors({});
    setConfirmOpen(true);
  }

  async function handleConfirmSubmit() {
    try {
      const requisition = requisitionId ? undefined : await handleSaveDraft();
      const id = requisitionId ?? requisition?.id;
      if (!id) return;

      const response = await submitDraft.mutateAsync(id);
      setStatus(response.data.status);
      setRequisitionId(response.data.id);
      setConfirmOpen(false);
      setSubmittedNotice(true);
      toast.success("Requisition submitted");
    } catch (error) {
      const message = error instanceof Error ? error.message : "Unable to submit requisition";
      toast.error(message);
    }
  }

  return (
    <form ref={formRef} className="space-y-5" onSubmit={(event) => event.preventDefault()}>
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">New requisition</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Create a draft, review the checklist, then submit for review.
          </p>
          <p className="mt-2 text-sm" aria-live="polite">
            {submittedNotice ? "Requisition submitted" : saveStateLabel(saveState)}
          </p>
          <p className="mt-1 text-sm font-medium">
            {status === "submitted" ? "Submitted" : "Draft"}
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            className="min-h-11 rounded-md border px-4 text-sm font-medium disabled:opacity-50"
            onClick={handleSaveDraft}
            disabled={saveDraft.isPending || status !== "draft"}
          >
            Save draft
          </button>
          <button
            type="button"
            className="min-h-11 rounded-md bg-foreground px-4 text-sm font-medium text-background disabled:opacity-50"
            onClick={handleSubmitAttempt}
            disabled={submitDraft.isPending || status !== "draft"}
          >
            Submit
          </button>
        </div>
      </div>

      <FormErrorSummary
        title="Complete the highlighted fields before submitting."
        errors={errorSummary}
      />

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="space-y-5">
          <section className="space-y-3 rounded-md border p-4">
            <h2 className="text-base font-semibold">Request summary</h2>
            <FormField htmlFor="title" label="Title" error={errors.title?.[0]} required>
              <input
                id="title"
                className="min-h-11 w-full rounded-md border px-3 text-base"
                value={values.title}
                aria-invalid={Boolean(errors.title)}
                onChange={(event) => updateValue("title", event.target.value)}
              />
            </FormField>
            <FormField
              htmlFor="needed-by"
              label="Needed by"
              error={errors.neededByDate?.[0]}
              required
            >
              <input
                id="needed-by"
                type="date"
                className="min-h-11 w-full rounded-md border px-3 text-base"
                value={values.neededByDate}
                aria-invalid={Boolean(errors.neededByDate)}
                onChange={(event) => updateValue("neededByDate", event.target.value)}
              />
            </FormField>
            <FormField
              htmlFor="business-justification"
              label="Business justification"
              error={errors.businessJustification?.[0]}
              required
            >
              <textarea
                id="business-justification"
                className="min-h-28 w-full rounded-md border px-3 py-2 text-base"
                value={values.businessJustification}
                aria-invalid={Boolean(errors.businessJustification)}
                onChange={(event) => updateValue("businessJustification", event.target.value)}
              />
            </FormField>
          </section>

          <section id="line-items" className="space-y-3 rounded-md border p-4">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-base font-semibold">Line items</h2>
              <button
                type="button"
                className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm font-medium"
                onClick={() =>
                  setValues((current) => ({
                    ...current,
                    lineItems: [...current.lineItems, { ...emptyLineItem }],
                  }))
                }
              >
                <Plus className="h-4 w-4" aria-hidden="true" />
                Add item
              </button>
            </div>
            <div className="space-y-4">
              {values.lineItems.map((item, index) => (
                <div key={index} className="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                  <FormField htmlFor={`item-name-${index}`} label={`Item name ${index + 1}`}>
                    <input
                      id={`item-name-${index}`}
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.name}
                      aria-invalid={Boolean(errors.lineItems)}
                      aria-describedby={lineItemsErrorId}
                      onChange={(event) => updateLineItem(index, "name", event.target.value)}
                    />
                  </FormField>
                  <FormField htmlFor={`quantity-${index}`} label={`Quantity ${index + 1}`}>
                    <input
                      id={`quantity-${index}`}
                      type="number"
                      min="0"
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.quantity}
                      aria-invalid={Boolean(errors.lineItems)}
                      aria-describedby={lineItemsErrorId}
                      onChange={(event) => updateLineItem(index, "quantity", event.target.value)}
                    />
                  </FormField>
                  <FormField htmlFor={`unit-${index}`} label={`Unit ${index + 1}`}>
                    <input
                      id={`unit-${index}`}
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.unit}
                      aria-invalid={Boolean(errors.lineItems)}
                      aria-describedby={lineItemsErrorId}
                      onChange={(event) => updateLineItem(index, "unit", event.target.value)}
                    />
                  </FormField>
                  <FormField
                    htmlFor={`unit-price-${index}`}
                    label={`Estimated unit price ${index + 1}`}
                  >
                    <input
                      id={`unit-price-${index}`}
                      type="number"
                      min="0"
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.estimatedUnitPrice}
                      aria-invalid={Boolean(errors.lineItems)}
                      aria-describedby={lineItemsErrorId}
                      onChange={(event) =>
                        updateLineItem(index, "estimatedUnitPrice", event.target.value)
                      }
                    />
                  </FormField>
                  <FormField htmlFor={`currency-${index}`} label={`Currency ${index + 1}`}>
                    <select
                      id={`currency-${index}`}
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.currency}
                      onChange={(event) => updateLineItem(index, "currency", event.target.value)}
                    >
                      <option value="MYR">MYR</option>
                      <option value="USD">USD</option>
                      <option value="SGD">SGD</option>
                    </select>
                  </FormField>
                  <button
                    type="button"
                    className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium md:self-end"
                    onClick={() =>
                      setValues((current) => ({
                        ...current,
                        lineItems: current.lineItems.filter((_, itemIndex) => itemIndex !== index),
                      }))
                    }
                    aria-label={`Remove line item ${index + 1}`}
                  >
                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                    Remove
                  </button>
                </div>
              ))}
            </div>
            {errors.lineItems?.[0] ? (
              <p id="line-items-error" className="text-sm text-red-700">
                {errors.lineItems[0]}
              </p>
            ) : null}
          </section>

          <section className="space-y-3 rounded-md border p-4">
            <h2 className="text-base font-semibold">Optional context</h2>
            <div className="grid gap-3 md:grid-cols-2">
              <FormField htmlFor="department" label="Department">
                <input
                  id="department"
                  className="min-h-11 w-full rounded-md border px-3 text-base"
                  value={values.department}
                  onChange={(event) => updateValue("department", event.target.value)}
                />
              </FormField>
              <FormField htmlFor="cost-center" label="Cost center">
                <input
                  id="cost-center"
                  className="min-h-11 w-full rounded-md border px-3 text-base"
                  value={values.costCenter}
                  onChange={(event) => updateValue("costCenter", event.target.value)}
                />
              </FormField>
              <div className="md:col-span-2">
                <FormField htmlFor="delivery-location" label="Delivery location">
                  <textarea
                    id="delivery-location"
                    className="min-h-20 w-full rounded-md border px-3 py-2 text-base"
                    value={values.deliveryLocation}
                    onChange={(event) => updateValue("deliveryLocation", event.target.value)}
                  />
                </FormField>
              </div>
            </div>
          </section>
        </div>
        <SubmissionChecklist values={values} />
      </div>

      <SubmitRequisitionDialog
        open={confirmOpen}
        values={values}
        isSubmitting={submitDraft.isPending}
        onCancel={() => setConfirmOpen(false)}
        onConfirm={handleConfirmSubmit}
      />
    </form>
  );
}

function saveStateLabel(saveState: "unsaved" | "saving" | "saved" | "failed") {
  if (saveState === "saving") return "Saving";
  if (saveState === "saved") return "Saved";
  if (saveState === "failed") return "Save failed";
  return "Unsaved";
}
