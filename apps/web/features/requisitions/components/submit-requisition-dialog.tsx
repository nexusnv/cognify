"use client";

import { X } from "lucide-react";
import { SubmissionChecklist } from "./submission-checklist";
import type { RequisitionFormValues } from "../types/requisition-view-model";

export function SubmitRequisitionDialog({
  open,
  values,
  isSubmitting,
  onCancel,
  onConfirm,
}: {
  open: boolean;
  values: RequisitionFormValues;
  isSubmitting: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="submit-requisition-title"
        className="w-full max-w-lg rounded-md border bg-background p-5 shadow-lg"
      >
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 id="submit-requisition-title" className="text-lg font-semibold">
              Submit requisition?
            </h2>
            <p className="mt-2 text-sm text-muted-foreground">
              Submitted requisitions are locked for requester edits in this first workflow slice.
            </p>
          </div>
          <button
            type="button"
            className="inline-flex h-11 w-11 items-center justify-center rounded-md border"
            aria-label="Keep editing"
            onClick={onCancel}
          >
            <X className="h-4 w-4" aria-hidden="true" />
          </button>
        </div>
        <div className="mt-4">
          <SubmissionChecklist values={values} />
        </div>
        <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" className="min-h-11 rounded-md border px-4 text-sm font-medium" onClick={onCancel}>
            Keep editing
          </button>
          <button
            type="button"
            className="min-h-11 rounded-md bg-foreground px-4 text-sm font-medium text-background disabled:opacity-50"
            onClick={onConfirm}
            disabled={isSubmitting}
          >
            {isSubmitting ? "Submitting" : "Submit requisition"}
          </button>
        </div>
      </div>
    </div>
  );
}
