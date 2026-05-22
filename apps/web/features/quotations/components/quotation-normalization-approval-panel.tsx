"use client";

import { useState } from "react";
import { Button, Textarea } from "@cognify/ui";
import type { QuotationNormalization } from "@cognify/api-client/schemas";
import { getQuotationNormalizationErrorMessage } from "../utils/quotation-normalization-ui";

export function QuotationNormalizationApprovalPanel({
  normalization,
  canEdit,
  onApprove,
  onApproveWithWarnings,
}: {
  normalization: QuotationNormalization;
  canEdit: boolean;
  onApprove: (approvalNote: string) => Promise<void>;
  onApproveWithWarnings: (approvalNote: string) => Promise<void>;
}) {
  const [approvalNote, setApprovalNote] = useState("");
  const [localError, setLocalError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!canEdit && (normalization.status === "approved" || normalization.status === "approved_with_warnings")) {
    return (
      <section id="approval" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Approval</h2>
        <p className="mt-2 text-sm text-muted-foreground">Read-only approved record</p>
      </section>
    );
  }

  async function submitApproval(action: (approvalNote: string) => Promise<void>) {
    if (isSubmitting) return;

    setIsSubmitting(true);
    setLocalError(null);

    try {
      await action(approvalNote);
    } catch (error) {
      setLocalError(error instanceof Error ? error.message : getQuotationNormalizationErrorMessage(error));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <section id="approval" className="rounded-md border p-4">
      <div className="space-y-1">
        <h2 className="text-base font-semibold">Approval</h2>
        <p className="text-sm text-muted-foreground">Approval locks this normalization revision for downstream comparison.</p>
      </div>

      <label className="mt-4 block text-sm font-medium">
        Approval note
        <Textarea
          className="mt-1 min-h-24 text-sm"
          value={approvalNote}
          onChange={(event) => {
            setApprovalNote(event.target.value);
            setLocalError(null);
          }}
        />
      </label>

      {localError ? (
        <p role="alert" className="mt-3 text-sm text-red-700">
          {localError}
        </p>
      ) : null}

      <div className="mt-4 flex flex-wrap gap-2">
        <Button
          type="button"
          disabled={!normalization.permissions.canApprove || isSubmitting}
          onClick={() => void submitApproval(onApprove)}
        >
          Approve normalization
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={!normalization.permissions.canApproveWithWarnings || isSubmitting}
          onClick={() => {
            if (!approvalNote.trim()) {
              setLocalError("Add an acknowledgement note before approving with warnings.");
              return;
            }

            void submitApproval(onApproveWithWarnings);
          }}
        >
          Approve with warnings
        </Button>
      </div>
    </section>
  );
}
