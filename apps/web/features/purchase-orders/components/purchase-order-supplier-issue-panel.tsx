"use client";

import { useState } from "react";
import { Button, Input, Textarea } from "@cognify/ui";
import type { AcknowledgePurchaseOrderRequest, PurchaseOrder } from "@cognify/api-client/schemas";
import {
  useAcknowledgePurchaseOrderSupplier,
  useExportPurchaseOrderSupplierJson,
  useIssuePurchaseOrderToSupplier,
  useRecordPurchaseOrderSupplierJsonExport,
} from "../hooks/use-purchase-order-actions";
import { errorToMessage } from "../utils/error-helpers";

type IssueMethod = "manual_email" | "portal_upload" | "external_system" | "manual_export";

export function PurchaseOrderSupplierIssuePanel({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  return <PurchaseOrderSupplierIssuePanelContent key={supplierIssueFormKey(purchaseOrder)} purchaseOrder={purchaseOrder} />;
}

function PurchaseOrderSupplierIssuePanelContent({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  const issueMutation = useIssuePurchaseOrderToSupplier(purchaseOrder.id);
  const exportMutation = useExportPurchaseOrderSupplierJson(purchaseOrder.id);
  const recordExportMutation = useRecordPurchaseOrderSupplierJsonExport(purchaseOrder.id);
  const acknowledgeMutation = useAcknowledgePurchaseOrderSupplier(purchaseOrder.id);
  const [issueMethod, setIssueMethod] = useState<IssueMethod>("manual_email");
  const [supplierContactName, setSupplierContactName] = useState(purchaseOrder.supplierIssue.supplierContactName ?? "");
  const [supplierContactEmail, setSupplierContactEmail] = useState(purchaseOrder.supplierIssue.supplierContactEmail ?? "");
  const [message, setMessage] = useState(purchaseOrder.supplierIssue.message ?? "");
  const [acknowledgedContactName, setAcknowledgedContactName] = useState(
    purchaseOrder.supplierIssue.acknowledgedContactName ?? purchaseOrder.supplierIssue.supplierContactName ?? "",
  );
  const [acknowledgementReference, setAcknowledgementReference] = useState(
    purchaseOrder.supplierIssue.acknowledgementReference ?? "",
  );
  const [acknowledgementNote, setAcknowledgementNote] = useState(purchaseOrder.supplierIssue.acknowledgementNote ?? "");
  const [acknowledgementValidationError, setAcknowledgementValidationError] = useState<string | null>(null);
  const canIssue = purchaseOrder.permissions.canIssueToSupplier && !issueMutation.isPending;
  const canAcknowledge = purchaseOrder.permissions.canAcknowledgeSupplier && !acknowledgeMutation.isPending;
  const errorMessage =
    errorToMessage(issueMutation.error) ??
    errorToMessage(recordExportMutation.error) ??
    errorToMessage(exportMutation.error) ??
    acknowledgementValidationError ??
    errorToMessage(acknowledgeMutation.error);

  return (
    <section className="rounded-md border p-4" aria-label="Supplier issue">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h2 className="text-base font-semibold">Supplier issue</h2>
          <p className="text-sm text-muted-foreground">{supplierIssueDescription(purchaseOrder)}</p>
        </div>
      </div>

      {purchaseOrder.status === "approved" ? (
        <div className="mt-4 grid gap-3">
          <div className="grid gap-3 md:grid-cols-3">
            <label className="space-y-1 text-sm">
              <span className="font-medium">Issue method</span>
              <select
                className="h-9 rounded-md border bg-background px-3 text-sm"
                value={issueMethod}
                onChange={(event) => setIssueMethod(event.target.value as IssueMethod)}
              >
                <option value="manual_email">Manual email</option>
                <option value="manual_export">Manual export</option>
                <option value="portal_upload">Portal upload</option>
                <option value="external_system">External system</option>
              </select>
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Supplier contact</span>
              <Input value={supplierContactName} onChange={(event) => setSupplierContactName(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Supplier email</span>
              <Input
                type="email"
                value={supplierContactEmail}
                onChange={(event) => setSupplierContactEmail(event.target.value)}
              />
            </label>
          </div>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Issue message</span>
            <Textarea value={message} onChange={(event) => setMessage(event.target.value)} />
          </label>
          <div>
            <Button
              type="button"
              disabled={!canIssue}
              onClick={() =>
                issueMutation.mutate({
                  lockVersion: purchaseOrder.lockVersion,
                  method: issueMethod,
                  supplierContactName: supplierContactName || null,
                  supplierContactEmail: supplierContactEmail || null,
                  message: message || null,
                })
              }
            >
              {issueMutation.isPending ? "Issuing" : "Issue to supplier"}
            </Button>
          </div>
        </div>
      ) : null}

      {["issued", "acknowledged"].includes(purchaseOrder.status) ? (
        <div className="mt-4 space-y-4">
          <dl className="grid gap-3 text-sm sm:grid-cols-3">
            <SupplierFact label="Status" value={purchaseOrder.status === "issued" ? "Issued to supplier" : "Acknowledged"} />
            <SupplierFact label="Issued" value={purchaseOrder.supplierIssue.issuedAt ?? "Not recorded"} />
            <SupplierFact label="Method" value={methodLabel(purchaseOrder.supplierIssue.issueMethod)} />
            <SupplierFact label="Contact" value={purchaseOrder.supplierIssue.supplierContactName ?? "Not recorded"} />
            <SupplierFact label="Email" value={purchaseOrder.supplierIssue.supplierContactEmail ?? "Not recorded"} />
            <SupplierFact
              label="Last export"
              value={
                purchaseOrder.supplierIssue.lastExportedAt
                  ? `${purchaseOrder.supplierIssue.lastExportFormat ?? "json"} at ${purchaseOrder.supplierIssue.lastExportedAt}`
                  : "Not recorded"
              }
            />
          </dl>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={() => exportMutation.mutate()} disabled={exportMutation.isPending}>
              {exportMutation.isPending ? "Preparing" : "Preview JSON"}
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => recordExportMutation.mutate()}
              disabled={recordExportMutation.isPending}
            >
              {recordExportMutation.isPending ? "Recording" : "Record JSON export"}
            </Button>
          </div>
          {exportMutation.data ? (
            <p className="text-sm text-muted-foreground">Prepared supplier export for {exportPurchaseOrderNumber(exportMutation.data)}.</p>
          ) : null}
          {recordExportMutation.data ? (
            <p className="text-sm text-muted-foreground">Recorded supplier export for {exportPurchaseOrderNumber(recordExportMutation.data)}.</p>
          ) : null}
        </div>
      ) : null}

      {purchaseOrder.status === "issued" ? (
        <div className="mt-4 rounded-md border bg-muted/20 p-3">
          <h3 className="text-sm font-semibold">Supplier acknowledgement</h3>
          <div className="mt-3 grid gap-3 md:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span className="font-medium">Acknowledged contact</span>
              <Input value={acknowledgedContactName} onChange={(event) => setAcknowledgedContactName(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Acknowledgement reference</span>
              <Input value={acknowledgementReference} onChange={(event) => setAcknowledgementReference(event.target.value)} />
            </label>
          </div>
          <label className="mt-3 block space-y-1 text-sm">
            <span className="font-medium">Acknowledgement note</span>
            <Textarea value={acknowledgementNote} onChange={(event) => setAcknowledgementNote(event.target.value)} />
          </label>
          <Button
            className="mt-3"
            type="button"
            disabled={!canAcknowledge}
            onClick={() => {
              const payload = acknowledgementPayload({
                lockVersion: purchaseOrder.lockVersion,
                acknowledgedContactName,
                acknowledgementReference,
                acknowledgementNote,
              });

              if (!payload) {
                setAcknowledgementValidationError("At least one acknowledgement evidence field is required.");
                return;
              }

              setAcknowledgementValidationError(null);
              acknowledgeMutation.mutate(payload);
            }}
          >
            {acknowledgeMutation.isPending ? "Recording" : "Record acknowledgement"}
          </Button>
        </div>
      ) : null}

      {purchaseOrder.status === "acknowledged" ? (
        <div className="mt-4 rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-950">
          <p className="font-medium">Supplier acknowledged</p>
          <p className="mt-1">
            {purchaseOrder.supplierIssue.acknowledgementReference ?? "Acknowledgement recorded"}
            {purchaseOrder.supplierIssue.acknowledgedContactName ? ` by ${purchaseOrder.supplierIssue.acknowledgedContactName}` : ""}
          </p>
          {purchaseOrder.supplierIssue.acknowledgementNote ? <p className="mt-1">{purchaseOrder.supplierIssue.acknowledgementNote}</p> : null}
        </div>
      ) : null}

      {!["approved", "issued", "acknowledged"].includes(purchaseOrder.status) ? (
        <div className="mt-4 rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
          Complete approval before issuing this purchase order to the supplier.
        </div>
      ) : null}

      {errorMessage ? (
        <div role="alert" className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {errorMessage}
        </div>
      ) : null}
    </section>
  );
}

function SupplierFact({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="mt-1 break-words font-medium">{value}</dd>
    </div>
  );
}

function supplierIssueDescription(purchaseOrder: PurchaseOrder) {
  switch (purchaseOrder.status) {
    case "approved":
      return "Record the official supplier-facing issue package before downstream receiving or invoices start.";
    case "issued":
      return "This purchase order has been issued. Export the supplier package and record acknowledgement when received.";
    case "acknowledged":
      return "The supplier has acknowledged the issued purchase order.";
    default:
      return "Supplier issue unlocks after approval.";
  }
}

function methodLabel(method: PurchaseOrder["supplierIssue"]["issueMethod"]) {
  if (!method) return "Not recorded";

  return method.replaceAll("_", " ");
}

function exportPurchaseOrderNumber(exportData: { purchaseOrder: Record<string, unknown> }) {
  const number = exportData.purchaseOrder.number;

  return typeof number === "string" ? number : "purchase order";
}

function supplierIssueFormKey(purchaseOrder: PurchaseOrder) {
  return JSON.stringify([
    purchaseOrder.id,
    purchaseOrder.status,
    purchaseOrder.supplierIssue.issueMethod,
    purchaseOrder.supplierIssue.supplierContactName,
    purchaseOrder.supplierIssue.supplierContactEmail,
    purchaseOrder.supplierIssue.message,
    purchaseOrder.supplierIssue.acknowledgedContactName,
    purchaseOrder.supplierIssue.acknowledgementReference,
    purchaseOrder.supplierIssue.acknowledgementNote,
  ]);
}

function acknowledgementPayload({
  lockVersion,
  acknowledgedContactName,
  acknowledgementReference,
  acknowledgementNote,
}: {
  lockVersion: number;
  acknowledgedContactName: string;
  acknowledgementReference: string;
  acknowledgementNote: string;
}): AcknowledgePurchaseOrderRequest | null {
  const contactName = acknowledgedContactName.trim();
  const reference = acknowledgementReference.trim();
  const note = acknowledgementNote.trim();

  if (contactName) {
    return {
      lockVersion,
      acknowledgedContactName: contactName,
      acknowledgementReference: reference || null,
      acknowledgementNote: note || null,
    };
  }

  if (reference) {
    return {
      lockVersion,
      acknowledgementReference: reference,
      acknowledgementNote: note || null,
    };
  }

  if (note) {
    return {
      lockVersion,
      acknowledgementNote: note,
    };
  }

  return null;
}
