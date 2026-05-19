"use client";

import { useState } from "react";
import { Button, Textarea } from "@cognify/ui";

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
      {!open ? null : (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="w-full max-w-lg rounded-md border bg-background p-5 shadow-lg"
          >
            <h2 className="text-lg font-semibold">{title}</h2>
            {needsReason ? (
              <div className="mt-4 space-y-4">
                <label className="block text-sm font-medium">
                  Reason
                  <Textarea
                    aria-label="Reason"
                    className="mt-1"
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                  />
                </label>
                {action === "request-changes" ? (
                  <label className="block text-sm font-medium">
                    Requested fields
                    <input
                      aria-label="Requested fields"
                      className="mt-1 min-h-11 w-full rounded-md border px-3 text-base font-normal"
                      value={requestedFields}
                      onChange={(event) => setRequestedFields(event.target.value)}
                      placeholder="attachments, businessJustification"
                    />
                  </label>
                ) : null}
              </div>
            ) : (
              <p className="mt-2 text-sm text-muted-foreground">
                This records your approval decision for the assigned task.
              </p>
            )}
            {error ? <p className="mt-4 text-sm text-red-700">{error}</p> : null}
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
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
            </div>
          </div>
        </div>
      )}
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
