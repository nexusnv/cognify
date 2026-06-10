"use client";

import { useState } from "react";
import { Button, Input, Textarea } from "@cognify/ui";
import type { GoodsReceipt, PurchaseOrder, RecordGoodsReceiptRequest } from "@cognify/api-client/schemas";
import {
  useConfirmGoodsReceiptBuyer,
  useConfirmGoodsReceiptRequester,
  useGoodsReceipts,
  useRecordGoodsReceipt,
} from "../hooks/use-purchase-order-goods-receipts";
import { errorToMessage } from "../utils/error-helpers";

export function PurchaseOrderGoodsReceiptPanel({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  const { data: receipts = [], isLoading } = useGoodsReceipts(purchaseOrder.id);
  const recordMutation = useRecordGoodsReceipt(purchaseOrder.id);
  const confirmRequesterMutation = useConfirmGoodsReceiptRequester(purchaseOrder.id);
  const confirmBuyerMutation = useConfirmGoodsReceiptBuyer(purchaseOrder.id);
  const [receiptDate, setReceiptDate] = useState(new Date().toISOString().slice(0, 10));
  const [receiptReference, setReceiptReference] = useState("");
  const [receiptNotes, setReceiptNotes] = useState("");
  const [receivingLineId, setReceivingLineId] = useState(purchaseOrder.lines[0]?.id ?? "");
  const [quantityReceived, setQuantityReceived] = useState("");
  const [quantityAccepted, setQuantityAccepted] = useState("");
  const [lineNotes, setLineNotes] = useState("");
  const [showForm, setShowForm] = useState(false);

  const hasLines = purchaseOrder.lines.length > 0;
  const canRecord = purchaseOrder.permissions.canRecordGoodsReceipt && hasLines;
  const canConfirm = purchaseOrder.permissions.canConfirmGoodsReceipt;

  const errorMessage =
    errorToMessage(recordMutation.error) ??
    errorToMessage(confirmRequesterMutation.error) ??
    errorToMessage(confirmBuyerMutation.error);

  function handleRecord() {
    const payload: RecordGoodsReceiptRequest = {
      lockVersion: purchaseOrder.lockVersion,
      receiptDate,
      receiptReference: receiptReference || null,
      notes: receiptNotes || null,
      lines: [
        {
          purchaseOrderLineId: receivingLineId,
          quantityReceived,
          quantityAccepted: quantityAccepted || null,
          notes: lineNotes || null,
        },
      ],
    };

    recordMutation.mutate(payload, {
      onSuccess: () => {
        setShowForm(false);
        setReceiptReference("");
        setReceiptNotes("");
        setQuantityReceived("");
        setQuantityAccepted("");
        setLineNotes("");
      },
    });
  }

  return (
    <section className="rounded-md border p-4" aria-label="Goods receipt">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h2 className="text-base font-semibold">Goods receipt</h2>
          <p className="text-sm text-muted-foreground">
            {isLoading ? "Loading goods receipts..." : `${receipts.length} receipt(s) recorded`}
          </p>
        </div>
        {canRecord && !showForm ? (
          <Button type="button" variant="outline" size="sm" onClick={() => setShowForm(true)}>
            Record receipt
          </Button>
        ) : null}
      </div>

      {showForm ? (
        <div className="mt-4 space-y-3 rounded-md border bg-muted/20 p-3">
          <h3 className="text-sm font-semibold">New goods receipt</h3>
          <div className="grid gap-3 md:grid-cols-3">
            <label className="space-y-1 text-sm">
              <span className="font-medium">Receipt date</span>
              <Input type="date" value={receiptDate} onChange={(e) => setReceiptDate(e.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Reference (D/O #)</span>
              <Input value={receiptReference} onChange={(e) => setReceiptReference(e.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Line</span>
              <select
                className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                value={receivingLineId}
                onChange={(e) => setReceivingLineId(e.target.value)}
              >
                {purchaseOrder.lines.map((line) => (
                  <option key={line.id} value={line.id}>
                    Line {line.lineNumber} — {line.description}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span className="font-medium">Quantity received</span>
              <Input type="number" step="0.0001" min="0" value={quantityReceived} onChange={(e) => setQuantityReceived(e.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Quantity accepted</span>
              <Input type="number" step="0.0001" min="0" value={quantityAccepted} onChange={(e) => setQuantityAccepted(e.target.value)} />
            </label>
          </div>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Line notes</span>
            <Textarea value={lineNotes} onChange={(e) => setLineNotes(e.target.value)} />
          </label>
          <div className="flex gap-2">
            <Button type="button" disabled={!receivingLineId || !quantityReceived || recordMutation.isPending} onClick={handleRecord}>
              {recordMutation.isPending ? "Recording" : "Record"}
            </Button>
            <Button type="button" variant="outline" onClick={() => setShowForm(false)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : null}

      {receipts.length > 0 ? (
        <div className="mt-4 space-y-3">
          {receipts.map((receipt) => (
            <GoodsReceiptCard
              key={receipt.id}
              receipt={receipt}
              canConfirm={canConfirm}
              onConfirmRequester={(id) => confirmRequesterMutation.mutate({ goodsReceiptId: id, payload: { lockVersion: receipt.lockVersion } })}
              onConfirmBuyer={(id) => confirmBuyerMutation.mutate({ goodsReceiptId: id, payload: { lockVersion: receipt.lockVersion } })}
            />
          ))}
        </div>
      ) : null}

      {!isLoading && receipts.length === 0 && !showForm ? (
        <div className="mt-4 rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
          No goods receipts have been recorded for this purchase order yet.
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

function GoodsReceiptCard({
  receipt,
  canConfirm,
  onConfirmRequester,
  onConfirmBuyer,
}: {
  receipt: GoodsReceipt;
  canConfirm: boolean;
  onConfirmRequester: (id: string) => void;
  onConfirmBuyer: (id: string) => void;
}) {
  const statusColors: Record<string, string> = {
    completed: "bg-blue-100 text-blue-800 border-blue-200",
    requester_confirmed: "bg-amber-100 text-amber-800 border-amber-200",
    buyer_confirmed: "bg-emerald-100 text-emerald-800 border-emerald-200",
  };

  const statusColor = statusColors[receipt.status] ?? "bg-gray-100 text-gray-800 border-gray-200";

  return (
    <div className="rounded-md border p-3 text-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="space-y-1">
          <p className="font-medium">
            {receipt.number}
          </p>
          <p className="text-muted-foreground">
            {receipt.receiptDate}
            {receipt.receiptReference ? ` — ${receipt.receiptReference}` : ""}
          </p>
        </div>
        <span className={`rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${statusColor}`}>
          {receipt.status.replaceAll("_", " ")}
        </span>
      </div>

      <div className="mt-2 space-y-1">
        {receipt.lines.map((line) => (
          <div key={line.id} className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span>Line {line.lineNumber}</span>
            <span>Ordered: {line.quantityOrdered}</span>
            <span>Received: {line.quantityReceived}</span>
            <span>Accepted: {line.quantityAccepted}</span>
            {line.rejectionReason ? <span className="text-red-600">Rejected: {line.rejectionReason}</span> : null}
          </div>
        ))}
      </div>

      {receipt.notes ? (
        <p className="mt-2 text-xs text-muted-foreground">{receipt.notes}</p>
      ) : null}

      {canConfirm && receipt.status === "completed" ? (
        <div className="mt-3 flex gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => onConfirmRequester(receipt.id)}>
            Confirm as requester
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={() => onConfirmBuyer(receipt.id)}>
            Confirm as buyer
          </Button>
        </div>
      ) : null}

      {receipt.status === "requester_confirmed" && canConfirm ? (
        <div className="mt-3 flex gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => onConfirmBuyer(receipt.id)}>
            Confirm as buyer
          </Button>
        </div>
      ) : null}

      {receipt.status === "buyer_confirmed" ? (
        <p className="mt-3 text-xs text-emerald-700">Fully confirmed</p>
      ) : null}
    </div>
  );
}
