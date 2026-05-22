"use client";

import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import type { QuotationLineItem } from "@cognify/api-client/schemas";
import { useQuotationVersion } from "@/features/sourcing/hooks/use-quotation-versions";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import {
  useApproveQuotationNormalization,
  useApproveQuotationNormalizationWithWarnings,
  useSaveQuotationNormalizationCorrections,
  useSaveQuotationNormalizationLineMappings,
} from "../hooks/use-quotation-normalization-actions";
import { useQuotationNormalization } from "../hooks/use-quotation-normalization";
import { QuotationNormalizationApprovalPanel } from "../components/quotation-normalization-approval-panel";
import { QuotationNormalizationAttachmentPanel } from "../components/quotation-normalization-attachment-panel";
import { QuotationNormalizationFieldReview } from "../components/quotation-normalization-field-review";
import { QuotationNormalizationIssueList } from "../components/quotation-normalization-issue-list";
import { QuotationNormalizationLineMappingPanel } from "../components/quotation-normalization-line-mapping-panel";
import { QuotationNormalizationStatusBadge } from "../components/quotation-normalization-status-badge";
import { getLastJobError, isApprovedNormalization } from "../utils/quotation-normalization-ui";

export function QuotationNormalizationWorkspace({
  normalizationId,
}: {
  normalizationId: string;
}) {
  const normalizationQuery = useQuotationNormalization(normalizationId);
  const normalization = normalizationQuery.data;
  const versionQuery = useQuotationVersion(
    normalization?.source.quotationId ?? null,
    normalization?.source.quotationVersionId ? Number(normalization.source.quotationVersionId) : null,
  );
  const saveCorrections = useSaveQuotationNormalizationCorrections(normalizationId);
  const saveMappings = useSaveQuotationNormalizationLineMappings(normalizationId);
  const approve = useApproveQuotationNormalization(normalizationId);
  const approveWithWarnings = useApproveQuotationNormalizationWithWarnings(normalizationId);

  if (normalizationQuery.isLoading) {
    return (
      <div aria-label="Loading quotation normalization workspace" className="rounded-md border p-4 text-sm text-muted-foreground">
        Loading quotation normalization workspace
      </div>
    );
  }

  if (normalizationQuery.isError || !normalization) {
    const code = getApiErrorCode(normalizationQuery.error);
    const message = code === "forbidden"
      ? "You do not have access to this quotation normalization."
      : code === "not_found"
        ? "This quotation normalization could not be found."
        : getApiErrorMessage(normalizationQuery.error);

    return (
      <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        {message}
      </div>
    );
  }

  const versionLines =
    versionQuery.data?.lineItems ??
    ((normalization as { currentVersionLines?: QuotationLineItem[] }).currentVersionLines ?? []);
  const canEdit = normalization.permissions.canEdit;
  const lastJobError = getLastJobError(normalization as { lastJobError?: string | null });

  return (
    <RecordWorkspaceLayout
      backHref="/quotations/normalizations"
      backLabel="Back to quotations"
      eyebrow={normalization.source.rfqNumber ?? normalization.source.rfqId ?? "Quotation normalization"}
      title={normalization.source.quotationNumber ?? normalization.id}
      status={<QuotationNormalizationStatusBadge status={normalization.status} />}
      metadata={[
        {
          id: "vendor",
          label: "Vendor",
          value: normalization.source.vendorName ?? "Unknown vendor",
        },
        {
          id: "version",
          label: "Version",
          value: `Version ${normalization.source.versionNumber ?? "?"}`,
        },
        {
          id: "revision",
          label: "Normalization revision",
          value: String(normalization.normalizationRevision),
        },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "header-fields", label: "Header fields" },
        { id: "line-mappings", label: "Line mappings" },
        { id: "attachments", label: "Attachments" },
        { id: "issues", label: "Issues" },
        { id: "approval", label: "Approval" },
      ]}
      sidebar={
        <>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Overview</h2>
            <dl className="mt-3 grid gap-3 text-sm">
              <div>
                <dt className="text-muted-foreground">RFQ</dt>
                <dd className="font-medium">{normalization.source.rfqNumber ?? normalization.source.rfqId ?? "No RFQ"}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">Algorithm</dt>
                <dd>{normalization.algorithmVersion}</dd>
              </div>
              {lastJobError ? (
                <div>
                  <dt className="text-muted-foreground">Last job error</dt>
                  <dd className="text-red-700">{lastJobError}</dd>
                </div>
              ) : null}
            </dl>
          </section>
          <QuotationNormalizationApprovalPanel
            normalization={normalization}
            canEdit={canEdit}
            onApprove={async (approvalNote) => {
              await approve.mutateAsync({ approvalNote });
            }}
            onApproveWithWarnings={async (approvalNote) => {
              await approveWithWarnings.mutateAsync({ approvalNote });
            }}
          />
        </>
      }
    >
      <section id="overview" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Source version summary</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          Compare the current quotation version values against the normalized output before approval.
        </p>
      </section>

      <section id="header-fields" className="space-y-4">
        {normalization.fields.map((field) => (
          <QuotationNormalizationFieldReview
            key={field.id}
            field={field}
            issues={normalization.issues.filter((issue) => issue.fieldPath === field.fieldPath)}
            canEdit={canEdit}
            onSave={async (payload) => {
              await saveCorrections.mutateAsync({
                corrections: [payload],
              });
            }}
          />
        ))}
      </section>

      <QuotationNormalizationLineMappingPanel
        normalization={normalization}
        versionLines={versionLines}
        canEdit={canEdit}
        onSave={async (draft) => {
          await saveMappings.mutateAsync({
            lineGroups: [
              {
                groupNumber: 1,
                pricingMode: draft.pricingMode,
                description: draft.description,
                currency: "USD",
                bundleTotalAmount: draft.bundleTotalAmount,
                notes: draft.buyerNote,
                mappings: [
                  {
                    rfqLineItemId: draft.rfqLineItemId,
                    quotationVersionLineItemId: draft.quotationVersionLineItemId,
                    mappingType: "bundled",
                    quantity: "10.0000",
                    unit: "each",
                    lineTotal: draft.bundleTotalAmount,
                    buyerNote: draft.buyerNote,
                  },
                ],
              },
            ],
          });
        }}
      />

      <QuotationNormalizationAttachmentPanel attachments={normalization.attachments} />
      <QuotationNormalizationIssueList issues={normalization.issues} />
    </RecordWorkspaceLayout>
  );
}
