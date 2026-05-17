"use client";

import { ApprovalStageMap } from "./approval-stage-map";
import type { ApprovalPolicyFormValues } from "../types/approval-view-model";

export function ApprovalPolicyPreview({ values }: { values: ApprovalPolicyFormValues }) {
  return (
    <section className="rounded-md border p-4" aria-labelledby="approval-policy-preview-title">
      <div className="flex flex-col gap-1 border-b pb-3">
        <h2 id="approval-policy-preview-title" className="text-base font-semibold">
          Policy route preview
        </h2>
        <p className="text-sm text-muted-foreground">
          This authoring preview does not create approval tasks.
        </p>
      </div>
      <div className="mt-4">
        <ApprovalStageMap stages={values.routeTemplate.stages} slaRules={values.slaRules} />
      </div>
    </section>
  );
}
