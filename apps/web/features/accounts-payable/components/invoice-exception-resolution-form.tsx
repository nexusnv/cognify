"use client";

import { Button, Input, Label, RadioGroup, RadioGroupItem, Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, Textarea } from "@cognify/ui";
import { useState } from "react";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";
import { InvoiceExceptionStatusBadge } from "./invoice-exception-status-badge";

interface InvoiceExceptionResolutionFormProps {
  exception: SupplierInvoiceException;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (data: {
    lockVersion: number;
    resolutionType: "value_adjustment" | "explanation";
    adjustedValue?: string;
    explanation?: string;
  }) => void;
  isPending: boolean;
}

export function InvoiceExceptionResolutionForm({
  exception,
  open,
  onOpenChange,
  onSubmit,
  isPending,
}: InvoiceExceptionResolutionFormProps) {
  const [resolutionType, setResolutionType] = useState<"value_adjustment" | "explanation">("explanation");
  const [adjustedValue, setAdjustedValue] = useState(exception.actualValue ?? "");
  const [explanation, setExplanation] = useState("");

  const handleSubmit = () => {
    onSubmit({
      lockVersion: exception.lockVersion,
      resolutionType,
      ...(resolutionType === "value_adjustment" ? { adjustedValue } : {}),
      ...(explanation.trim() ? { explanation: explanation.trim() } : {}),
    });
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent>
        <SheetHeader>
          <SheetTitle>Resolve exception</SheetTitle>
          <SheetDescription>
            <span className="font-medium">{exception.dimension}</span> &mdash;{" "}
            expected {exception.expectedValue}, actual {exception.actualValue}
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 py-4">
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Status:</span>
            <InvoiceExceptionStatusBadge status={exception.status} />
          </div>

          <RadioGroup
            value={resolutionType}
            onValueChange={(v) => setResolutionType(v as "value_adjustment" | "explanation")}
          >
            <div className="flex items-center gap-2">
              <RadioGroupItem value="explanation" id="explanation" />
              <Label htmlFor="explanation">Explanation (waive variance)</Label>
            </div>
            <div className="flex items-center gap-2">
              <RadioGroupItem value="value_adjustment" id="value_adjustment" />
              <Label htmlFor="value_adjustment">Value adjustment (propose payment overlay)</Label>
            </div>
          </RadioGroup>

          {resolutionType === "value_adjustment" && (
            <div className="space-y-2">
              <Label htmlFor="adjustedValue">Adjusted value</Label>
              <Input
                id="adjustedValue"
                type="text"
                value={adjustedValue}
                onChange={(e) => setAdjustedValue(e.target.value)}
              />
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="explanation-note">Explanation notes</Label>
            <Textarea
              id="explanation-note"
              value={explanation}
              onChange={(e) => setExplanation(e.target.value)}
              placeholder="Why is this variance acceptable?"
              rows={3}
            />
          </div>
        </div>

        <SheetFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={isPending}>
            {isPending ? "Submitting..." : "Submit resolution"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
