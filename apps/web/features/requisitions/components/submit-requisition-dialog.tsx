"use client";

import { Button, Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@cognify/ui";
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
  return (
    <Dialog open={open} onOpenChange={(nextOpen) => (!nextOpen ? onCancel() : undefined)}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Submit requisition?</DialogTitle>
          <DialogDescription>
            Submitted requisitions are locked for requester edits in this first workflow slice.
          </DialogDescription>
        </DialogHeader>
        <div>
          <SubmissionChecklist values={values} />
        </div>
        <DialogFooter className="flex-col-reverse sm:flex-row">
          <DialogClose asChild>
            <Button variant="outline" type="button">
              Keep editing
            </Button>
          </DialogClose>
          <Button type="button" onClick={onConfirm} disabled={isSubmitting}>
            {isSubmitting ? "Submitting" : "Submit requisition"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
