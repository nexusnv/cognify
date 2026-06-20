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
  Input,
  Label,
} from "@cognify/ui";
import type { ApPaymentHandoff } from "@cognify/api-client/schemas";
import { ApPaymentHandoffStatus } from "@cognify/api-client/schemas";
import { toast } from "sonner";
import { useSchedulePaymentHandoff } from "../hooks/use-ap-payment-handoff-status";

type HandoffWithNumber = ApPaymentHandoff & { number?: string };

interface HandoffSchedulePanelProps {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}

export function HandoffSchedulePanel({
  handoff,
  onMutationSettled,
}: HandoffSchedulePanelProps) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [scheduledForDate, setScheduledForDate] = useState("");
  const [paymentReference, setPaymentReference] = useState("");
  const [error, setError] = useState<string | null>(null);

  const scheduleMutation = useSchedulePaymentHandoff(handoff.id);

  const canSchedule =
    handoff.status === ApPaymentHandoffStatus.draft ||
    handoff.status === ApPaymentHandoffStatus.ready ||
    handoff.status === ApPaymentHandoffStatus.exported;

  if (!canSchedule) {
    return null;
  }

  function handleOpen() {
    setScheduledForDate("");
    setPaymentReference("");
    setError(null);
    setDialogOpen(true);
  }

  function handleConfirm() {
    setError(null);
    scheduleMutation.mutate(
      {
        lockVersion: handoff.lockVersion,
        scheduledForDate: scheduledForDate || undefined,
        paymentReference: paymentReference.trim() || undefined,
      },
      {
        onSuccess: () => {
          toast.success("Handoff scheduled successfully");
          setDialogOpen(false);
          onMutationSettled();
        },
        onError: (err) => {
          const message = errorToMessage(err) ?? "Failed to schedule handoff.";
          setError(message);
        },
      },
    );
  }

  return (
    <div>
      <Button
        type="button"
        variant="outline"
        className="w-full"
        onClick={handleOpen}
        disabled={scheduleMutation.isPending}
      >
        {scheduleMutation.isPending ? "Scheduling..." : "Schedule payment"}
      </Button>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Schedule payment</DialogTitle>
            <DialogDescription>
              Set the scheduled payment date and reference for{" "}
              {handoff.number ?? handoff.id}.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="schedule-date">Scheduled for date</Label>
              <Input
                id="schedule-date"
                type="date"
                value={scheduledForDate}
                onChange={(e) => setScheduledForDate(e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="payment-reference">Payment reference</Label>
              <Input
                id="payment-reference"
                placeholder="e.g. Wire-2024-001"
                value={paymentReference}
                onChange={(e) => setPaymentReference(e.target.value)}
              />
            </div>

            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>

          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDialogOpen(false)}
              disabled={scheduleMutation.isPending}
            >
              Cancel
            </Button>
            <Button
              onClick={handleConfirm}
              disabled={scheduleMutation.isPending}
            >
              {scheduleMutation.isPending ? "Scheduling..." : "Confirm schedule"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function errorToMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null && "error" in error) {
    const apiError = (error as { error?: { message?: string } }).error;
    if (apiError?.message) return apiError.message;
  }
  if (error instanceof Error) return error.message;
  return null;
}
