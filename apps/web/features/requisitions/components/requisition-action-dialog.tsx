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
  DialogTrigger,
  Input,
  Label,
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
  action,
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
      setError(submitError instanceof Error ? submitError.message : "Unable to complete this action.");
    }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant={triggerVariant}>{triggerLabel}</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor={`reason-${action}`}>Reason</Label>
            <Textarea
              id={`reason-${action}`}
              aria-label="Reason"
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </div>
          {requireRequestedFields ? (
            <div className="space-y-2">
              <Label htmlFor={`requested-fields-${action}`}>Requested fields</Label>
              <Input
                id={`requested-fields-${action}`}
                aria-label="Requested fields"
                value={requestedFields}
                onChange={(event) => setRequestedFields(event.target.value)}
                placeholder="lineItems, deliveryLocation"
              />
            </div>
          ) : null}
          {error ? <p className="text-sm text-red-700">{error}</p> : null}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => setOpen(false)}>
            Keep editing
          </Button>
          <Button variant={triggerVariant === "destructive" ? "destructive" : "default"} onClick={handleSubmit} disabled={isPending}>
            {isPending ? "Working" : confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
