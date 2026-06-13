"use client";

import { useState } from "react";
import { Button, Input, Textarea } from "@cognify/ui";
import type {
  AddFulfillmentTrackingEventRequest,
  CreateShipmentRequest,
  FulfillmentTrackingEventStatus,
  PurchaseOrder,
  Shipment,
  ShipmentLine,
  UpdateShipmentRequest,
  UpdateShipmentBackorderRequest,
} from "@cognify/api-client/schemas";
import {
  useCancelShipment,
  useCreatePurchaseOrderShipment,
  useCreateShipmentTrackingEvent,
  usePurchaseOrderFulfillment,
  usePurchaseOrderShipments,
  useShipmentTrackingEvents,
  useUpdateShipment,
  useUpdateShipmentLineBackorder,
} from "../hooks/use-purchase-order-fulfillment";
import { errorToMessage } from "../utils/error-helpers";

export function PurchaseOrderFulfillmentPanel({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  const fulfillmentQuery = usePurchaseOrderFulfillment(purchaseOrder.id);
  const shipmentsQuery = usePurchaseOrderShipments(purchaseOrder.id);
  const createShipmentMutation = useCreatePurchaseOrderShipment(purchaseOrder.id);
  const [showForm, setShowForm] = useState(false);
  const [carrierName, setCarrierName] = useState("");
  const [trackingReference, setTrackingReference] = useState("");
  const [shipmentDate, setShipmentDate] = useState(new Date().toISOString().slice(0, 10));
  const [estimatedArrivalDate, setEstimatedArrivalDate] = useState("");
  const [notes, setNotes] = useState("");
  const [lineDrafts, setLineDrafts] = useState(() => createLineDrafts(purchaseOrder.lines));

  const fulfillment = fulfillmentQuery.data;
  const shipments = shipmentsQuery.data ?? [];
  const errorMessage = errorToMessage(createShipmentMutation.error);

  const updateLineDraft = (
    purchaseOrderLineId: string,
    field: Exclude<keyof LineDraft, "purchaseOrderLineId">,
    value: string,
  ) => {
    setLineDrafts((current) =>
      current.map((draft) =>
        draft.purchaseOrderLineId === purchaseOrderLineId
          ? {
              ...draft,
              [field]: value,
            }
          : draft,
      ),
    );
  };

  function handleCreateShipment() {
    const lines = lineDrafts
      .filter((line) => Number(line.quantityShipped) > 0)
      .map((line) => ({
        purchaseOrderLineId: line.purchaseOrderLineId,
        quantityShipped: line.quantityShipped,
        backorderQuantity: line.backorderQuantity || undefined,
        backorderExpectedAt: line.backorderExpectedAt || undefined,
        notes: line.notes || undefined,
      }));

    const payload: CreateShipmentRequest = {
      lockVersion: purchaseOrder.lockVersion,
      carrierName: carrierName || null,
      trackingReference: trackingReference || null,
      shipmentDate,
      estimatedArrivalDate: estimatedArrivalDate || null,
      notes: notes || null,
      lines,
    };

    createShipmentMutation.mutate(payload, {
      onSuccess: () => {
        setShowForm(false);
        setCarrierName("");
        setTrackingReference("");
        setEstimatedArrivalDate("");
        setNotes("");
        setLineDrafts(createLineDrafts(purchaseOrder.lines));
      },
    });
  }

  return (
    <section className="rounded-md border p-4" aria-label="Fulfillment tracking">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h2 className="text-base font-semibold">Fulfillment tracking</h2>
          <p className="text-sm text-muted-foreground">
            {shipmentsQuery.isLoading ? "Loading shipments..." : `${shipments.length} shipment(s) recorded`}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <StatusBadge label={humanizeStatus(fulfillment?.overallStatus ?? "pending_shipment")} />
          {purchaseOrder.permissions.canCreateShipment && !showForm ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => {
                setLineDrafts(createLineDrafts(purchaseOrder.lines));
                setShowForm(true);
              }}
            >
              Record shipment
            </Button>
          ) : null}
        </div>
      </div>

      {showForm ? (
        <div className="mt-4 space-y-3 rounded-md border bg-muted/20 p-3">
          <h3 className="text-sm font-semibold">New shipment</h3>
          <div className="grid gap-3 md:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span className="font-medium">Carrier</span>
              <Input value={carrierName} onChange={(event) => setCarrierName(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Tracking reference</span>
              <Input value={trackingReference} onChange={(event) => setTrackingReference(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Shipment date</span>
              <Input type="date" value={shipmentDate} onChange={(event) => setShipmentDate(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Estimated arrival</span>
              <Input type="date" value={estimatedArrivalDate} onChange={(event) => setEstimatedArrivalDate(event.target.value)} />
            </label>
          </div>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Notes</span>
            <Textarea value={notes} onChange={(event) => setNotes(event.target.value)} />
          </label>
          <div className="space-y-3">
            <div>
              <h4 className="text-sm font-semibold">Line quantities</h4>
              <p className="text-xs text-muted-foreground">
                Enter the shipped quantity for each purchase order line. Leave a line blank if it is not part of this shipment.
              </p>
            </div>
            <div className="space-y-3">
              {purchaseOrder.lines.map((line) => {
                const draft = lineDrafts.find((item) => item.purchaseOrderLineId === line.id) ?? createEmptyLineDraft(line.id);

                return (
                  <div key={line.id} className="rounded-md border bg-background p-3">
                    <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p className="text-sm font-medium">
                          Line {line.lineNumber} - {line.description}
                        </p>
                        <p className="text-xs text-muted-foreground">
                          Ordered {line.quantity} {line.unit}
                        </p>
                      </div>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                      <label className="space-y-1 text-sm">
                        <span className="font-medium">Line {line.lineNumber} quantity shipped</span>
                        <Input
                          type="number"
                          step="0.0001"
                          min="0"
                          value={draft.quantityShipped}
                          onChange={(event) => updateLineDraft(line.id, "quantityShipped", event.target.value)}
                        />
                      </label>
                      <label className="space-y-1 text-sm">
                        <span className="font-medium">Line {line.lineNumber} backorder quantity</span>
                        <Input
                          type="number"
                          step="0.0001"
                          min="0"
                          value={draft.backorderQuantity}
                          onChange={(event) => updateLineDraft(line.id, "backorderQuantity", event.target.value)}
                        />
                      </label>
                      <label className="space-y-1 text-sm">
                        <span className="font-medium">Line {line.lineNumber} backorder expected at</span>
                        <Input
                          type="date"
                          value={draft.backorderExpectedAt}
                          onChange={(event) => updateLineDraft(line.id, "backorderExpectedAt", event.target.value)}
                        />
                      </label>
                      <label className="space-y-1 text-sm">
                        <span className="font-medium">Line {line.lineNumber} notes</span>
                        <Textarea
                          value={draft.notes}
                          onChange={(event) => updateLineDraft(line.id, "notes", event.target.value)}
                        />
                      </label>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
          <div className="flex gap-2">
            <Button
              type="button"
              disabled={!lineDrafts.some((line) => Number(line.quantityShipped) > 0) || createShipmentMutation.isPending}
              onClick={handleCreateShipment}
            >
              {createShipmentMutation.isPending ? "Saving shipment" : "Save shipment"}
            </Button>
            <Button type="button" variant="outline" onClick={() => setShowForm(false)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : null}

      {fulfillment ? (
        <div className="mt-4 grid gap-3 md:grid-cols-3">
          <MetricCard label="Late lines" value={String(fulfillment.lateDeliveryCount)} />
          <MetricCard label="Delivered lines" value={`${fulfillment.deliveredLineCount}/${fulfillment.totalLineCount}`} />
          <MetricCard label="Shipments" value={String(fulfillment.shipmentCount)} />
        </div>
      ) : null}

      {shipments.length > 0 ? (
        <div className="mt-4 space-y-3">
          {shipments.map((shipment) => (
            <ShipmentCard key={shipment.id} purchaseOrderId={purchaseOrder.id} shipment={shipment} />
          ))}
        </div>
      ) : null}

      {!shipmentsQuery.isLoading && shipments.length === 0 && !showForm ? (
        <div className="mt-4 rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
          No shipments recorded. Expected delivery by {purchaseOrder.expectedDeliveryDate ?? "TBD"}.
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

type LineDraft = {
  purchaseOrderLineId: string;
  quantityShipped: string;
  backorderQuantity: string;
  backorderExpectedAt: string;
  notes: string;
};

function createEmptyLineDraft(purchaseOrderLineId: string): LineDraft {
  return {
    purchaseOrderLineId,
    quantityShipped: "",
    backorderQuantity: "0.0000",
    backorderExpectedAt: "",
    notes: "",
  };
}

function createLineDrafts(lines: PurchaseOrder["lines"]): LineDraft[] {
  return lines.map((line) => createEmptyLineDraft(line.id));
}

function MetricCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border bg-muted/20 p-3">
      <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="mt-1 text-base font-semibold">{value}</p>
    </div>
  );
}

function StatusBadge({ label }: { label: string }) {
  return <span className="rounded-full border px-2 py-1 text-xs font-medium">{label}</span>;
}

function humanizeStatus(value: string) {
  return value.replaceAll("_", " ");
}

function ShipmentCard({ purchaseOrderId, shipment }: { purchaseOrderId: string; shipment: Shipment }) {
  const trackingEventsQuery = useShipmentTrackingEvents(shipment.id);
  const addTrackingEventMutation = useCreateShipmentTrackingEvent(purchaseOrderId, shipment.id);
  const updateShipmentMutation = useUpdateShipment(purchaseOrderId, shipment.id);
  const cancelShipmentMutation = useCancelShipment(purchaseOrderId, shipment.id);
  const [showTrackingForm, setShowTrackingForm] = useState(false);
  const [showEditForm, setShowEditForm] = useState(false);
  const [trackingStatus, setTrackingStatus] = useState<FulfillmentTrackingEventStatus>("in_transit");
  const [trackingLocation, setTrackingLocation] = useState("");
  const [trackingNotes, setTrackingNotes] = useState("");
  const [editCarrierName, setEditCarrierName] = useState(shipment.carrierName ?? "");
  const [editTrackingReference, setEditTrackingReference] = useState(shipment.trackingReference ?? "");
  const [editShipmentDate, setEditShipmentDate] = useState(shipment.shipmentDate);
  const [editEstimatedArrivalDate, setEditEstimatedArrivalDate] = useState(shipment.estimatedArrivalDate ?? "");
  const [editActualDeliveryDate, setEditActualDeliveryDate] = useState(shipment.actualDeliveryDate ?? "");
  const [editNotes, setEditNotes] = useState(shipment.notes ?? "");
  const trackingErrorMessage = errorToMessage(addTrackingEventMutation.error);
  const editErrorMessage = errorToMessage(updateShipmentMutation.error ?? cancelShipmentMutation.error);

  function openEditForm() {
    setEditCarrierName(shipment.carrierName ?? "");
    setEditTrackingReference(shipment.trackingReference ?? "");
    setEditShipmentDate(shipment.shipmentDate);
    setEditEstimatedArrivalDate(shipment.estimatedArrivalDate ?? "");
    setEditActualDeliveryDate(shipment.actualDeliveryDate ?? "");
    setEditNotes(shipment.notes ?? "");
    setShowEditForm(true);
  }

  function handleAddTrackingEvent() {
    const payload: AddFulfillmentTrackingEventRequest = {
      status: trackingStatus,
      occurredAt: new Date().toISOString(),
      location: trackingLocation || undefined,
      notes: trackingNotes || undefined,
    };

    addTrackingEventMutation.mutate(payload, {
      onSuccess: () => {
        setShowTrackingForm(false);
        setTrackingStatus("in_transit");
        setTrackingLocation("");
        setTrackingNotes("");
      },
    });
  }

  function handleSaveShipment() {
    const payload: UpdateShipmentRequest = {
      lockVersion: shipment.lockVersion,
      carrierName: editCarrierName || null,
      trackingReference: editTrackingReference || null,
      shipmentDate: editShipmentDate || undefined,
      estimatedArrivalDate: editEstimatedArrivalDate || null,
      actualDeliveryDate: editActualDeliveryDate || null,
      notes: editNotes || null,
    };

    updateShipmentMutation.mutate(payload, {
      onSuccess: () => {
        setShowEditForm(false);
      },
    });
  }

  function handleCancelShipment() {
    if (!window.confirm("Cancel this shipment?")) {
      return;
    }

    cancelShipmentMutation.mutate(
      {
        lockVersion: shipment.lockVersion,
        notes: shipment.notes ?? null,
      },
      {
        onSuccess: () => {
          setShowEditForm(false);
          setShowTrackingForm(false);
        },
      },
    );
  }

  return (
    <div className="rounded-md border p-3 text-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="font-medium">{shipment.number}</p>
          <p className="text-muted-foreground">
            {shipment.carrierName ?? "Carrier pending"}
            {shipment.trackingReference ? ` — ${shipment.trackingReference}` : ""}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <StatusBadge label={humanizeStatus(shipment.status)} />
          {shipment.status !== "delivered" && shipment.status !== "cancelled" && !showEditForm ? (
            <Button type="button" variant="outline" size="sm" onClick={openEditForm}>
              Edit
            </Button>
          ) : null}
          {shipment.status !== "delivered" && shipment.status !== "cancelled" ? (
            <Button type="button" variant="outline" size="sm" onClick={handleCancelShipment}>
              Cancel shipment
            </Button>
          ) : null}
          {!showTrackingForm ? (
            <Button type="button" variant="outline" size="sm" onClick={() => setShowTrackingForm(true)}>
              Add tracking event
            </Button>
          ) : null}
        </div>
      </div>
      <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
        <span>Shipment date: {shipment.shipmentDate}</span>
        {shipment.estimatedArrivalDate ? <span>ETA: {shipment.estimatedArrivalDate}</span> : null}
      </div>
      {showEditForm ? (
        <div className="mt-3 space-y-3 rounded-md border bg-muted/20 p-3">
          <h4 className="text-sm font-semibold">Edit shipment</h4>
          <div className="grid gap-3 md:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span className="font-medium">Carrier</span>
              <Input value={editCarrierName} onChange={(event) => setEditCarrierName(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Tracking reference</span>
              <Input value={editTrackingReference} onChange={(event) => setEditTrackingReference(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Shipment date</span>
              <Input type="date" value={editShipmentDate} onChange={(event) => setEditShipmentDate(event.target.value)} />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Estimated arrival</span>
              <Input
                type="date"
                value={editEstimatedArrivalDate}
                onChange={(event) => setEditEstimatedArrivalDate(event.target.value)}
              />
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Actual delivery</span>
              <Input
                type="date"
                value={editActualDeliveryDate}
                onChange={(event) => setEditActualDeliveryDate(event.target.value)}
              />
            </label>
          </div>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Notes</span>
            <Textarea value={editNotes} onChange={(event) => setEditNotes(event.target.value)} />
          </label>
          <div className="flex gap-2">
            <Button type="button" disabled={updateShipmentMutation.isPending} onClick={handleSaveShipment}>
              {updateShipmentMutation.isPending ? "Saving shipment" : "Save shipment"}
            </Button>
            <Button type="button" variant="outline" onClick={() => setShowEditForm(false)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : null}
      <div className="mt-3 space-y-2">
        {shipment.lines.map((line) => (
          <ShipmentLineCard key={line.id} purchaseOrderId={purchaseOrderId} shipmentId={shipment.id} line={line} />
        ))}
      </div>

      {showTrackingForm ? (
        <div className="mt-3 space-y-3 rounded-md border bg-muted/20 p-3">
          <div className="grid gap-3 md:grid-cols-2">
            <label className="space-y-1 text-sm">
              <span className="font-medium">Tracking status</span>
              <select
                className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                value={trackingStatus}
                onChange={(event) => setTrackingStatus(event.target.value as FulfillmentTrackingEventStatus)}
              >
                <option value="created">created</option>
                <option value="shipped">shipped</option>
                <option value="in_transit">in transit</option>
                <option value="customs">customs</option>
                <option value="arrived">arrived</option>
                <option value="out_for_delivery">out for delivery</option>
                <option value="delivered">delivered</option>
                <option value="delayed">delayed</option>
                <option value="exception">exception</option>
              </select>
            </label>
            <label className="space-y-1 text-sm">
              <span className="font-medium">Tracking location</span>
              <Input value={trackingLocation} onChange={(event) => setTrackingLocation(event.target.value)} />
            </label>
          </div>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Tracking notes</span>
            <Textarea value={trackingNotes} onChange={(event) => setTrackingNotes(event.target.value)} />
          </label>
          <div className="flex gap-2">
            <Button type="button" disabled={addTrackingEventMutation.isPending} onClick={handleAddTrackingEvent}>
              {addTrackingEventMutation.isPending ? "Saving tracking event" : "Save tracking event"}
            </Button>
            <Button type="button" variant="outline" onClick={() => setShowTrackingForm(false)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : null}

      <div className="mt-3 space-y-2">
        {trackingEventsQuery.isLoading ? (
          <p className="text-xs text-muted-foreground">Loading tracking events...</p>
        ) : trackingEventsQuery.data && trackingEventsQuery.data.length > 0 ? (
          trackingEventsQuery.data.map((event) => (
            <div key={event.id} className="rounded-md border-l-2 border-muted px-3 py-2">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-medium">{humanizeStatus(event.status)}</span>
                {event.location ? <span className="text-xs text-muted-foreground">{event.location}</span> : null}
              </div>
              {event.notes ? <p className="mt-1 text-xs text-muted-foreground">{event.notes}</p> : null}
            </div>
          ))
        ) : (
          <p className="text-xs text-muted-foreground">No tracking events recorded yet.</p>
        )}
      </div>

      {trackingErrorMessage ? (
        <div role="alert" className="mt-3 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {trackingErrorMessage}
        </div>
      ) : null}
      {editErrorMessage ? (
        <div role="alert" className="mt-3 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {editErrorMessage}
        </div>
      ) : null}
    </div>
  );
}

function ShipmentLineCard({
  purchaseOrderId,
  shipmentId,
  line,
}: {
  purchaseOrderId: string;
  shipmentId: string;
  line: ShipmentLine;
}) {
  const updateBackorderMutation = useUpdateShipmentLineBackorder(purchaseOrderId, shipmentId, line.id);
  const [showBackorderForm, setShowBackorderForm] = useState(false);
  const [backorderQuantity, setBackorderQuantity] = useState(line.backorderQuantity);
  const [backorderExpectedAt, setBackorderExpectedAt] = useState(line.backorderExpectedAt ?? "");
  const backorderErrorMessage = errorToMessage(updateBackorderMutation.error);

  function handleShowBackorderForm() {
    setBackorderQuantity(line.backorderQuantity);
    setBackorderExpectedAt(line.backorderExpectedAt ?? "");
    setShowBackorderForm(true);
  }

  function handleSaveBackorder() {
    const payload: UpdateShipmentBackorderRequest = {
      backorderQuantity,
      backorderExpectedAt: backorderExpectedAt || undefined,
    };

    updateBackorderMutation.mutate(payload, {
      onSuccess: () => {
        setShowBackorderForm(false);
      },
    });
  }

  return (
    <div className="rounded-md border bg-muted/10 p-3">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="space-y-1 text-xs text-muted-foreground">
          <p>Line {line.lineNumber}</p>
          <p>Shipped: {line.quantityShipped}</p>
          <p>Delivered: {line.quantityDelivered}</p>
          <p>Backorder: {line.backorderQuantity}</p>
          {line.backorderExpectedAt ? <p>Expected backorder delivery: {line.backorderExpectedAt}</p> : null}
        </div>
        {!showBackorderForm ? (
          <Button type="button" variant="outline" size="sm" onClick={handleShowBackorderForm}>
            Update backorder
          </Button>
        ) : null}
      </div>

      {showBackorderForm ? (
        <div className="mt-3 grid gap-3 md:grid-cols-2">
          <label className="space-y-1 text-sm">
            <span className="font-medium">Line {line.lineNumber} backorder quantity</span>
            <Input
              type="number"
              step="0.0001"
              min="0"
              value={backorderQuantity}
              onChange={(event) => setBackorderQuantity(event.target.value)}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span className="font-medium">Line {line.lineNumber} backorder expected at</span>
            <Input type="date" value={backorderExpectedAt} onChange={(event) => setBackorderExpectedAt(event.target.value)} />
          </label>
          <div className="md:col-span-2 flex gap-2">
            <Button type="button" disabled={updateBackorderMutation.isPending} onClick={handleSaveBackorder}>
              {updateBackorderMutation.isPending ? "Saving backorder" : "Save backorder"}
            </Button>
            <Button type="button" variant="outline" onClick={() => setShowBackorderForm(false)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : null}

      {backorderErrorMessage ? (
        <div role="alert" className="mt-3 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {backorderErrorMessage}
        </div>
      ) : null}
    </div>
  );
}
