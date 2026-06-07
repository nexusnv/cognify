"use client";

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@cognify/ui";
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
    <AlertDialog open={open}>
      <AlertDialogContent className="max-w-lg">
        <AlertDialogHeader className="text-left">
          <AlertDialogTitle>Submit requisition?</AlertDialogTitle>
          <AlertDialogDescription>
            Submitted requisitions are locked for requester edits in this first workflow slice.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div className="mt-4">
          <SubmissionChecklist values={values} />
        </div>
        <AlertDialogFooter className="mt-5">
          <AlertDialogCancel onClick={onCancel}>
            Keep editing
          </AlertDialogCancel>
          <AlertDialogAction
            onClick={onConfirm}
            disabled={isSubmitting}
          >
            {isSubmitting ? "Submitting" : "Submit requisition"}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
