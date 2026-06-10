"use client";

import { useMemo, useState, type Dispatch, type SetStateAction } from "react";
import { Button, Input, Textarea } from "@cognify/ui";
import type {
  PurchaseOrder,
  PurchaseOrderChangeOrder,
  PurchaseOrderChangeOrderLine,
  PurchaseOrderLine,
} from "@cognify/api-client/schemas";
import { errorToMessage } from "../utils/error-helpers";
import {
  useCancelPurchaseOrderChangeOrder,
  useCreatePurchaseOrderChangeOrder,
  useSubmitPurchaseOrderChangeOrder,
  useUpdatePurchaseOrderChangeOrder,
} from "../hooks/use-purchase-order-actions";
import { usePurchaseOrderChangeOrder, usePurchaseOrderChangeOrders } from "../hooks/use-purchase-order";

type ChangeOrderDraft = {
  changeType: PurchaseOrderChangeOrder["changeType"];
  reason: string;
  requestedPoDate: string;
  expectedDeliveryDate: string;
  billingName: string;
  shippingName: string;
  deliveryAttention: string;
  paymentTerms: string;
  deliveryTerms: string;
  buyerNote: string;
  financeNote: string;
  lines: ChangeOrderLineDraft[];
};

type ChangeOrderLineDraft = {
  lineId: string;
  action: PurchaseOrderChangeOrderLine["changeAction"];
  quantity: string;
  unitPrice: string;
  expectedDeliveryDate: string;
  deliveryLocation: string;
  notes: string;
};

export function PurchaseOrderChangeOrderPanel({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  const changeOrdersQuery = usePurchaseOrderChangeOrders(purchaseOrder.id);
  const currentChangeOrderId =
    changeOrdersQuery.data?.find((changeOrder) => ["draft", "changes_requested"].includes(changeOrder.status))?.id ?? "";
  const currentChangeOrderDetailQuery = usePurchaseOrderChangeOrder(currentChangeOrderId);

  if (changeOrdersQuery.isLoading) {
    return (
      <section id="change-orders" className="rounded-md border p-4" aria-label="Purchase order change orders">
        <h2 className="text-base font-semibold">Change orders</h2>
        <p className="mt-2 text-sm text-muted-foreground">Loading change orders</p>
      </section>
    );
  }

  if (changeOrdersQuery.isError) {
    return (
      <section id="change-orders" className="rounded-md border p-4" aria-label="Purchase order change orders">
        <h2 className="text-base font-semibold">Change orders</h2>
        <p className="mt-2 text-sm text-red-900">Change orders could not be loaded.</p>
      </section>
    );
  }

  const changeOrders = changeOrdersQuery.data ?? [];
  const currentChangeOrder = currentActiveChangeOrder(changeOrders);
  const latestChangeOrder = changeOrders.length > 0 ? changeOrders[changeOrders.length - 1] : null;
  const currentChangeOrderDetail = currentChangeOrderDetailQuery.data ?? null;

  return (
    <PurchaseOrderChangeOrderPanelContent
      key={changeOrderFormKey(purchaseOrder, currentChangeOrder, currentChangeOrderDetail)}
      purchaseOrder={purchaseOrder}
      changeOrders={changeOrders}
      currentChangeOrder={currentChangeOrder}
      currentChangeOrderDetail={currentChangeOrderDetail}
      latestChangeOrder={latestChangeOrder}
    />
  );
}

function PurchaseOrderChangeOrderPanelContent({
  purchaseOrder,
  changeOrders,
  currentChangeOrder,
  currentChangeOrderDetail,
  latestChangeOrder,
}: {
  purchaseOrder: PurchaseOrder;
  changeOrders: PurchaseOrderChangeOrder[];
  currentChangeOrder: PurchaseOrderChangeOrder | null;
  currentChangeOrderDetail: PurchaseOrderChangeOrder | null;
  latestChangeOrder: PurchaseOrderChangeOrder | null;
}) {
  const createMutation = useCreatePurchaseOrderChangeOrder(purchaseOrder.id);
  const updateMutation = useUpdatePurchaseOrderChangeOrder(purchaseOrder.id);
  const submitMutation = useSubmitPurchaseOrderChangeOrder(purchaseOrder.id);
  const cancelMutation = useCancelPurchaseOrderChangeOrder(purchaseOrder.id);
  const isBusy = createMutation.isPending || updateMutation.isPending || submitMutation.isPending || cancelMutation.isPending;
  const canEdit = currentChangeOrder
    ? purchaseOrder.permissions.canUpdateChangeOrder && ["draft", "changes_requested"].includes(currentChangeOrder.status) && !isBusy
    : purchaseOrder.permissions.canCreateChangeOrder && !isBusy;
  const isExisting = currentChangeOrder !== null;
  const [draft, setDraft] = useState<ChangeOrderDraft>(() =>
    draftFromPurchaseOrder(purchaseOrder, currentChangeOrderDetail ?? currentChangeOrder),
  );
  const errorMessage =
    errorToMessage(createMutation.error) ??
    errorToMessage(updateMutation.error) ??
    errorToMessage(submitMutation.error) ??
    errorToMessage(cancelMutation.error);

  const summaryRows = useMemo(
    () => [
      { label: "Current", value: currentChangeOrder ? `${currentChangeOrder.number} (${currentChangeOrder.status.replaceAll("_", " ")})` : "None" },
      { label: "Latest", value: latestChangeOrder ? `${latestChangeOrder.number} (${latestChangeOrder.status.replaceAll("_", " ")})` : "None" },
      { label: "Count", value: String(changeOrders.length) },
      {
        label: "Material",
        value: currentChangeOrderDetail ? (currentChangeOrderDetail.materialChange ? "Yes" : "No") : "None",
      },
    ],
    [changeOrders.length, currentChangeOrder, currentChangeOrderDetail, latestChangeOrder],
  );

  return (
    <section id="change-orders" className="rounded-md border p-4" aria-label="Purchase order change orders">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h2 className="text-base font-semibold">Change orders</h2>
          <p className="text-sm text-muted-foreground">
            Draft, submit, and track purchase order changes from this workspace.
          </p>
        </div>
      </div>

      <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
        {summaryRows.map((row) => (
          <ChangeOrderFact key={row.label} label={row.label} value={row.value} />
        ))}
      </dl>

      {changeOrders.length > 0 ? (
        <div className="mt-4 overflow-hidden rounded-md border">
          <table className="w-full text-sm">
            <thead className="bg-muted/40 text-left">
              <tr>
                <th className="px-3 py-2 font-medium">Number</th>
                <th className="px-3 py-2 font-medium">Status</th>
                <th className="px-3 py-2 font-medium">Type</th>
                <th className="px-3 py-2 font-medium">Reason</th>
              </tr>
            </thead>
            <tbody>
              {changeOrders.map((changeOrder) => (
                <tr key={changeOrder.id} className="border-t">
                  <td className="px-3 py-2 font-medium">{changeOrder.number}</td>
                  <td className="px-3 py-2 capitalize">{changeOrder.status.replaceAll("_", " ")}</td>
                  <td className="px-3 py-2 capitalize">{changeOrder.changeType.replaceAll("_", " ")}</td>
                  <td className="px-3 py-2">{changeOrder.reason}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      <div className="mt-4 grid gap-3">
        <label className="space-y-1 text-sm">
          <span className="font-medium">Change type</span>
          <select
            className="h-9 rounded-md border bg-background px-3 text-sm"
            value={draft.changeType}
            onChange={(event) => setDraft((current) => ({ ...current, changeType: event.target.value as ChangeOrderDraft["changeType"] }))}
            disabled={!canEdit}
          >
            <option value="amendment">Amendment</option>
            <option value="partial_cancellation">Partial cancellation</option>
            <option value="full_cancellation">Full cancellation</option>
          </select>
        </label>
        <label className="space-y-1 text-sm">
          <span className="font-medium">Reason</span>
          <Textarea
            value={draft.reason}
            onChange={(event) => setDraft((current) => ({ ...current, reason: event.target.value }))}
            disabled={!canEdit}
          />
        </label>
        <div className="grid gap-3 md:grid-cols-2">
          <DraftField
            label="Requested PO date"
            type="date"
            value={draft.requestedPoDate}
            onChange={(value) => setDraft((current) => ({ ...current, requestedPoDate: value }))}
            disabled={!canEdit}
          />
          <DraftField
            label="Expected delivery date"
            type="date"
            value={draft.expectedDeliveryDate}
            onChange={(value) => setDraft((current) => ({ ...current, expectedDeliveryDate: value }))}
            disabled={!canEdit}
          />
          <DraftField
            label="Billing name"
            value={draft.billingName}
            onChange={(value) => setDraft((current) => ({ ...current, billingName: value }))}
            disabled={!canEdit}
          />
          <DraftField
            label="Shipping name"
            value={draft.shippingName}
            onChange={(value) => setDraft((current) => ({ ...current, shippingName: value }))}
            disabled={!canEdit}
          />
          <DraftField
            label="Delivery attention"
            value={draft.deliveryAttention}
            onChange={(value) => setDraft((current) => ({ ...current, deliveryAttention: value }))}
            disabled={!canEdit}
          />
          <DraftField
            label="Payment terms"
            value={draft.paymentTerms}
            onChange={(value) => setDraft((current) => ({ ...current, paymentTerms: value }))}
            disabled={!canEdit}
          />
          <DraftField
            label="Delivery terms"
            value={draft.deliveryTerms}
            onChange={(value) => setDraft((current) => ({ ...current, deliveryTerms: value }))}
            disabled={!canEdit}
          />
        </div>
        <label className="space-y-1 text-sm">
          <span className="font-medium">Buyer note</span>
          <Textarea
            value={draft.buyerNote}
            onChange={(event) => setDraft((current) => ({ ...current, buyerNote: event.target.value }))}
            disabled={!canEdit}
          />
        </label>
        <label className="space-y-1 text-sm">
          <span className="font-medium">Finance note</span>
          <Textarea
            value={draft.financeNote}
            onChange={(event) => setDraft((current) => ({ ...current, financeNote: event.target.value }))}
            disabled={!canEdit}
          />
        </label>
      </div>

      <div className="mt-4 space-y-2">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-sm font-semibold">Line changes</h3>
          <p className="text-xs text-muted-foreground">
            Update quantity, price, delivery, or cancellation intent for current committed lines.
          </p>
        </div>
        <div className="overflow-hidden rounded-md border">
          <table className="w-full text-sm">
            <thead className="bg-muted/40 text-left">
              <tr>
                <th className="px-3 py-2 font-medium">Line</th>
                <th className="px-3 py-2 font-medium">Action</th>
                <th className="px-3 py-2 font-medium">Qty</th>
                <th className="px-3 py-2 font-medium">Unit price</th>
                <th className="px-3 py-2 font-medium">Delivery date</th>
                <th className="px-3 py-2 font-medium">Delivery location</th>
                <th className="px-3 py-2 font-medium">Notes</th>
              </tr>
            </thead>
            <tbody>
              {draft.lines.map((line, index) => {
                const sourceLine = purchaseOrder.lines.find((purchaseOrderLine) => purchaseOrderLine.id === line.lineId) ?? null;
                return (
                  <tr key={line.lineId} className="border-t align-top">
                    <td className="px-3 py-2">
                      <div className="font-medium">{sourceLine?.description ?? `Line ${index + 1}`}</div>
                      <div className="text-xs text-muted-foreground">#{sourceLine?.lineNumber ?? index + 1}</div>
                    </td>
                    <td className="px-3 py-2">
                      <select
                        className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                        value={line.action}
                        aria-label={`${sourceLine?.description ?? `Line ${index + 1}`} action`}
                        onChange={(event) => updateDraftLine(index, "action", event.target.value as ChangeOrderLineDraft["action"], setDraft)}
                        disabled={!canEdit}
                      >
                        <option value="update">Update</option>
                        <option value="cancel">Cancel</option>
                      </select>
                    </td>
                    <td className="px-3 py-2">
                      <Input
                        value={line.quantity}
                        aria-label={`${sourceLine?.description ?? `Line ${index + 1}`} quantity`}
                        onChange={(event) => updateDraftLine(index, "quantity", event.target.value, setDraft)}
                        disabled={!canEdit || line.action === "cancel"}
                      />
                    </td>
                    <td className="px-3 py-2">
                      <Input
                        value={line.unitPrice}
                        aria-label={`${sourceLine?.description ?? `Line ${index + 1}`} unit price`}
                        onChange={(event) => updateDraftLine(index, "unitPrice", event.target.value, setDraft)}
                        disabled={!canEdit || line.action === "cancel"}
                      />
                    </td>
                    <td className="px-3 py-2">
                      <Input
                        type="date"
                        value={line.expectedDeliveryDate}
                        aria-label={`${sourceLine?.description ?? `Line ${index + 1}`} expected delivery date`}
                        onChange={(event) => updateDraftLine(index, "expectedDeliveryDate", event.target.value, setDraft)}
                        disabled={!canEdit || line.action === "cancel"}
                      />
                    </td>
                    <td className="px-3 py-2">
                      <Input
                        value={line.deliveryLocation}
                        aria-label={`${sourceLine?.description ?? `Line ${index + 1}`} delivery location`}
                        onChange={(event) => updateDraftLine(index, "deliveryLocation", event.target.value, setDraft)}
                        disabled={!canEdit || line.action === "cancel"}
                      />
                    </td>
                    <td className="px-3 py-2">
                      <Textarea
                        value={line.notes}
                        aria-label={`${sourceLine?.description ?? `Line ${index + 1}`} notes`}
                        onChange={(event) => updateDraftLine(index, "notes", event.target.value, setDraft)}
                        disabled={!canEdit || line.action === "cancel"}
                      />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        <Button
          type="button"
          disabled={!canEdit}
          onClick={() => {
            const payload = draftToPayload(draft, currentChangeOrder?.lockVersion ?? purchaseOrder.lockVersion);
            if (isExisting && currentChangeOrder) {
              updateMutation.mutate(
                { changeOrderId: currentChangeOrder.id, payload },
                {
                  onSuccess: () => setDraft(draftFromPurchaseOrder(purchaseOrder, null)),
                },
              );
              return;
            }

            createMutation.mutate(payload, {
              onSuccess: () => setDraft(draftFromPurchaseOrder(purchaseOrder, null)),
            });
          }}
        >
          {isExisting ? (updateMutation.isPending ? "Saving" : "Save change order") : createMutation.isPending ? "Creating" : "Create change order"}
        </Button>

        {currentChangeOrder && purchaseOrder.permissions.canSubmitChangeOrder ? (
          <Button
            type="button"
            disabled={isBusy}
            onClick={() => submitMutation.mutate({ changeOrderId: currentChangeOrder.id, payload: { lockVersion: currentChangeOrder.lockVersion } })}
          >
            {submitMutation.isPending ? "Submitting" : "Submit change order"}
          </Button>
        ) : null}

        {currentChangeOrder && purchaseOrder.permissions.canCancelChangeOrder ? (
          <Button
            type="button"
            variant="destructive"
            disabled={isBusy}
            onClick={() =>
              cancelMutation.mutate({
                changeOrderId: currentChangeOrder.id,
                payload: { lockVersion: currentChangeOrder.lockVersion, reason: currentChangeOrder.reason },
              })
            }
          >
            {cancelMutation.isPending ? "Cancelling" : "Cancel change order"}
          </Button>
        ) : null}
      </div>

      {errorMessage ? (
        <div role="alert" className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {errorMessage}
        </div>
      ) : null}
    </section>
  );
}

function DraftField({
  label,
  value,
  onChange,
  disabled,
  type = "text",
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  disabled: boolean;
  type?: "text" | "date";
}) {
  return (
    <label className="space-y-1 text-sm">
      <span className="font-medium">{label}</span>
      <Input type={type} value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled} />
    </label>
  );
}

function ChangeOrderFact({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="mt-1 break-words font-medium">{value}</dd>
    </div>
  );
}

function currentActiveChangeOrder(changeOrders: PurchaseOrderChangeOrder[]) {
  return [...changeOrders].reverse().find((changeOrder) => ["draft", "changes_requested"].includes(changeOrder.status)) ?? null;
}

function changeOrderFormKey(
  purchaseOrder: PurchaseOrder,
  currentChangeOrder: PurchaseOrderChangeOrder | null,
  currentChangeOrderDetail: PurchaseOrderChangeOrder | null,
) {
  return JSON.stringify([
    purchaseOrder.id,
    currentChangeOrder?.id ?? "new",
    currentChangeOrder?.status ?? "none",
    currentChangeOrder?.lockVersion ?? 0,
    currentChangeOrderDetail?.lockVersion ?? 0,
  ]);
}

function draftFromPurchaseOrder(
  purchaseOrder: PurchaseOrder,
  currentChangeOrder: PurchaseOrderChangeOrder | null,
): ChangeOrderDraft {
  const snapshot = (currentChangeOrder?.after ?? {}) as Record<string, unknown>;
  const currentLineChangeOrder = new Map(
    (currentChangeOrder?.lines ?? []).map((line) => [line.lineId, line] as const),
  );

  return {
    changeType: currentChangeOrder?.changeType ?? "amendment",
    reason: currentChangeOrder?.reason ?? "",
    requestedPoDate: stringValue(snapshot.requestedPoDate, purchaseOrder.requestedPoDate),
    expectedDeliveryDate: stringValue(snapshot.expectedDeliveryDate, purchaseOrder.expectedDeliveryDate),
    billingName: stringValue(snapshot.billingName, purchaseOrder.billingName),
    shippingName: stringValue(snapshot.shippingName, purchaseOrder.shippingName),
    deliveryAttention: stringValue(snapshot.deliveryAttention, purchaseOrder.deliveryAttention),
    paymentTerms: stringValue(snapshot.paymentTerms, purchaseOrder.paymentTerms),
    deliveryTerms: stringValue(snapshot.deliveryTerms, purchaseOrder.deliveryTerms),
    buyerNote: stringValue(snapshot.buyerNote, purchaseOrder.buyerNote),
    financeNote: stringValue(snapshot.financeNote, purchaseOrder.financeNote),
    lines: purchaseOrder.lines.map((line) => draftLineFromPurchaseOrderLine(line, currentLineChangeOrder.get(line.id))),
  };
}

function draftToPayload(draft: ChangeOrderDraft, lockVersion: number) {
  return {
    lockVersion,
    changeType: draft.changeType,
    reason: draft.reason,
    requestedPoDate: draft.requestedPoDate || null,
    expectedDeliveryDate: draft.expectedDeliveryDate || null,
    billingName: draft.billingName || null,
    shippingName: draft.shippingName || null,
    deliveryAttention: draft.deliveryAttention || null,
    paymentTerms: draft.paymentTerms || null,
    deliveryTerms: draft.deliveryTerms || null,
    buyerNote: draft.buyerNote || null,
    financeNote: draft.financeNote || null,
    billingAddress: null,
    shippingAddress: null,
    lines: draft.lines.map((line) => ({
      lineId: line.lineId,
      action: line.action,
      quantity: line.quantity || null,
      unitPrice: line.unitPrice || null,
      expectedDeliveryDate: line.expectedDeliveryDate || null,
      deliveryLocation: line.deliveryLocation || null,
      notes: line.notes || null,
    })),
  };
}

function draftLineFromPurchaseOrderLine(
  line: PurchaseOrderLine,
  changeOrderLine: PurchaseOrderChangeOrderLine | undefined,
): ChangeOrderLineDraft {
  return {
    lineId: line.id,
    action: changeOrderLine?.changeAction ?? "update",
    quantity: stringValue(changeOrderLine?.quantityAfter, line.quantity),
    unitPrice: stringValue(changeOrderLine?.unitPriceAfter, line.unitPrice),
    expectedDeliveryDate: stringValue(changeOrderLine?.expectedDeliveryDateAfter, line.expectedDeliveryDate),
    deliveryLocation: stringValue(changeOrderLine?.deliveryLocationAfter, line.deliveryLocation),
    notes: stringValue(changeOrderLine?.notesAfter, line.notes),
  };
}

function updateDraftLine<K extends keyof ChangeOrderLineDraft>(
  index: number,
  key: K,
  value: ChangeOrderLineDraft[K],
  setDraft: Dispatch<SetStateAction<ChangeOrderDraft>>,
) {
  setDraft((current) => ({
    ...current,
    lines: current.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, [key]: value } : line)),
  }));
}

function stringValue(value: unknown, fallback: string | null | undefined) {
  if (typeof value === "string") return value;
  return fallback ?? "";
}
