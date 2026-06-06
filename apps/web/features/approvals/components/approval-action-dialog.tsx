"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  Button,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  Textarea,
  Input,
} from "@cognify/ui";

type ApprovalActionDialogProps = {
  action: "approve" | "reject" | "request-changes";
  triggerLabel: string;
  title: string;
  confirmLabel: string;
  lockVersion: number;
  isPending: boolean;
  onSubmit: (values: { lockVersion: number; reason?: string; requestedFields?: string[] }) => Promise<void>;
};

export function ApprovalActionDialog({
  action,
  triggerLabel,
  title,
  confirmLabel,
  lockVersion,
  isPending,
  onSubmit,
}: ApprovalActionDialogProps) {
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [requestedFields, setRequestedFields] = useState("");
  const [error, setError] = useState<string | null>(null);
  const needsReason = action !== "approve";

  async function handleSubmit() {
    if (needsReason && !reason.trim()) {
      setError("Reason is required.");
      return;
    }

    setError(null);
    try {
      await onSubmit({
        lockVersion,
        reason: reason.trim() || undefined,
        requestedFields:
          action === "request-changes"
            ? requestedFields
                .split(",")
                .map((field) => field.trim())
                .filter(Boolean)
            : undefined,
      });
      setOpen(false);
      setReason("");
      setRequestedFields("");
    } catch (caught) {
      setError(errorMessage(caught));
    }
  }

  return (
    <>
      <Button
        variant={action === "reject" ? "destructive" : action === "approve" ? "default" : "outline"}
        onClick={() => setOpen(true)}
      >
        {triggerLabel}
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>
              {needsReason
                ? "Add the context needed for this decision."
                : "This records your approval decision for the assigned task."}
            </DialogDescription>
          </DialogHeader>
          {needsReason ? (
            <div className="space-y-4">
              <label className="space-y-1.5 text-sm font-medium">
                Reason
                <Textarea aria-label="Reason" value={reason} onChange={(event) => setReason(event.target.value)} />
              </label>
              {action === "request-changes" ? (
                <label className="space-y-1.5 text-sm font-medium">
                  Requested fields
                  <Input
                    aria-label="Requested fields"
                    value={requestedFields}
                    onChange={(event) => setRequestedFields(event.target.value)}
                    placeholder="attachments, businessJustification"
                  />
                </label>
              ) : null}
            </div>
          ) : null}
          {error ? (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button
              variant={action === "reject" ? "destructive" : "default"}
              disabled={isPending}
              onClick={handleSubmit}
            >
              {isPending ? "Working" : confirmLabel}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function errorMessage(caught: unknown): string {
  const directMessage = apiErrorMessage(caught);
  if (directMessage) {
    return directMessage;
  }

  if (caught && typeof caught === "object" && "data" in caught) {
    const wrappedMessage = apiErrorMessage(caught.data);
    if (wrappedMessage) {
      return wrappedMessage;
    }
  }

  if (caught instanceof Error) {
    return caught.message;
  }

  return "Approval action could not be completed. Refresh and try again.";
}

function apiErrorMessage(value: unknown): string | null {
  if (
    value &&
    typeof value === "object" &&
    "error" in value &&
    value.error &&
    typeof value.error === "object" &&
    "message" in value.error &&
    typeof value.error.message === "string"
  ) {
    return value.error.message;
  }

  return null;
}
