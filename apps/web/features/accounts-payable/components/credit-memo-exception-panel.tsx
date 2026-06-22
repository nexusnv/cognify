"use client";

import { useState } from "react";
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Input, Label } from "@cognify/ui";
import type { SupplierCreditMemoException, SupplierCreditMemoExceptionResolutionType } from "@cognify/api-client/schemas";
import {
  useSupplierCreditMemoExceptions,
  useAcknowledgeCreditMemoException,
  useResolveCreditMemoException,
  useEscalateCreditMemoException,
} from "../hooks/use-supplier-credit-memo-exceptions";

const severityStyles: Record<string, string> = {
  blocking: "bg-red-100 text-red-800",
  warning: "bg-amber-100 text-amber-800",
  info: "bg-blue-100 text-blue-800",
};

interface CreditMemoExceptionPanelProps {
  creditMemoId: string;
}

export function CreditMemoExceptionPanel({ creditMemoId }: CreditMemoExceptionPanelProps) {
  const exceptionsQuery = useSupplierCreditMemoExceptions(creditMemoId);
  const acknowledge = useAcknowledgeCreditMemoException(creditMemoId);
  const resolve = useResolveCreditMemoException(creditMemoId);
  const escalate = useEscalateCreditMemoException(creditMemoId);

  const [resolvingId, setResolvingId] = useState<string | null>(null);
  const [resolutionType, setResolutionType] = useState<SupplierCreditMemoExceptionResolutionType>("accepted");
  const [resolutionNotes, setResolutionNotes] = useState("");

  const exceptions = exceptionsQuery.data ?? [];

  function handleAcknowledge(exception: SupplierCreditMemoException) {
    acknowledge.mutate({
      exceptionId: exception.id,
      payload: { lockVersion: exception.lockVersion },
    });
  }

  function handleEscalate(exception: SupplierCreditMemoException) {
    escalate.mutate({
      exceptionId: exception.id,
      payload: { lockVersion: exception.lockVersion },
    });
  }

  function handleResolve(exception: SupplierCreditMemoException) {
    resolve.mutate(
      {
        exceptionId: exception.id,
        payload: {
          lockVersion: exception.lockVersion,
          resolutionType,
          resolutionNotes,
        },
      },
      {
        onSuccess: () => {
          setResolvingId(null);
          setResolutionNotes("");
        },
      },
    );
  }

  if (exceptionsQuery.isLoading) {
    return (
      <Card>
        <CardContent className="py-4 text-sm text-muted-foreground">Loading exceptions…</CardContent>
      </Card>
    );
  }

  if (exceptions.length === 0) return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Exceptions</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {exceptions.map((ex) => (
          <div key={ex.id} className="rounded border p-3 text-sm space-y-2">
            <div className="flex items-center justify-between">
              <span className="font-medium">{ex.exceptionType.replace(/_/g, " ")}</span>
              <Badge className={severityStyles[ex.severity] ?? "bg-gray-100"}>{ex.severity}</Badge>
            </div>
            <p className="text-muted-foreground">{ex.description}</p>
            {ex.expectedValue && (
              <p className="text-xs text-muted-foreground">Expected: {ex.expectedValue} | Adjusted: {ex.adjustedValue ?? "—"}</p>
            )}

            {ex.resolvedAt ? (
              <p className="text-xs text-emerald-700">Resolved: {ex.resolutionType}</p>
            ) : ex.acknowledgedAt ? (
              <p className="text-xs text-muted-foreground">Acknowledged</p>
            ) : (
              <div className="flex flex-wrap gap-2 pt-1">
                <Button type="button" size="sm" variant="outline" onClick={() => handleAcknowledge(ex)}>
                  Acknowledge
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={() => setResolvingId(ex.id)}>
                  Resolve
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={() => handleEscalate(ex)}>
                  Escalate
                </Button>
              </div>
            )}

            {resolvingId === ex.id && (
              <div className="space-y-2 pt-1">
                <div className="space-y-1">
                  <Label htmlFor={`resolution-type-${ex.id}`}>Resolution type</Label>
                  <select
                    id={`resolution-type-${ex.id}`}
                    value={resolutionType}
                    onChange={(e) => setResolutionType(e.target.value as SupplierCreditMemoExceptionResolutionType)}
                    className="w-full rounded border px-2 py-1 text-sm"
                  >
                    <option value="accepted">Accepted</option>
                    <option value="value_adjustment">Value adjustment</option>
                    <option value="vendor_reassignment">Vendor reassignment</option>
                    <option value="voided">Voided</option>
                    <option value="info_only">Info only</option>
                  </select>
                </div>
                <div className="space-y-1">
                  <Label htmlFor={`resolution-notes-${ex.id}`}>Resolution notes</Label>
                  <Input
                    id={`resolution-notes-${ex.id}`}
                    value={resolutionNotes}
                    onChange={(e) => setResolutionNotes(e.target.value)}
                    minLength={5}
                    required
                  />
                </div>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    size="sm"
                    disabled={resolutionNotes.length < 5 || resolve.isPending}
                    onClick={() => handleResolve(ex)}
                  >
                    Confirm resolution
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => {
                      setResolvingId(null);
                      setResolutionNotes("");
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </div>
            )}
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
