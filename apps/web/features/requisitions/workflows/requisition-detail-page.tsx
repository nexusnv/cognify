"use client";

import Link from "next/link";
import { ArrowLeft, Pencil, Send } from "lucide-react";
import { RequisitionActivityTimeline } from "../components/requisition-activity-timeline";
import { RequisitionStatusBadge } from "../components/requisition-status-badge";
import { SubmissionChecklist } from "../components/submission-checklist";
import { useRequisition, useRequisitionActivity } from "../hooks/use-requisition";
import { formatMoney } from "../utils/requisition-totals";

export function RequisitionDetailPage({ requisitionId }: { requisitionId: string }) {
  const requisitionQuery = useRequisition(requisitionId);
  const activityQuery = useRequisitionActivity(requisitionId);
  const requisition = requisitionQuery.data;

  if (requisitionQuery.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading requisition workspace</div>;
  }

  if (requisitionQuery.isError || !requisition) {
    return <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">Requisition could not be loaded.</div>;
  }

  return (
    <section className="space-y-5">
      <Link href="/requisitions" className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm">
        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
        Back to requisitions
      </Link>
      <div className="grid gap-4 border-b pb-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <p className="font-mono text-xs text-muted-foreground">{requisition.number}</p>
            <RequisitionStatusBadge status={requisition.status} />
          </div>
          <h1 className="mt-3 text-2xl font-semibold">{requisition.title}</h1>
          <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
            <div>
              <dt className="text-muted-foreground">Estimated total</dt>
              <dd className="font-mono font-semibold tabular-nums">{formatMoney(requisition.estimatedTotal, requisition.currency)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Needed by</dt>
              <dd>{requisition.neededByDate}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Requester</dt>
              <dd>{requisition.requester.name}</dd>
            </div>
          </dl>
        </div>
        <div className="space-y-2 rounded-md border p-3">
          {requisition.permissions.canUpdate ? (
            <Link href={`/requisitions/${requisition.id}/edit`} className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium">
              <Pencil className="h-4 w-4" aria-hidden="true" />
              Edit draft
            </Link>
          ) : null}
          {requisition.permissions.canSubmit ? (
            <Link href={`/requisitions/${requisition.id}/edit`} className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-foreground px-3 text-sm font-medium text-background">
              <Send className="h-4 w-4" aria-hidden="true" />
              Submit
            </Link>
          ) : null}
          {!requisition.permissions.canSubmit && !requisition.permissions.canUpdate ? (
            <p className="text-sm text-muted-foreground">Requester editing is locked after submission.</p>
          ) : null}
        </div>
      </div>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="space-y-5">
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Overview</h2>
            <p className="mt-2 text-sm leading-6">{requisition.businessJustification}</p>
          </section>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Line items</h2>
            <div className="mt-3 space-y-2">
              {requisition.lineItems.map((item) => (
                <div key={item.id ?? item.name} className="grid gap-2 rounded-md border p-3 text-sm sm:grid-cols-[minmax(0,1fr)_7rem_8rem]">
                  <span className="font-medium">{item.name}</span>
                  <span className="tabular-nums">
                    {item.quantity} {item.unit}
                  </span>
                  <span className="font-mono tabular-nums">
                    {formatMoney(item.estimatedLineTotal ?? item.quantity * item.estimatedUnitPrice, item.currency)}
                  </span>
                </div>
              ))}
            </div>
          </section>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Activity</h2>
            <div className="mt-3">
              <RequisitionActivityTimeline events={activityQuery.data?.data ?? []} />
            </div>
          </section>
        </div>
        <div className="space-y-5">
          <SubmissionChecklist
            values={{
              title: requisition.title,
              businessJustification: requisition.businessJustification,
              neededByDate: requisition.neededByDate,
              department: requisition.department ?? "",
              costCenter: requisition.costCenter ?? "",
              deliveryLocation: requisition.deliveryLocation ?? "",
              currency: requisition.currency,
              lineItems: requisition.lineItems,
            }}
          />
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Approval readiness</h2>
            <p className="mt-2 text-sm text-muted-foreground">Approval routing will attach here after the submitted workflow is active.</p>
          </section>
        </div>
      </div>
    </section>
  );
}
