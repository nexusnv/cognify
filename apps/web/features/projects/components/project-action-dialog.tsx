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
  Textarea,
} from "@cognify/ui";

export function ProjectActionDialog({
  action,
  title,
  description,
  confirmLabel,
  triggerLabel,
  triggerVariant = "outline",
  isPending,
  onSubmit,
}: {
  action: "activate" | "hold" | "resume" | "complete" | "cancel";
  title: string;
  description: string;
  confirmLabel: string;
  triggerLabel: string;
  triggerVariant?: "default" | "outline" | "destructive";
  isPending: boolean;
  onSubmit: (values: { reason?: string }) => Promise<void> | void;
}) {
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    if (action === "cancel" && !reason.trim()) {
      setError("Reason is required.");
      return;
    }

    setError(null);
    try {
      await onSubmit({ reason: reason.trim() || undefined });
    } catch (submitError) {
      setError(
        submitError instanceof Error
          ? submitError.message
          : "Unable to complete this action right now.",
      );
      return;
    }

    setOpen(false);
    setReason("");
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
          setError(null);
        }
      }}
    >
      <AlertDialogTrigger asChild>
        <Button variant={triggerVariant}>{triggerLabel}</Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        {action === "cancel" ? (
          <label className="grid gap-1.5 text-sm font-medium">
            Reason
            <Textarea
              aria-label="Reason"
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </label>
        ) : null}
        {error ? (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}
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
