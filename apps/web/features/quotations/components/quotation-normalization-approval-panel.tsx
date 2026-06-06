"use client";

import { useState } from "react";
import { Alert, AlertDescription, Button, Card, CardContent, CardHeader, CardTitle, Textarea } from "@cognify/ui";
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

  if (!canEdit) {
    const readOnlyLabel =
      normalization.status === "approved" || normalization.status === "approved_with_warnings"
        ? "Read-only approved record"
        : "Read-only approval record";

    return (
      <Card id="approval">
        <CardHeader>
          <CardTitle className="text-base">Approval</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">{readOnlyLabel}</p>
        </CardContent>
      </Card>
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
    <Card id="approval">
      <CardHeader className="space-y-1">
        <CardTitle className="text-base">Approval</CardTitle>
        <p className="text-sm text-muted-foreground">Approval locks this normalization revision for downstream comparison.</p>
      </CardHeader>
      <CardContent>

      <label className="block text-sm font-medium">
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
        <Alert variant="destructive" className="mt-3">
          <AlertDescription>{localError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="mt-4 flex flex-wrap gap-2">
        <Button
          type="button"
          disabled={!canEdit || !normalization.permissions.canApprove || isSubmitting}
          onClick={() => void submitApproval(onApprove)}
        >
          Approve normalization
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={!canEdit || !normalization.permissions.canApproveWithWarnings || isSubmitting}
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
      </CardContent>
    </Card>
  );
}
