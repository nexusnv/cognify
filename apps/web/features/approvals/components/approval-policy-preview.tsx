"use client";

import { AlertTriangle } from "lucide-react";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@cognify/ui";
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
  const previewContext = context ?? previewContextForValues(values);
  const previewQuery = useApprovalPreview(values, previewContext, preview === undefined && !hasEmptyDraft);
  const data = preview ?? previewQuery.data;

  if (previewQuery.isError && preview === undefined && !hasEmptyDraft) {
    return (
      <Alert variant="destructive">
        <AlertTitle>{title}</AlertTitle>
        <AlertDescription>Unable to load approval route preview.</AlertDescription>
      </Alert>
    );
  }

  if (!data && hasEmptyDraft) {
    return (
      <Card className="py-0" aria-labelledby="approval-policy-preview-title">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>
            <h2 id="approval-policy-preview-title" className="text-base font-semibold">
              {title}
            </h2>
          </CardTitle>
          <CardDescription>{description}</CardDescription>
        </CardHeader>
        <CardContent className="py-4">
          <p className="text-sm text-muted-foreground">No approval stages configured.</p>
        </CardContent>
      </Card>
    );
  }

  if (!data) {
    return (
      <Card className="py-0" aria-labelledby="approval-policy-preview-title">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>
            <h2 id="approval-policy-preview-title" className="text-base font-semibold">
              {title}
            </h2>
          </CardTitle>
          <CardDescription>{description}</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  return (
    <Card className="py-0" aria-labelledby="approval-policy-preview-title">
      <CardHeader className="border-b bg-muted/30">
        <CardTitle>
          <h2 id="approval-policy-preview-title" className="text-base font-semibold">
            {title}
          </h2>
        </CardTitle>
        <CardDescription>{description}</CardDescription>
        <p className="text-sm text-muted-foreground">
          Previewing {data.matchedPolicy.name} version {data.matchedVersion.versionNumber}.
        </p>
      </CardHeader>
      <CardContent className="grid gap-4 py-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div className="space-y-3">
          <Card>
            <CardContent className="space-y-2 py-4">
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
            </CardContent>
          </Card>

          {data.warnings.length > 0 ? (
            <Alert>
              <AlertTitle className="flex items-center gap-2">
                <AlertTriangle className="h-4 w-4" aria-hidden="true" />
                Preview warnings
              </AlertTitle>
              <ul className="mt-2 space-y-1 text-sm text-amber-900">
                {data.warnings.map((warning) => (
                  <li key={warning.code}>{warning.message}</li>
                ))}
              </ul>
            </Alert>
          ) : null}

          <Card>
            <CardContent className="py-4">
              <h3 className="text-sm font-semibold">Route stages</h3>
              <p className="mt-1 text-sm text-muted-foreground">
                {data.stages.length} stage{data.stages.length === 1 ? "" : "s"} matched for{" "}
                {data.context.subjectType}.
              </p>
              <p className="mt-1 text-sm text-muted-foreground">
                Fallback approvers are shown inside each stage.
              </p>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardContent className="space-y-3 py-4">
            <div className="flex items-center justify-between gap-3">
              <h3 className="text-sm font-semibold">Matched conditions</h3>
              <span className="text-xs text-muted-foreground">
                {data.createsTasks ? "Creates tasks" : "Computed preview only"}
              </span>
            </div>
            {data.matchedConditions.length > 0 ? (
              <ul className="space-y-2">
                {data.matchedConditions.map((condition, index) => (
                  <li
                    key={`${condition.field}-${condition.operator}-${index}`}
                    className="text-xs text-muted-foreground"
                  >
                    <span className="block font-medium text-foreground">{condition.summary}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-xs text-muted-foreground">No policy rules matched.</p>
            )}
          </CardContent>
        </Card>
      </CardContent>

      <div className="px-6 pb-6">
        <ApprovalStageMap stages={data.stages} />
      </div>
    </Card>
  );
}

function previewContextForValues(values?: ApprovalPolicyFormValues): ApprovalPreviewContext | undefined {
  if (!values) return undefined;

  if (values.subjectType === "rfq_award_recommendation") {
    return {
      tenantId: "tenant-1",
      subjectType: "rfq_award_recommendation",
      awardRecommendationId: "award-preview",
      rfqId: "rfq-preview",
      rfqNumber: "RFQ-PREVIEW",
      recommendedVendorId: "vendor-preview",
      recommendedVendorName: "Preview vendor",
      recommendedQuotationId: "quotation-preview",
      recommendedQuotationVersionId: "quotation-version-preview",
      recommendedAmount: 125000,
      recommendedCurrency: "MYR",
      scorecardId: "scorecard-preview",
      scorecardWeightedTotal: 86.5,
      riskClassification: "high",
      riskSummaryPresent: true,
      exceptionSummaryPresent: false,
    };
  }

  return {
    tenantId: "tenant-1",
    subjectType: "requisition",
    requisitionId: "req-preview",
    requesterId: "user-preview",
    amount: 3400,
    currency: "MYR",
    department: "Operations",
    costCenter: "OPS-220",
    projectId: "project-preview",
    lineItemCategories: ["Packing box bundle"],
    riskClassification: "medium",
    vendorId: "vendor-preview",
  };
}
