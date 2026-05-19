"use client";

import { useState } from "react";
import { Button, NativeSelect, Textarea } from "@cognify/ui";
import { useDecideSourcingIntakeReview } from "../hooks/use-sourcing-intake-actions";
import { sourcingIntakeDecisionSchema } from "../schemas/sourcing-intake-schema";
import type { SourcingIntakeReview, SourcingPath } from "../types/sourcing-view-model";

const labels: Record<SourcingPath, string> = {
  needs_rfq: "Mark ready for RFQ",
  needs_clarification: "Request clarification",
  direct_award: "Record direct award path",
  no_sourcing_required: "Close without sourcing",
};

export function SourcingIntakeDecisionDialog({ review }: { review: SourcingIntakeReview }) {
  const [open, setOpen] = useState(false);
  const [sourcingPath, setSourcingPath] = useState<SourcingPath>("needs_rfq");
  const [decisionReason, setDecisionReason] = useState("");
  const [clarificationMessage, setClarificationMessage] = useState("");
  const [error, setError] = useState<string | null>(null);
  const mutation = useDecideSourcingIntakeReview(review.id);

  async function handleSubmit() {
    const parsed = sourcingIntakeDecisionSchema.safeParse({
      sourcingPath,
      decisionReason,
      clarificationMessage: sourcingPath === "needs_clarification" ? clarificationMessage : null,
      clarificationFields: sourcingPath === "needs_clarification" ? ["lineItems", "businessJustification"] : [],
    });
    if (!parsed.success || (sourcingPath === "needs_clarification" && !clarificationMessage.trim())) {
      setError("Decision reason and clarification details are required.");
      return;
    }

    setError(null);
    try {
      await mutation.mutateAsync(parsed.data);
      setOpen(false);
      setDecisionReason("");
      setClarificationMessage("");
    } catch {
      setError("Decision could not be recorded. Refresh and try again.");
    }
  }

  return (
    <>
      <Button onClick={() => setOpen(true)}>Record decision</Button>
      {!open ? null : (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div role="dialog" aria-modal="true" aria-label="Record sourcing decision" className="w-full max-w-lg rounded-md border bg-background p-5 shadow-lg">
            <h2 className="text-lg font-semibold">Record sourcing decision</h2>
            <div className="mt-4 space-y-4">
              <label className="block space-y-1.5 text-sm font-medium">
                Decision
                <NativeSelect value={sourcingPath} onChange={(event) => setSourcingPath(event.target.value as SourcingPath)}>
                  {Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </NativeSelect>
              </label>
              <label className="block space-y-1.5 text-sm font-medium">
                Decision reason
                <Textarea value={decisionReason} onChange={(event) => setDecisionReason(event.target.value)} />
              </label>
              {sourcingPath === "needs_clarification" ? (
                <label className="block space-y-1.5 text-sm font-medium">
                  Clarification message
                  <Textarea value={clarificationMessage} onChange={(event) => setClarificationMessage(event.target.value)} />
                </label>
              ) : null}
            </div>
            {error ? <p className="mt-4 text-sm text-red-700">{error}</p> : null}
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
              <Button disabled={mutation.isPending} onClick={handleSubmit}>{mutation.isPending ? "Recording" : labels[sourcingPath]}</Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
