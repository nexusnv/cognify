"use client";

import { useState } from "react";
import {
  Button,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
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
      setError(submitError instanceof Error ? submitError.message : "Unable to complete this action right now.");
      return;
    }

    setOpen(false);
    setReason("");
  }

  return (
    <>
      <Button variant={triggerVariant} onClick={() => setOpen(true)}>
        {triggerLabel}
      </Button>
      <Dialog
        open={open}
        onOpenChange={(nextOpen) => {
          setOpen(nextOpen);
          if (!nextOpen) {
            setError(null);
            setReason("");
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>{description}</DialogDescription>
          </DialogHeader>
          {action === "cancel" ? (
            <label className="space-y-1.5 text-sm font-medium">
              Reason
              <Textarea
                aria-label="Reason"
                value={reason}
                onChange={(event) => setReason(event.target.value)}
              />
            </label>
          ) : null}
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>
              Keep editing
            </Button>
            <Button
              variant={triggerVariant === "destructive" ? "destructive" : "default"}
              onClick={() => void handleSubmit()}
              disabled={isPending}
            >
              {isPending ? "Working" : confirmLabel}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
