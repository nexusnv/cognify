"use client";

import type { Requisition } from "../types/requisition-view-model";

function formatTimestamp(value?: string | null) {
  if (!value) return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export function RequisitionCorrectionPanel({ requisition }: { requisition: Requisition }) {
  if (requisition.status !== "changes_requested") return null;

  const formattedChangesRequestedAt = formatTimestamp(requisition.changesRequestedAt);

  return (
    <section className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
      <h2 className="text-base font-semibold">Changes requested</h2>
      <p className="mt-2 whitespace-pre-wrap">
        {requisition.changeRequestReason ??
          "Please review the requested updates before resubmitting."}
      </p>
      {requisition.changeRequestFields?.length ? (
        <div className="mt-3">
          <p className="font-medium">Requested fields</p>
          <ul className="mt-2 list-disc space-y-1 pl-5">
            {requisition.changeRequestFields.map((field) => (
              <li key={field}>{field}</li>
            ))}
          </ul>
        </div>
      ) : null}
      <p className="mt-3 text-xs">
        {requisition.changesRequestedBy
          ? `Requested by ${requisition.changesRequestedBy.name}`
          : "Requested by a reviewer"}
        {formattedChangesRequestedAt ? ` on ${formattedChangesRequestedAt}` : ""}. Update the draft,
        then resubmit from this workspace when you are ready.
      </p>
    </section>
  );
}
