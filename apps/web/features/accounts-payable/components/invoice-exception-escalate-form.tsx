"use client";

import { Button, Label, Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, Textarea } from "@cognify/ui";
import { useState } from "react";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";
import { InvoiceExceptionStatusBadge } from "./invoice-exception-status-badge";

interface InvoiceExceptionEscalateFormProps {
  exception: SupplierInvoiceException;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (data: {
    lockVersion: number;
    escalatedToUserId: string;
    note?: string;
  }) => void;
  isPending: boolean;
}

export function InvoiceExceptionEscalateForm({
  exception,
  open,
  onOpenChange,
  onSubmit,
  isPending,
}: InvoiceExceptionEscalateFormProps) {
  const [note, setNote] = useState("");

  const escalatedToUserId = "procurement-manager-id";

  const handleSubmit = () => {
    onSubmit({
      lockVersion: exception.lockVersion,
      escalatedToUserId,
      ...(note.trim() ? { note: note.trim() } : {}),
    });
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent>
        <SheetHeader>
          <SheetTitle>Escalate exception</SheetTitle>
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

          <div className="rounded-md bg-muted p-3 text-sm">
            Escalation transfers ownership to the selected user. Only they can resolve the exception after escalation.
          </div>

          <div className="space-y-2">
            <Label htmlFor="escalationNote">Escalation note</Label>
            <Textarea
              id="escalationNote"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Why does this need escalation?"
              rows={3}
            />
          </div>
        </div>

        <SheetFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={isPending}>
            {isPending ? "Escalating..." : "Confirm escalation"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
