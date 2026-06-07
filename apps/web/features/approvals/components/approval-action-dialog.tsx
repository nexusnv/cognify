"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
  Button,
  Field,
  FieldContent,
  FieldDescription,
  FieldLabel,
  Input,
  Textarea,
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
    <AlertDialog open={open} onOpenChange={setOpen}>
      <AlertDialogTrigger asChild>
        <Button variant={action === "reject" ? "destructive" : action === "approve" ? "default" : "outline"}>
          {triggerLabel}
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent className="max-w-lg">
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>
            {needsReason
              ? "Record the decision reason before updating the workflow."
              : "This records your approval decision for the assigned task."}
          </AlertDialogDescription>
        </AlertDialogHeader>
        {needsReason ? (
          <div className="space-y-4">
            <Field>
              <FieldLabel htmlFor={`${action}-reason`}>Reason</FieldLabel>
              <FieldContent>
                <Textarea
                  id={`${action}-reason`}
                  aria-label="Reason"
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                />
              </FieldContent>
            </Field>
            {action === "request-changes" ? (
              <Field>
                <FieldLabel htmlFor="requested-fields">Requested fields</FieldLabel>
                <FieldContent>
                  <Input
                    id="requested-fields"
                    aria-label="Requested fields"
                    value={requestedFields}
                    onChange={(event) => setRequestedFields(event.target.value)}
                    placeholder="attachments, businessJustification"
                  />
                  <FieldDescription>Separate fields with commas.</FieldDescription>
                </FieldContent>
              </Field>
            ) : null}
          </div>
        ) : null}
        {error ? (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <Button
            variant={action === "reject" ? "destructive" : "default"}
            disabled={isPending}
            onClick={handleSubmit}
          >
            {isPending ? "Working" : confirmLabel}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
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
