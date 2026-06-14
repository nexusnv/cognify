"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { Alert, AlertDescription, Button, Skeleton, Textarea } from "@cognify/ui";
import type { SupplierInvoiceQueueItem, SupplierInvoiceReviewChecklist } from "@cognify/api-client/schemas";
import {
  useCompleteSupplierInvoiceReview,
  useMarkSupplierInvoiceNeedsInformation,
  useStartSupplierInvoiceReview,
  useSupplierInvoiceDetail,
} from "../hooks/use-supplier-invoice-review-actions";
import {
  buildEmptyChecklist,
  checklistItems,
  InvoiceReviewChecklist,
} from "./invoice-review-checklist";
import { InvoiceReviewStatusBadge } from "./invoice-review-status-badge";

export function InvoiceReviewPanel({
  invoice,
  onMutationSettled,
}: {
  invoice: SupplierInvoiceQueueItem | null;
  onMutationSettled?: () => void;
}) {
  const detailQuery = useSupplierInvoiceDetail(invoice?.id ?? null);
  const current = detailQuery.data;
  const [checklist, setChecklist] = useState<SupplierInvoiceReviewChecklist>(buildEmptyChecklist);
  const [notes, setNotes] = useState("");
  const startMutation = useStartSupplierInvoiceReview(invoice?.id ?? "");
  const needsInformationMutation = useMarkSupplierInvoiceNeedsInformation(invoice?.id ?? "");
  const completeMutation = useCompleteSupplierInvoiceReview(invoice?.id ?? "");
  const actionError = startMutation.error ?? needsInformationMutation.error ?? completeMutation.error;
  const previousCurrentRef = useRef(current);

  useEffect(() => {
    if (!current) return;
    if (previousCurrentRef.current?.id === current.id) return;
    previousCurrentRef.current = current;
    setChecklist(current.reviewChecklist ?? buildEmptyChecklist());
    setNotes(current.reviewNotes ?? "");
  }, [current]);

  if (!invoice) {
    return (
      <aside className="rounded-md border p-4 text-sm text-muted-foreground">
        Select an invoice to review.
      </aside>
    );
  }

  if (detailQuery.isLoading) {
    return (
      <aside className="space-y-3 rounded-md border p-4" aria-label="Loading invoice review panel">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-24 w-full" />
      </aside>
    );
  }

  const displayInvoice = current ?? {
    ...invoice,
    purchaseOrderId: invoice.purchaseOrder.id,
    vendorId: invoice.vendor.id,
    subtotalAmount: invoice.totalAmount,
    taxAmount: "0.0000",
    freightAmount: "0.0000",
    notes: null,
    capturedByUserId: null,
    capturedAt: null,
    lines: [],
    purchaseOrder: invoice.purchaseOrder,
    vendor: invoice.vendor,
    reviewStartedByUserId: null,
    reviewedByUserId: null,
    reviewNotes: null,
    reviewChecklist: null,
    reviewBlockers: [],
    reviewBlockerCount: invoice.reviewBlockerCount,
  };

  async function handleStart() {
    if (!current) return;
    await startMutation.mutateAsync({ lockVersion: current.lockVersion }).catch(() => undefined);
    onMutationSettled?.();
  }

  async function handleComplete() {
    if (!current) return;
    await completeMutation
      .mutateAsync({
        lockVersion: current.lockVersion,
        notes: notes || null,
        checklist: markChecklistPassed(checklist),
      })
      .catch(() => undefined);
    onMutationSettled?.();
  }

  async function handleNeedsInformation() {
    if (!current) return;
    await needsInformationMutation
      .mutateAsync({
        lockVersion: current.lockVersion,
        notes: notes || "Information is required before matching.",
        checklist,
      })
      .catch(() => undefined);
    onMutationSettled?.();
  }

  return (
    <aside className="space-y-4 rounded-md border p-4" aria-label="Invoice review panel">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold">{displayInvoice.invoiceNumber}</h2>
          <p className="text-sm text-muted-foreground">{displayInvoice.vendor.name ?? "Unknown vendor"}</p>
        </div>
        <InvoiceReviewStatusBadge status={displayInvoice.status} />
      </div>

      {actionError ? (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{errorToMessage(actionError)}</AlertDescription>
        </Alert>
      ) : null}

      <dl className="grid gap-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-xs uppercase text-muted-foreground">Purchase order</dt>
          <dd>
            <Link className="font-medium underline-offset-4 hover:underline" href={`/purchase-orders/${displayInvoice.purchaseOrder.id}`}>
              {displayInvoice.purchaseOrder.number ?? displayInvoice.purchaseOrder.id}
            </Link>
          </dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-muted-foreground">Total</dt>
          <dd>{formatMoney(displayInvoice.totalAmount, displayInvoice.currency)}</dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-muted-foreground">Attachments</dt>
          <dd>{displayInvoice.attachmentCount}</dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-muted-foreground">Blockers</dt>
          <dd>{displayInvoice.reviewBlockerCount}</dd>
        </div>
      </dl>

      {displayInvoice.status === "captured" || displayInvoice.status === "needs_information" ? (
        <Button type="button" onClick={() => void handleStart()} disabled={!current || startMutation.isPending}>
          Start review
        </Button>
      ) : null}

      {displayInvoice.status === "in_review" ? (
        <div className="space-y-4">
          <InvoiceReviewChecklist value={checklist} onChange={setChecklist} />
          <Textarea
            aria-label="Review notes"
            placeholder="Review notes"
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
          />
          <div className="flex flex-wrap gap-2">
            <Button type="button" onClick={() => void handleComplete()} disabled={completeMutation.isPending}>
              Complete review
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => void handleNeedsInformation()}
              disabled={needsInformationMutation.isPending}
            >
              Needs information
            </Button>
          </div>
        </div>
      ) : null}

      {displayInvoice.reviewBlockers.length > 0 ? (
        <div className="rounded-md border bg-muted/20 p-3 text-sm">
          {displayInvoice.reviewBlockers.map((blocker) => (
            <p key={blocker.key}>{blocker.note ?? blocker.key}</p>
          ))}
        </div>
      ) : null}
    </aside>
  );
}

function markChecklistPassed(checklist: SupplierInvoiceReviewChecklist): SupplierInvoiceReviewChecklist {
  return checklistItems.reduce<SupplierInvoiceReviewChecklist>((next, item) => ({
    ...next,
    [item.key]: {
      status: "pass",
      note: checklist[item.key].note,
    },
  }), checklist);
}

function errorToMessage(error: unknown) {
  if (typeof error === "object" && error !== null && "error" in error) {
    const apiError = (error as { error?: { message?: string } }).error;
    if (apiError?.message) return apiError.message;
  }

  if (error instanceof Error) return error.message;

  return "Supplier invoice review could not be updated.";
}

function formatMoney(amount: string, currency: string) {
  const numericAmount = Number(amount);

  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(numericAmount) ? numericAmount : 0);
}
