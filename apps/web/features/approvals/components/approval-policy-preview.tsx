"use client";

import { AlertTriangle } from "lucide-react";
import { ApprovalStageMap } from "./approval-stage-map";
import { useApprovalPreview } from "../hooks/use-approval-preview";
import type {
  ApprovalPreview,
  ApprovalPreviewContext,
  ApprovalPolicyFormValues,
} from "../types/approval-view-model";

type ApprovalPolicyPreviewProps = {
  values?: ApprovalPolicyFormValues;
  preview?: ApprovalPreview;
  context?: ApprovalPreviewContext;
  title?: string;
  description?: string;
};

export function ApprovalPolicyPreview({
  values,
  preview,
  context,
  title = "Approval preview",
  description = "Loading approval route preview.",
}: ApprovalPolicyPreviewProps) {
  const hasEmptyDraft = preview === undefined && (values?.routeTemplate.stages.length ?? 0) === 0;
  const previewQuery = useApprovalPreview(values, context, preview === undefined && !hasEmptyDraft);
  const data = preview ?? previewQuery.data;

  if (previewQuery.isError && preview === undefined && !hasEmptyDraft) {
    return (
      <section
        className="rounded-md border border-red-300 bg-red-50 p-4"
        aria-labelledby="approval-policy-preview-title"
      >
        <h2 id="approval-policy-preview-title" className="text-base font-semibold text-red-900">
          {title}
        </h2>
        <p className="mt-2 text-sm text-red-900">Unable to load approval route preview.</p>
      </section>
    );
  }

  if (!data && hasEmptyDraft) {
    return (
      <section className="rounded-md border p-4" aria-labelledby="approval-policy-preview-title">
        <div className="flex flex-col gap-1 border-b pb-3">
          <h2 id="approval-policy-preview-title" className="text-base font-semibold">
            {title}
          </h2>
          <p className="text-sm text-muted-foreground">{description}</p>
          <p className="text-sm text-muted-foreground">No approval stages configured.</p>
        </div>
      </section>
    );
  }

  if (!data) {
    return (
      <section className="rounded-md border p-4" aria-labelledby="approval-policy-preview-title">
        <div className="flex flex-col gap-1 border-b pb-3">
          <h2 id="approval-policy-preview-title" className="text-base font-semibold">
            {title}
          </h2>
          <p className="text-sm text-muted-foreground">{description}</p>
        </div>
      </section>
    );
  }

  return (
    <section className="rounded-md border p-4" aria-labelledby="approval-policy-preview-title">
      <div className="flex flex-col gap-1 border-b pb-3">
        <h2 id="approval-policy-preview-title" className="text-base font-semibold">
          {title}
        </h2>
        <p className="text-sm text-muted-foreground">{description}</p>
        <p className="text-sm text-muted-foreground">
          Previewing {data.matchedPolicy.name} version {data.matchedVersion.versionNumber}.
        </p>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div className="space-y-3">
          <div className="rounded-md border p-3">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-sm font-medium">{data.matchedPolicy.name}</span>
              <span className="text-xs uppercase text-muted-foreground">
                {data.matchedPolicy.status}
              </span>
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              Matched policy {data.matchedPolicy.id} and version {data.matchedVersion.id}.
            </p>
            <p className="mt-2 text-sm text-muted-foreground">
              Estimated due date: {data.estimatedDueAt ?? "Not available"}
            </p>
          </div>

          {data.warnings.length > 0 ? (
            <div className="rounded-md border border-amber-200 bg-amber-50 p-3">
              <div className="flex items-center gap-2 text-sm font-medium text-amber-900">
                <AlertTriangle className="h-4 w-4" aria-hidden="true" />
                Preview warnings
              </div>
              <ul className="mt-2 space-y-1 text-sm text-amber-900">
                {data.warnings.map((warning) => (
                  <li key={warning.code}>{warning.message}</li>
                ))}
              </ul>
            </div>
          ) : null}
        </div>

        <div className="space-y-3 rounded-md border p-3">
          <div className="flex items-center justify-between gap-3">
            <h3 className="text-sm font-semibold">Matched conditions</h3>
            <span className="text-xs text-muted-foreground">
              {data.createsTasks ? "Creates tasks" : "Computed preview only"}
            </span>
          </div>
          {data.matchedConditions.length > 0 ? (
            <ul className="space-y-2">
              {data.matchedConditions.map((condition) => (
                <li key={`${condition.field}-${condition.operator}`} className="text-xs text-muted-foreground">
                  <span className="block font-medium text-foreground">{condition.summary}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-xs text-muted-foreground">No policy rules matched.</p>
          )}
        </div>
      </div>

      <div className="mt-4">
        <ApprovalStageMap stages={data.stages} />
      </div>
    </section>
  );
}
