"use client";

import { useState } from "react";
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label } from "@cognify/ui";
import {
  useCreditApplications,
  useCreateCreditApplication,
  useVoidCreditApplication,
} from "../hooks/use-credit-applications";

interface CreditMemoApplicationPanelProps {
  creditMemoId: string;
  lockVersion: number;
}

export function CreditMemoApplicationPanel({ creditMemoId, lockVersion }: CreditMemoApplicationPanelProps) {
  const applicationsQuery = useCreditApplications(creditMemoId);
  const createApplication = useCreateCreditApplication(creditMemoId);
  const voidApplication = useVoidCreditApplication(creditMemoId);

  const [supplierInvoiceId, setSupplierInvoiceId] = useState("");
  const [appliedAmount, setAppliedAmount] = useState("");
  const [applicationDate, setApplicationDate] = useState("");
  const [appNotes, setAppNotes] = useState("");

  const [voidingId, setVoidingId] = useState<string | null>(null);
  const [voidReason, setVoidReason] = useState("");

  const applications = applicationsQuery.data ?? [];

  function handleApply(e: React.FormEvent) {
    e.preventDefault();
    createApplication.mutate(
      {
        lockVersion,
        supplierInvoiceId,
        appliedAmount,
        applicationDate,
        notes: appNotes || undefined,
      },
      {
        onSuccess: () => {
          setSupplierInvoiceId("");
          setAppliedAmount("");
          setApplicationDate("");
          setAppNotes("");
        },
      },
    );
  }

  function handleVoid(applicationId: string, applicationLockVersion: number) {
    voidApplication.mutate(
      {
        applicationId,
        payload: { lockVersion: applicationLockVersion, voidReason },
      },
      {
        onSuccess: () => {
          setVoidingId(null);
          setVoidReason("");
        },
      },
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Credit applications</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {applicationsQuery.isLoading && (
          <p className="text-sm text-muted-foreground">Loading applications…</p>
        )}

        {applications.length > 0 && (
          <div className="space-y-2">
            {applications.map((app) => (
              <div key={app.id} className="rounded border p-3 text-sm space-y-1">
                <div className="flex items-center justify-between">
                  <span className="font-medium">
                    Invoice: {app.supplierInvoiceNumber ?? app.supplierInvoiceId}
                  </span>
                  <span className="font-mono">{app.appliedAmount}</span>
                </div>
                <p className="text-muted-foreground">Date: {app.applicationDate}</p>
                {app.notes && <p className="text-muted-foreground">{app.notes}</p>}
                {app.voidedAt ? (
                  <p className="text-destructive text-xs">Voided: {app.voidReason}</p>
                ) : (
                  <>
                    {voidingId === app.id ? (
                      <div className="flex items-end gap-2 pt-1">
                        <div className="flex-1 space-y-1">
                          <Label htmlFor={`void-reason-${app.id}`}>Void reason</Label>
                          <Input
                            id={`void-reason-${app.id}`}
                            value={voidReason}
                            onChange={(e) => setVoidReason(e.target.value)}
                            minLength={5}
                            required
                          />
                        </div>
                        <Button
                          type="button"
                          size="sm"
                          variant="destructive"
                          disabled={voidReason.length < 5 || voidApplication.isPending}
                          onClick={() => handleVoid(app.id, app.lockVersion)}
                        >
                          Confirm
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            setVoidingId(null);
                            setVoidReason("");
                          }}
                        >
                          Cancel
                        </Button>
                      </div>
                    ) : (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setVoidingId(app.id)}
                      >
                        Void
                      </Button>
                    )}
                  </>
                )}
              </div>
            ))}
          </div>
        )}

        <form onSubmit={handleApply} className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1">
            <Label htmlFor="supplier-invoice-id">Supplier invoice ID</Label>
            <Input
              id="supplier-invoice-id"
              value={supplierInvoiceId}
              onChange={(e) => setSupplierInvoiceId(e.target.value)}
              required
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor="applied-amount">Applied amount</Label>
            <Input
              id="applied-amount"
              value={appliedAmount}
              onChange={(e) => setAppliedAmount(e.target.value)}
              required
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor="application-date">Application date</Label>
            <Input
              id="application-date"
              type="date"
              value={applicationDate}
              onChange={(e) => setApplicationDate(e.target.value)}
              required
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor="app-notes">Notes</Label>
            <Input
              id="app-notes"
              value={appNotes}
              onChange={(e) => setAppNotes(e.target.value)}
            />
          </div>
          <div className="sm:col-span-2">
            <Button type="submit" size="sm" disabled={createApplication.isPending}>
              {createApplication.isPending ? "Applying…" : "Apply"}
            </Button>
          </div>
        </form>

        {createApplication.isError && (
          <p className="text-sm text-destructive">
            {(createApplication.error as Error)?.message ?? "Failed to create application."}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
