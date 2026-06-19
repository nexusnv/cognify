"use client";

import { useState } from "react";
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  Textarea,
} from "@cognify/ui";
import type { SupplierInvoiceQueueItemPaymentStatus } from "@cognify/api-client/schemas";
import {
  usePlaceInvoicePaymentHold,
  useReleaseInvoicePaymentHold,
} from "../hooks/use-payment-holds";

interface PaymentHoldPanelProps {
  invoiceId: string;
  paymentStatus: SupplierInvoiceQueueItemPaymentStatus | null | undefined;
  paymentOnHoldReason?: string | null;
  paymentOnHoldAt?: string | null;
  paymentOnHoldByUserId?: string | null;
  lockVersion: number;
  onMutationSettled?: () => void;
}

function errorToMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null) {
    const apiError = (error as { error?: { code?: string; message?: string } }).error;
    if (apiError?.message) {
      return apiError.message;
    }
  }

  return null;
}

export function PaymentHoldPanel({
  invoiceId,
  paymentStatus,
  paymentOnHoldReason,
  paymentOnHoldAt,
  paymentOnHoldByUserId,
  lockVersion,
  onMutationSettled,
}: PaymentHoldPanelProps) {
  const [holdDialogOpen, setHoldDialogOpen] = useState(false);
  const [releaseDialogOpen, setReleaseDialogOpen] = useState(false);
  const [holdReason, setHoldReason] = useState("");
  const [releaseNote, setReleaseNote] = useState("");
  const [error, setError] = useState<string | null>(null);

  const placeHoldMutation = usePlaceInvoicePaymentHold(invoiceId);
  const releaseHoldMutation = useReleaseInvoicePaymentHold(invoiceId);

  function handlePlaceHold() {
    setError(null);
    placeHoldMutation.mutate(
      { reason: holdReason, lockVersion },
      {
        onSettled: () => {
          setHoldDialogOpen(false);
          setHoldReason("");
          onMutationSettled?.();
        },
        onError: (err) => {
          setError(errorToMessage(err) ?? "Failed to place payment hold.");
        },
      },
    );
  }

  function handleReleaseHold() {
    setError(null);
    releaseHoldMutation.mutate(
      { releaseNote, lockVersion },
      {
        onSettled: () => {
          setReleaseDialogOpen(false);
          setReleaseNote("");
          onMutationSettled?.();
        },
        onError: (err) => {
          setError(errorToMessage(err) ?? "Failed to release payment hold.");
        },
      },
    );
  }

  if (!paymentStatus) {
    return null;
  }

  if (paymentStatus === "on_hold") {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Payment Hold</CardTitle>
          <CardDescription>This invoice is on payment hold.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {paymentOnHoldByUserId && (
            <div className="text-sm text-muted-foreground">
              Placed by {paymentOnHoldByUserId}
              {paymentOnHoldAt && <> on {new Date(paymentOnHoldAt).toLocaleDateString()}</>}
            </div>
          )}

          {paymentOnHoldReason && (
            <div className="text-sm">
              <span className="font-medium">Reason:</span> {paymentOnHoldReason}
            </div>
          )}

          {error && <p className="text-sm text-destructive">{error}</p>}

          <Dialog open={releaseDialogOpen} onOpenChange={setReleaseDialogOpen}>
            <DialogTrigger asChild>
              <Button variant="outline" className="w-full">
                Release hold
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Release payment hold</DialogTitle>
                <DialogDescription>
                  Provide a note explaining why the hold is being released.
                </DialogDescription>
              </DialogHeader>
              <Textarea
                placeholder="Reason for releasing hold..."
                value={releaseNote}
                onChange={(e) => setReleaseNote(e.target.value)}
              />
              <DialogFooter>
                <Button variant="outline" onClick={() => setReleaseDialogOpen(false)}>
                  Cancel
                </Button>
                <Button
                  onClick={handleReleaseHold}
                  disabled={releaseHoldMutation.isPending || !releaseNote.trim()}
                >
                  {releaseHoldMutation.isPending ? "Releasing..." : "Release hold"}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </CardContent>
      </Card>
    );
  }

  if (paymentStatus === "payment_eligible") {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Payment Hold</CardTitle>
          <CardDescription>This invoice is eligible for payment.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {error && <p className="text-sm text-destructive">{error}</p>}

          <Dialog open={holdDialogOpen} onOpenChange={setHoldDialogOpen}>
            <DialogTrigger asChild>
              <Button variant="outline" className="w-full">
                Place hold
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Place payment hold</DialogTitle>
                <DialogDescription>
                  Provide a reason for placing this invoice on payment hold.
                </DialogDescription>
              </DialogHeader>
              <Textarea
                placeholder="Reason for hold..."
                value={holdReason}
                onChange={(e) => setHoldReason(e.target.value)}
              />
              <DialogFooter>
                <Button variant="outline" onClick={() => setHoldDialogOpen(false)}>
                  Cancel
                </Button>
                <Button
                  onClick={handlePlaceHold}
                  disabled={placeHoldMutation.isPending || !holdReason.trim()}
                >
                  {placeHoldMutation.isPending ? "Placing hold..." : "Place hold"}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </CardContent>
      </Card>
    );
  }

  return null;
}
