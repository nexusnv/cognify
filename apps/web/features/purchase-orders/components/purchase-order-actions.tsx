"use client";

import { useState } from "react";
import { Button, Input, Textarea } from "@cognify/ui";
import type { PurchaseOrder } from "@cognify/api-client/schemas";
import {
  useCancelPurchaseOrder,
  useMarkPurchaseOrderReadyForReview,
  useUpdatePurchaseOrder,
} from "../hooks/use-purchase-order-actions";

type DraftFields = {
  requestedPoDate: string;
  expectedDeliveryDate: string;
  billingName: string;
  shippingName: string;
  deliveryAttention: string;
  paymentTerms: string;
  deliveryTerms: string;
  buyerNote: string;
  financeNote: string;
};

export function PurchaseOrderActions({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  const updateMutation = useUpdatePurchaseOrder(purchaseOrder.id);
  const readyMutation = useMarkPurchaseOrderReadyForReview(purchaseOrder.id);
  const cancelMutation = useCancelPurchaseOrder(purchaseOrder.id);
  const [draft, setDraft] = useState<DraftFields>({
    requestedPoDate: purchaseOrder.requestedPoDate ?? "",
    expectedDeliveryDate: purchaseOrder.expectedDeliveryDate ?? "",
    billingName: purchaseOrder.billingName ?? "",
    shippingName: purchaseOrder.shippingName ?? "",
    deliveryAttention: purchaseOrder.deliveryAttention ?? "",
    paymentTerms: purchaseOrder.paymentTerms ?? "",
    deliveryTerms: purchaseOrder.deliveryTerms ?? "",
    buyerNote: purchaseOrder.buyerNote ?? "",
    financeNote: purchaseOrder.financeNote ?? "",
  });
  const isBusy = updateMutation.isPending || readyMutation.isPending || cancelMutation.isPending;
  const isEditable = purchaseOrder.permissions.canUpdate && !isBusy;

  return (
    <section id="draft-fields" className="rounded-md border p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold">Draft fields</h2>
          <p className="text-sm text-muted-foreground">
            Operational notes stay editable while the purchase order is in draft.
          </p>
        </div>
      </div>

      <fieldset
        className="mt-4 space-y-3"
        disabled={!isEditable}
        aria-label="Purchase order draft fields"
      >
        <legend className="sr-only">Purchase order draft fields</legend>
        <div className="grid gap-3 md:grid-cols-2">
          <label className="space-y-1 text-sm">
            <span className="font-medium">Requested PO date</span>
            <Input
              type="date"
              value={draft.requestedPoDate}
              onChange={(event) => setDraft((current) => ({ ...current, requestedPoDate: event.target.value }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Expected delivery date</span>
            <Input
              type="date"
              value={draft.expectedDeliveryDate}
              onChange={(event) => setDraft((current) => ({ ...current, expectedDeliveryDate: event.target.value }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Billing name</span>
            <Input
              value={draft.billingName}
              onChange={(event) => setDraft((current) => ({ ...current, billingName: event.target.value }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Shipping name</span>
            <Input
              value={draft.shippingName}
              onChange={(event) => setDraft((current) => ({ ...current, shippingName: event.target.value }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Delivery attention</span>
            <Input
              value={draft.deliveryAttention}
              onChange={(event) => setDraft((current) => ({ ...current, deliveryAttention: event.target.value }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Payment terms</span>
            <Input
              value={draft.paymentTerms}
              onChange={(event) => setDraft((current) => ({ ...current, paymentTerms: event.target.value }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Delivery terms</span>
            <Input
              value={draft.deliveryTerms}
              onChange={(event) => setDraft((current) => ({ ...current, deliveryTerms: event.target.value }))}
            />
          </label>
        </div>

        <div className="grid gap-3">
          <label className="space-y-1 text-sm">
            <span className="font-medium">Buyer note</span>
            <Textarea
              value={draft.buyerNote}
              onChange={(event) => setDraft((current) => ({ ...current, buyerNote: event.target.value }))}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Finance note</span>
            <Textarea
              value={draft.financeNote}
              onChange={(event) => setDraft((current) => ({ ...current, financeNote: event.target.value }))}
            />
          </label>
        </div>
      </fieldset>

      <div className="mt-4 flex flex-wrap gap-2">
        <Button
          type="button"
          variant="outline"
          disabled={!purchaseOrder.permissions.canUpdate || isBusy}
          onClick={() =>
            updateMutation.mutate({
              lockVersion: purchaseOrder.lockVersion,
              requestedPoDate: draft.requestedPoDate || null,
              expectedDeliveryDate: draft.expectedDeliveryDate || null,
              billingName: draft.billingName || null,
              shippingName: draft.shippingName || null,
              deliveryAttention: draft.deliveryAttention || null,
              paymentTerms: draft.paymentTerms || null,
              deliveryTerms: draft.deliveryTerms || null,
              buyerNote: draft.buyerNote || null,
              financeNote: draft.financeNote || null,
              billingAddress: purchaseOrder.billingAddress ?? null,
              shippingAddress: purchaseOrder.shippingAddress ?? null,
            })
          }
        >
          {updateMutation.isPending ? "Saving" : "Save draft"}
        </Button>
        <Button
          type="button"
          disabled={!purchaseOrder.permissions.canMarkReadyForReview || isBusy}
          onClick={() => readyMutation.mutate({ lockVersion: purchaseOrder.lockVersion })}
        >
          {readyMutation.isPending ? "Marking ready" : "Mark ready for review"}
        </Button>
      </div>
    </section>
  );
}
