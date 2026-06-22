"use client";

import { useState } from "react";
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label } from "@cognify/ui";
import { useVoidSupplierCreditMemo } from "../hooks/use-supplier-credit-memos";

interface CreditMemoVoidPanelProps {
  creditMemoId: string;
  lockVersion: number;
}

export function CreditMemoVoidPanel({ creditMemoId, lockVersion }: CreditMemoVoidPanelProps) {
  const voidMutation = useVoidSupplierCreditMemo(creditMemoId);
  const [reason, setReason] = useState("");
  const [confirming, setConfirming] = useState(false);
  const [voided, setVoided] = useState(false);

  if (voided) return null;

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!confirming) {
      setConfirming(true);
      return;
    }
    voidMutation.mutate(
      { lockVersion, voidReason: reason },
      {
        onSuccess: () => setVoided(true),
      },
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Void credit memo</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-3">
          <div className="space-y-1">
            <Label htmlFor="void-reason">Reason</Label>
            <Input
              id="void-reason"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Enter void reason (min 5 characters)"
              required
              minLength={5}
            />
          </div>
          {voidMutation.isError && (
            <p className="text-sm text-destructive">
              {(voidMutation.error as Error)?.message ?? "Failed to void credit memo."}
            </p>
          )}
          <div className="flex gap-2">
            <Button
              type="submit"
              variant="destructive"
              size="sm"
              disabled={voidMutation.isPending || reason.length < 5}
            >
              {confirming ? "Confirm void" : "Void credit memo"}
            </Button>
            {confirming && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setConfirming(false)}
              >
                Cancel
              </Button>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
