"use client";

import { Alert, AlertDescription, AlertTitle } from "@cognify/ui";
import { AlertCircle } from "lucide-react";
import type { ApPaymentHandoff } from "@cognify/api-client/schemas";

type HandoffWithNumber = ApPaymentHandoff & { number?: string };

interface HandoffFailureDetailProps {
  handoff: HandoffWithNumber;
}

export function HandoffFailureDetail({ handoff }: HandoffFailureDetailProps) {
  const failureCode = (handoff as unknown as { failureCode?: string }).failureCode;
  const failureMessage = (handoff as unknown as { failureMessage?: string }).failureMessage;
  const suggestedRemediation = (handoff as unknown as { suggestedRemediation?: string }).suggestedRemediation;

  if (!failureCode && !failureMessage) {
    return null;
  }

  return (
    <Alert variant="destructive">
      <AlertCircle className="h-4 w-4" />
      <AlertTitle>Failure details</AlertTitle>
      <AlertDescription className="space-y-2">
        {failureCode && (
          <p>
            <span className="font-medium">Code:</span> {failureCode}
          </p>
        )}
        {failureMessage && (
          <p>
            <span className="font-medium">Message:</span> {failureMessage}
          </p>
        )}
        {suggestedRemediation && (
          <p>
            <span className="font-medium">Suggested remediation:</span>{" "}
            {suggestedRemediation}
          </p>
        )}
      </AlertDescription>
    </Alert>
  );
}
