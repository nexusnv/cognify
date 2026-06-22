"use client";

import { useState } from "react";
import { Button } from "@cognify/ui";
import { useSubmitSupplierCreditMemoForApproval } from "../hooks/use-supplier-credit-memos";

interface CreditMemoSubmitButtonProps {
  creditMemoId: string;
  lockVersion: number;
}

export function CreditMemoSubmitButton({ creditMemoId, lockVersion }: CreditMemoSubmitButtonProps) {
  const submitMutation = useSubmitSupplierCreditMemoForApproval(creditMemoId);
  const [submitted, setSubmitted] = useState(false);

  function handleClick() {
    submitMutation.mutate(
      { lockVersion },
      {
        onSuccess: () => setSubmitted(true),
      },
    );
  }

  if (submitted) return null;

  return (
    <Button
      type="button"
      size="sm"
      onClick={handleClick}
      disabled={submitMutation.isPending}
    >
      {submitMutation.isPending ? "Submitting…" : "Submit for approval"}
    </Button>
  );
}
