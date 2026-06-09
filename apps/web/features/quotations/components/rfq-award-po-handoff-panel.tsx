"use client";

import { useState } from "react";
import { Alert, AlertDescription, Badge, Button, Input, Textarea } from "@cognify/ui";
import { getApiErrorMessage } from "@cognify/api-client";
import type { PurchaseOrderRequestHandoff } from "@cognify/api-client/schemas";
import {
  useCancelRfqAwardRecommendationPoHandoff,
  useCreatePurchaseOrderFromRfqAwardHandoff,
  useCreateRfqAwardRecommendationPoHandoff,
  useDownloadRfqAwardRecommendationPoHandoffCsv,
  useExportRfqAwardRecommendationPoHandoffJson,
  useMarkRfqAwardRecommendationPoHandoffReady,
  useUpdateRfqAwardRecommendationPoHandoff,
} from "../hooks/use-rfq-award-recommendation-actions";

type RfqAwardPoHandoffPanelProps = {
  rfqId: string;
  handoff: PurchaseOrderRequestHandoff | null | undefined;
  isLoading: boolean;
  error: unknown;
};

type ReviewDraft = {
  requestedPoDate: string;
  deliveryAttention: string;
  financeNote: string;
  exportMemo: string;
  cancelReason: string;
};

export function RfqAwardPoHandoffPanel({ rfqId, handoff, isLoading, error }: RfqAwardPoHandoffPanelProps) {
  const create = useCreateRfqAwardRecommendationPoHandoff(rfqId);
  const createPurchaseOrder = useCreatePurchaseOrderFromRfqAwardHandoff(rfqId, handoff?.id);
  const update = useUpdateRfqAwardRecommendationPoHandoff(rfqId, handoff?.id);
  const markReady = useMarkRfqAwardRecommendationPoHandoffReady(rfqId, handoff?.id);
  const exportJson = useExportRfqAwardRecommendationPoHandoffJson(rfqId, handoff?.id);
  const exportCsv = useDownloadRfqAwardRecommendationPoHandoffCsv(rfqId, handoff?.id);
  const cancel = useCancelRfqAwardRecommendationPoHandoff(rfqId, handoff?.id);
  const [draft, setDraft] = useState<ReviewDraft>(() => buildReviewDraft(handoff));
  const [lastAction, setLastAction] = useState<
    "create" | "createPurchaseOrder" | "update" | "ready" | "json" | "csv" | "cancel" | null
  >(null);

  const actionError = lastAction === "create"
    ? create.error
    : lastAction === "createPurchaseOrder"
      ? createPurchaseOrder.error
    : lastAction === "update"
      ? update.error
      : lastAction === "ready"
        ? markReady.error
        : lastAction === "json"
          ? exportJson.error
          : lastAction === "csv"
            ? exportCsv.error
            : lastAction === "cancel"
              ? cancel.error
              : null;
  const canUpdate = handoff?.permissions.canUpdate && handoff.status === "draft";
  const canExport = handoff?.permissions.canExport && (handoff.status === "ready" || handoff.status === "exported");
  const isCancelled = handoff?.status === "cancelled";

  return (
    <section id="po-handoff" className="rounded-md border p-4" aria-label="PO request handoff">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold">PO request handoff</h2>
          <p className="text-sm text-muted-foreground">Finance-ready purchase order request generated from the approved award.</p>
        </div>
        {handoff ? (
          <Badge variant="outline">{handoffStatusLabel(handoff.status)}</Badge>
        ) : null}
      </div>

      {isLoading ? <p className="mt-3 text-sm text-muted-foreground">Loading PO handoff</p> : null}
      {error ? (
        <Alert variant="destructive" className="mt-3">
          <AlertDescription>{getMutationErrorMessage(error)}</AlertDescription>
        </Alert>
      ) : null}

      {!handoff && !isLoading ? (
        <div className="mt-4 space-y-3">
          <p className="text-sm text-muted-foreground">No PO handoff has been generated for this approved award.</p>
          <Button
            disabled={create.isPending}
            onClick={() => {
              setLastAction("create");
              create.mutate();
            }}
          >
            Create PO handoff
          </Button>
        </div>
      ) : null}

      {handoff ? (
        <div className="mt-4 space-y-4">
          <dl className="grid gap-3 text-sm md:grid-cols-4">
            <div>
              <dt className="text-muted-foreground">Handoff</dt>
              <dd className="font-medium">{handoff.number}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Vendor</dt>
              <dd className="font-medium">{sourceValue(handoff.source.vendor, "name")}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Amount</dt>
              <dd className="font-medium">{formatAmount(handoff.totalAmount, handoff.currency)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">RFQ</dt>
              <dd className="font-medium">{sourceValue(handoff.source.rfq, "number")}</dd>
            </div>
          </dl>

          {handoff.readinessWarnings.length > 0 ? (
            <ul className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
              {handoff.readinessWarnings.map((warning) => <li key={warning}>{warning}</li>)}
            </ul>
          ) : null}

          <div className="grid gap-3 md:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span className="font-medium">Requested PO date</span>
              <Input
                aria-label="Requested PO date"
                className="min-h-10 px-3 py-2 text-sm"
                disabled={!canUpdate}
                type="date"
                value={draft.requestedPoDate}
                onChange={(event) => setDraft((current) => ({ ...current, requestedPoDate: event.target.value }))}
              />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Delivery attention</span>
              <Input
                aria-label="Delivery attention"
                className="min-h-10 px-3 py-2 text-sm"
                disabled={!canUpdate}
                value={draft.deliveryAttention}
                onChange={(event) => setDraft((current) => ({ ...current, deliveryAttention: event.target.value }))}
              />
            </label>
          </div>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Finance note</span>
            <Textarea
              aria-label="Finance note"
              disabled={!canUpdate}
              value={draft.financeNote}
              onChange={(event) => setDraft((current) => ({ ...current, financeNote: event.target.value }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Export memo</span>
            <Textarea
              aria-label="Export memo"
              disabled={!canUpdate}
              value={draft.exportMemo}
              onChange={(event) => setDraft((current) => ({ ...current, exportMemo: event.target.value }))}
            />
          </label>

          <div className="flex flex-wrap gap-2">
            {canUpdate ? (
              <Button
                disabled={update.isPending}
                onClick={() => {
                  setLastAction("update");
                  update.mutate({
                    lockVersion: handoff.lockVersion,
                    requestedPoDate: draft.requestedPoDate || null,
                    deliveryAttention: draft.deliveryAttention || null,
                    financeNote: draft.financeNote || null,
                    exportMemo: draft.exportMemo || null,
                  });
                }}
              >
                Save handoff review
              </Button>
            ) : null}
            {handoff.permissions.canMarkReady ? (
              <Button
                disabled={markReady.isPending}
                onClick={() => {
                  setLastAction("ready");
                  markReady.mutate({ lockVersion: handoff.lockVersion });
                }}
              >
                Mark ready
              </Button>
            ) : null}
            {canExport ? (
              <>
                {!handoff.source.purchaseOrderId ? (
                  <Button
                    disabled={createPurchaseOrder.isPending}
                    onClick={() => {
                      setLastAction("createPurchaseOrder");
                      createPurchaseOrder.mutate();
                    }}
                  >
                    Create purchase order
                  </Button>
                ) : null}
                <Button
                  disabled={exportJson.isPending}
                  onClick={() => {
                    setLastAction("json");
                    exportJson.mutate(undefined, {
                      onSuccess: (payload) => downloadBlob(
                        new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" }),
                        `${handoff.number}.json`,
                      ),
                    });
                  }}
                >
                  Download JSON
                </Button>
                <Button
                  disabled={exportCsv.isPending}
                  onClick={() => {
                    setLastAction("csv");
                    exportCsv.mutate(undefined, {
                      onSuccess: (blob) => downloadBlob(blob, `${handoff.number}.csv`),
                    });
                  }}
                >
                  Download CSV
                </Button>
              </>
            ) : null}
          </div>

          {handoff.lastExportedAt ? (
            <p className="text-sm text-muted-foreground">
              Last exported {new Date(handoff.lastExportedAt).toLocaleString()} as {handoff.lastExportFormat?.toUpperCase() ?? "export"}.
            </p>
          ) : null}

          {handoff.permissions.canCancel ? (
            <div className="space-y-2 border-t pt-4">
              <Textarea
                aria-label="Cancellation reason"
                disabled={cancel.isPending}
                placeholder="Cancellation reason"
                value={draft.cancelReason}
                onChange={(event) => setDraft((current) => ({ ...current, cancelReason: event.target.value }))}
              />
              <Button
                disabled={cancel.isPending || draft.cancelReason.trim().length < 3}
                onClick={() => {
                  setLastAction("cancel");
                  cancel.mutate({ lockVersion: handoff.lockVersion, reason: draft.cancelReason.trim() });
                }}
              >
                Cancel handoff
              </Button>
            </div>
          ) : null}

          {isCancelled ? (
            <p className="rounded-md border p-3 text-sm text-muted-foreground">
              Cancelled{handoff.cancelledReason ? `: ${handoff.cancelledReason}` : "."}
            </p>
          ) : null}
        </div>
      ) : null}

      {actionError ? (
        <Alert variant="destructive" className="mt-3">
          <AlertDescription>{getMutationErrorMessage(actionError)}</AlertDescription>
        </Alert>
      ) : null}
    </section>
  );
}

function buildReviewDraft(handoff: PurchaseOrderRequestHandoff | null | undefined): ReviewDraft {
  return {
    requestedPoDate: handoff?.review.requestedPoDate ?? "",
    deliveryAttention: handoff?.review.deliveryAttention ?? "",
    financeNote: handoff?.review.financeNote ?? "",
    exportMemo: handoff?.review.exportMemo ?? "",
    cancelReason: "",
  };
}

function handoffStatusLabel(status: PurchaseOrderRequestHandoff["status"]): string {
  if (status === "ready") return "Ready";
  if (status === "exported") return "Exported";
  if (status === "cancelled") return "Cancelled";

  return "Draft";
}

function sourceValue(source: { [key: string]: unknown } | null | undefined, key: string): string {
  if (!source) return "Unknown";
  const value = source[key];

  return typeof value === "string" || typeof value === "number" ? String(value) : "Unknown";
}

function formatAmount(amount: string | null | undefined, currency: string | null | undefined): string {
  if (!amount) return "Unknown";

  return `${currency ?? ""} ${amount}`.trim();
}

function downloadBlob(blob: Blob, filename: string) {
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(objectUrl);
}

function getMutationErrorMessage(error: unknown): string {
  const rawMessage = getRawErrorMessage(error);
  if (rawMessage) return rawMessage;

  return getApiErrorMessage(error);
}

function getRawErrorMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null && "error" in error) {
    const message = (error as { error?: { message?: unknown } }).error?.message;
    return typeof message === "string" ? message : null;
  }

  return null;
}
