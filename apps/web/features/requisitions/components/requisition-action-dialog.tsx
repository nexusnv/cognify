"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
  Button,
  Input,
  Textarea,
} from "@cognify/ui";

type RequisitionActionDialogProps = {
  action: "request-changes" | "withdraw" | "cancel";
  title: string;
  description: string;
  confirmLabel: string;
  triggerLabel: string;
  triggerVariant?: "default" | "outline" | "destructive";
  requireRequestedFields?: boolean;
  isPending: boolean;
  onSubmit: (values: { reason: string; requestedFields: string[] }) => Promise<void> | void;
};

export function RequisitionActionDialog({
  title,
  description,
  confirmLabel,
  triggerLabel,
  triggerVariant = "outline",
  requireRequestedFields = false,
  isPending,
  onSubmit,
}: RequisitionActionDialogProps) {
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [requestedFields, setRequestedFields] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    if (!reason.trim()) {
      setError("Reason is required.");
      return;
    }

    setError(null);
    try {
      await onSubmit({
        reason: reason.trim(),
        requestedFields: requireRequestedFields
          ? requestedFields
              .split(",")
              .map((field) => field.trim())
              .filter(Boolean)
          : [],
      });
      setOpen(false);
      setReason("");
      setRequestedFields("");
    } catch (submitError) {
      setError(
        submitError instanceof Error ? submitError.message : "Unable to complete this action.",
      );
    }
  }

  return (
    <AlertDialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (isPending) {
          return;
        }
        setOpen(nextOpen);
        if (!nextOpen) {
          setReason("");
          setRequestedFields("");
          setError(null);
        }
      }}
    >
      <AlertDialogTrigger asChild>
        <Button variant={triggerVariant}>{triggerLabel}</Button>
      </AlertDialogTrigger>
      <AlertDialogContent data-action={requireRequestedFields ? "request-changes" : undefined}>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        <div className="space-y-4">
          <label className="grid gap-1.5 text-sm font-medium">
            Reason
            <Textarea aria-label="Reason" value={reason} onChange={(event) => setReason(event.target.value)} />
          </label>
          {requireRequestedFields ? (
            <label className="grid gap-1.5 text-sm font-medium">
              Requested fields
              <Input
                aria-label="Requested fields"
                value={requestedFields}
                onChange={(event) => setRequestedFields(event.target.value)}
                placeholder="lineItems, deliveryLocation"
              />
            </label>
          ) : null}
          {error ? (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ) : null}
        </div>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={isPending}>Keep editing</AlertDialogCancel>
          <AlertDialogAction
            onClick={(event) => {
              event.preventDefault();
              void handleSubmit();
            }}
            disabled={isPending}
            className={
              triggerVariant === "destructive"
                ? "bg-destructive text-destructive-foreground hover:bg-destructive/90"
                : undefined
            }
          >
            {isPending ? "Working" : confirmLabel}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
