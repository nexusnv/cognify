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
  Textarea,
} from "@cognify/ui";
import type { ApPaymentHandoff } from "@cognify/api-client/schemas";
import { ApPaymentHandoffStatus, ApPaymentFailureCode } from "@cognify/api-client/schemas";
import { toast } from "sonner";
import {
  useMarkPaymentHandoffPaid,
  useClosePaymentHandoffWithVariance,
  useMarkPaymentHandoffFailed,
  useVoidPaymentHandoff,
  useReschedulePaymentHandoff,
} from "../hooks/use-ap-payment-handoff-status";

type HandoffWithNumber = ApPaymentHandoff & { number?: string };

interface HandoffPaymentActionsPanelProps {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}

export function HandoffPaymentActionsPanel({
  handoff,
  onMutationSettled,
}: HandoffPaymentActionsPanelProps) {
  return (
    <div className="flex flex-wrap gap-2">
      {handoff.status === ApPaymentHandoffStatus.scheduled && (
        <>
          <MarkPaidButton handoff={handoff} onMutationSettled={onMutationSettled} />
          <MarkFailedButton handoff={handoff} onMutationSettled={onMutationSettled} />
          <VoidButton handoff={handoff} onMutationSettled={onMutationSettled} />
        </>
      )}

      {handoff.status === ApPaymentHandoffStatus.paid && (
        <>
          <CloseWithVarianceButton handoff={handoff} onMutationSettled={onMutationSettled} />
          <MarkFailedButton handoff={handoff} onMutationSettled={onMutationSettled} />
        </>
      )}

      {handoff.status === ApPaymentHandoffStatus.failed && (
        <RescheduleButton handoff={handoff} onMutationSettled={onMutationSettled} />
      )}
    </div>
  );
}

function MarkPaidButton({
  handoff,
  onMutationSettled,
}: {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [remittanceReference, setRemittanceReference] = useState("");
  const [error, setError] = useState<string | null>(null);
  const mutation = useMarkPaymentHandoffPaid(handoff.id);

  function handleConfirm() {
    setError(null);
    mutation.mutate(
      {
        lockVersion: handoff.lockVersion,
        remittanceReference: remittanceReference.trim() || undefined,
      },
      {
        onSuccess: () => {
          toast.success("Handoff marked as paid");
          setDialogOpen(false);
          onMutationSettled();
        },
        onError: (err) => {
          setError(errorToMessage(err) ?? "Failed to mark as paid.");
        },
      },
    );
  }

  return (
    <>
      <Button
        type="button"
        variant="outline"
        onClick={() => {
          setRemittanceReference("");
          setError(null);
          setDialogOpen(true);
        }}
        disabled={mutation.isPending}
      >
        {mutation.isPending ? "Processing..." : "Mark paid"}
      </Button>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Mark as paid</DialogTitle>
            <DialogDescription>
              Confirm this handoff has been paid.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="paid-remittance">Remittance reference</Label>
              <Input
                id="paid-remittance"
                placeholder="Optional reference"
                value={remittanceReference}
                onChange={(e) => setRemittanceReference(e.target.value)}
              />
            </div>

            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)} disabled={mutation.isPending}>
              Cancel
            </Button>
            <Button onClick={handleConfirm} disabled={mutation.isPending}>
              {mutation.isPending ? "Processing..." : "Confirm paid"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function CloseWithVarianceButton({
  handoff,
  onMutationSettled,
}: {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [varianceReason, setVarianceReason] = useState("");
  const [remittanceReference, setRemittanceReference] = useState("");
  const [error, setError] = useState<string | null>(null);
  const mutation = useClosePaymentHandoffWithVariance(handoff.id);

  function handleConfirm() {
    setError(null);

    if (varianceReason.trim().length < 5) {
      setError("Variance reason must be at least 5 characters.");
      return;
    }

    mutation.mutate(
      {
        lockVersion: handoff.lockVersion,
        varianceReason: varianceReason.trim(),
        remittanceReference: remittanceReference.trim() || undefined,
      },
      {
        onSuccess: () => {
          toast.success("Handoff closed with variance");
          setDialogOpen(false);
          onMutationSettled();
        },
        onError: (err) => {
          setError(errorToMessage(err) ?? "Failed to close with variance.");
        },
      },
    );
  }

  return (
    <>
      <Button
        type="button"
        variant="outline"
        onClick={() => {
          setVarianceReason("");
          setRemittanceReference("");
          setError(null);
          setDialogOpen(true);
        }}
        disabled={mutation.isPending}
      >
        {mutation.isPending ? "Processing..." : "Close with variance"}
      </Button>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Close with variance</DialogTitle>
            <DialogDescription>
              Close this handoff and record a variance explanation.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="variance-reason">Variance reason</Label>
              <Textarea
                id="variance-reason"
                placeholder="Explain the variance..."
                value={varianceReason}
                onChange={(e) => setVarianceReason(e.target.value)}
                rows={3}
              />
              <p className="text-xs text-muted-foreground">Minimum 5 characters.</p>
            </div>

            <div className="space-y-2">
              <Label htmlFor="variance-remittance">Remittance reference</Label>
              <Input
                id="variance-remittance"
                placeholder="Optional reference"
                value={remittanceReference}
                onChange={(e) => setRemittanceReference(e.target.value)}
              />
            </div>

            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)} disabled={mutation.isPending}>
              Cancel
            </Button>
            <Button
              onClick={handleConfirm}
              disabled={varianceReason.trim().length < 5 || mutation.isPending}
            >
              {mutation.isPending ? "Processing..." : "Confirm close"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function MarkFailedButton({
  handoff,
  onMutationSettled,
}: {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [failureCode, setFailureCode] = useState<keyof typeof ApPaymentFailureCode>("other");
  const [failureReason, setFailureReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const mutation = useMarkPaymentHandoffFailed(handoff.id);

  function handleConfirm() {
    setError(null);

    if (failureReason.trim().length < 5) {
      setError("Failure reason must be at least 5 characters.");
      return;
    }

    mutation.mutate(
      {
        lockVersion: handoff.lockVersion,
        failureCode: ApPaymentFailureCode[failureCode],
        failureReason: failureReason.trim(),
      },
      {
        onSuccess: () => {
          toast.success("Handoff marked as failed");
          setDialogOpen(false);
          onMutationSettled();
        },
        onError: (err) => {
          setError(errorToMessage(err) ?? "Failed to mark as failed.");
        },
      },
    );
  }

  return (
    <>
      <Button
        type="button"
        variant="outline"
        className="text-destructive hover:text-destructive"
        onClick={() => {
          setFailureCode("other");
          setFailureReason("");
          setError(null);
          setDialogOpen(true);
        }}
        disabled={mutation.isPending}
      >
        {mutation.isPending ? "Processing..." : "Mark failed"}
      </Button>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Mark as failed</DialogTitle>
            <DialogDescription>
              Record a payment failure for this handoff.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="failure-code">Failure code</Label>
              <select
                id="failure-code"
                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                value={failureCode}
                onChange={(e) => setFailureCode(e.target.value as keyof typeof ApPaymentFailureCode)}
              >
                <option value="bank_rejected">Bank rejected</option>
                <option value="insufficient_funds">Insufficient funds</option>
                <option value="vendor_blocked">Vendor blocked</option>
                <option value="system_error">System error</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="failure-reason">Failure reason</Label>
              <Textarea
                id="failure-reason"
                placeholder="Describe the failure..."
                value={failureReason}
                onChange={(e) => setFailureReason(e.target.value)}
                rows={3}
              />
              <p className="text-xs text-muted-foreground">Minimum 5 characters.</p>
            </div>

            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)} disabled={mutation.isPending}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              onClick={handleConfirm}
              disabled={failureReason.trim().length < 5 || mutation.isPending}
            >
              {mutation.isPending ? "Processing..." : "Confirm failed"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function VoidButton({
  handoff,
  onMutationSettled,
}: {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [voidReason, setVoidReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const mutation = useVoidPaymentHandoff(handoff.id);

  function handleConfirm() {
    setError(null);

    if (voidReason.trim().length < 5) {
      setError("Void reason must be at least 5 characters.");
      return;
    }

    mutation.mutate(
      {
        lockVersion: handoff.lockVersion,
        voidReason: voidReason.trim(),
      },
      {
        onSuccess: () => {
          toast.success("Handoff voided");
          setDialogOpen(false);
          onMutationSettled();
        },
        onError: (err) => {
          setError(errorToMessage(err) ?? "Failed to void handoff.");
        },
      },
    );
  }

  return (
    <>
      <Button
        type="button"
        variant="outline"
        className="text-destructive hover:text-destructive"
        onClick={() => {
          setVoidReason("");
          setError(null);
          setDialogOpen(true);
        }}
        disabled={mutation.isPending}
      >
        {mutation.isPending ? "Processing..." : "Void"}
      </Button>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Void handoff</DialogTitle>
            <DialogDescription>
              This will void the handoff and prevent further payment actions.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="void-reason">Void reason</Label>
              <Textarea
                id="void-reason"
                placeholder="Explain why this handoff is being voided..."
                value={voidReason}
                onChange={(e) => setVoidReason(e.target.value)}
                rows={3}
              />
              <p className="text-xs text-muted-foreground">Minimum 5 characters.</p>
            </div>

            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)} disabled={mutation.isPending}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              onClick={handleConfirm}
              disabled={voidReason.trim().length < 5 || mutation.isPending}
            >
              {mutation.isPending ? "Processing..." : "Confirm void"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function RescheduleButton({
  handoff,
  onMutationSettled,
}: {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [scheduledForDate, setScheduledForDate] = useState("");
  const [paymentReference, setPaymentReference] = useState("");
  const [error, setError] = useState<string | null>(null);
  const mutation = useReschedulePaymentHandoff(handoff.id);

  function handleConfirm() {
    setError(null);
    mutation.mutate(
      {
        lockVersion: handoff.lockVersion,
        scheduledForDate: scheduledForDate || undefined,
        paymentReference: paymentReference.trim() || undefined,
      },
      {
        onSuccess: () => {
          toast.success("Handoff rescheduled");
          setDialogOpen(false);
          onMutationSettled();
        },
        onError: (err) => {
          setError(errorToMessage(err) ?? "Failed to reschedule handoff.");
        },
      },
    );
  }

  return (
    <>
      <Button
        type="button"
        variant="outline"
        onClick={() => {
          setScheduledForDate("");
          setPaymentReference("");
          setError(null);
          setDialogOpen(true);
        }}
        disabled={mutation.isPending}
      >
        {mutation.isPending ? "Processing..." : "Reschedule"}
      </Button>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reschedule handoff</DialogTitle>
            <DialogDescription>
              Set a new schedule for this failed handoff.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="reschedule-date">Scheduled for date</Label>
              <Input
                id="reschedule-date"
                type="date"
                value={scheduledForDate}
                onChange={(e) => setScheduledForDate(e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="reschedule-reference">Payment reference</Label>
              <Input
                id="reschedule-reference"
                placeholder="e.g. Wire-2024-002"
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
            <Button variant="outline" onClick={() => setDialogOpen(false)} disabled={mutation.isPending}>
              Cancel
            </Button>
            <Button onClick={handleConfirm} disabled={mutation.isPending}>
              {mutation.isPending ? "Processing..." : "Confirm reschedule"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
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
