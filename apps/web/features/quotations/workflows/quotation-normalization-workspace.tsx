"use client";

import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import { Alert, AlertDescription, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
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
import { getLastJobError } from "../utils/quotation-normalization-ui";

export function QuotationNormalizationWorkspace({
  normalizationId,
}: {
  normalizationId: string;
}) {
  const normalizationQuery = useQuotationNormalization(normalizationId);
  const normalization = normalizationQuery.data;
  const versionQuery = useQuotationVersion(
    normalization?.source.quotationId ?? null,
    normalization?.source.versionNumber ?? null,
  );
  const saveCorrections = useSaveQuotationNormalizationCorrections(normalizationId);
  const saveMappings = useSaveQuotationNormalizationLineMappings(normalizationId);
  const approve = useApproveQuotationNormalization(normalizationId);
  const approveWithWarnings = useApproveQuotationNormalizationWithWarnings(normalizationId);

  if (normalizationQuery.isLoading) {
    return (
      <Card aria-label="Loading quotation normalization workspace">
        <CardContent className="py-4 text-sm text-muted-foreground">Loading quotation normalization workspace</CardContent>
      </Card>
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
      <Alert variant="destructive"><AlertDescription>{message}</AlertDescription></Alert>
    );
  }

  const versionLines = versionQuery.data?.lineItems ?? [];
  const canEdit = normalization.permissions.canEdit;
  const lastJobError = getLastJobError(normalization);

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
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Overview</CardTitle>
            </CardHeader>
            <CardContent>
            <dl className="grid gap-3 text-sm">
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
            </CardContent>
          </Card>
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
      <Card id="overview">
        <CardHeader>
          <CardTitle className="text-base">Source version summary</CardTitle>
        </CardHeader>
        <CardContent>
        <p className="text-sm text-muted-foreground">
          Compare the current quotation version values against the normalized output before approval.
        </p>
        </CardContent>
      </Card>

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
        quotationCurrency={versionQuery.data?.manualEntry.currency ?? null}
        canEdit={canEdit}
        onSave={async (draft) => {
          const firstGroup = normalization.lineGroups[0] ?? null;
          await saveMappings.mutateAsync({
            lineGroups: [
              {
                groupNumber: firstGroup?.groupNumber ?? normalization.lineGroups.length + 1,
                pricingMode: draft.pricingMode,
                description: draft.description.trim(),
                currency: draft.currency.trim() || undefined,
                bundleTotalAmount: draft.bundleTotalAmount.trim() || undefined,
                notes: draft.buyerNote,
                mappings: [
                  {
                    rfqLineItemId: draft.rfqLineItemId,
                    quotationVersionLineItemId: draft.quotationVersionLineItemId,
                    mappingType: draft.mappingType,
                    quantity: draft.quantity.trim() || undefined,
                    unit: draft.unit.trim() || undefined,
                    lineTotal: draft.bundleTotalAmount.trim() || undefined,
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
