"use client";

import Link from "next/link";
import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import { WorkflowStateLayout } from "@/components/ui/workflow-state/record-workflow-layout";
import { useQuotationComparison } from "../hooks/use-quotation-comparison";
import {
  useCreateQuotationComparisonNote,
  useDeleteQuotationComparisonNote,
  useUpdateQuotationComparisonNote,
} from "../hooks/use-quotation-comparison-notes";
import { QuotationCommercialTermsTable } from "../components/quotation-commercial-terms-table";
import { QuotationComparisonNotesPanel } from "../components/quotation-comparison-notes-panel";
import { QuotationComparisonReadinessBanner } from "../components/quotation-comparison-readiness-banner";
import { QuotationComparisonTable } from "../components/quotation-comparison-table";
import { QuotationComparisonVendorSummary } from "../components/quotation-comparison-vendor-summary";

export function QuotationComparisonWorkspace({ rfqId }: { rfqId: string }) {
  const comparisonQuery = useQuotationComparison(rfqId);
  const comparison = comparisonQuery.data;
  const createNote = useCreateQuotationComparisonNote(rfqId);
  const updateNote = useUpdateQuotationComparisonNote(rfqId);
  const deleteNote = useDeleteQuotationComparisonNote(rfqId);

  if (comparisonQuery.isLoading) {
    return (
      <div aria-label="Loading quotation comparison workspace" className="rounded-md border p-4 text-sm text-muted-foreground">
        Loading quotation comparison workspace
      </div>
    );
  }

  if (comparisonQuery.isError || !comparison) {
    const code = getApiErrorCode(comparisonQuery.error);
    const message = code === "forbidden"
      ? "You do not have access to this quotation comparison."
      : code === "not_found"
        ? "This quotation comparison could not be found."
        : getApiErrorMessage(comparisonQuery.error);

    return (
      <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        {message}
      </div>
    );
  }

  const isNotePending = createNote.isPending || updateNote.isPending || deleteNote.isPending;

  return (
    <WorkflowStateLayout
      backHref="/quotations/normalizations"
      backLabel="Back to quotations"
      eyebrow={comparison.rfq.number ?? comparison.rfq.id}
      title={comparison.rfq.title ?? "Quotation comparison"}
      status={<span className="rounded-full border px-2 py-1 text-xs font-medium">Comparison workspace</span>}
      metadata={[
        { id: "responses", label: "Responses", value: String(comparison.readiness.responseCount) },
        { id: "approved", label: "Approved normalization", value: String(comparison.readiness.approvedNormalizationCount) },
        { id: "rfq-status", label: "RFQ status", value: comparison.rfq.status ?? "Unknown" },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "vendors", label: "Vendors" },
        { id: "line-comparison", label: "Line comparison" },
        { id: "commercial-terms", label: "Commercial terms" },
      ]}
      sidebar={
        <>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Risk scoring not configured</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              This workspace surfaces risk context from normalized evidence only. It does not rank vendors or create award recommendations.
            </p>
          </section>
          <QuotationComparisonNotesPanel
            notes={comparison.notes}
            noteGroups={comparison.noteGroups}
            canManage={comparison.permissions.canManageQuotationComparisonNotes}
            isPending={isNotePending}
            onCreate={async (payload) => {
              await createNote.mutateAsync(payload);
            }}
            onUpdate={async (noteId, payload) => {
              await updateNote.mutateAsync({ noteId, payload });
            }}
            onDelete={async (noteId) => {
              await deleteNote.mutateAsync(noteId);
            }}
          />
        </>
      }
    >
      <div className="flex flex-wrap gap-2">
        <Link
          className="inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent"
          href={`/quotations/scoring/${comparison.rfq.id}`}
        >
          Open scoring
        </Link>
        {comparison.permissions.canManageQuotationComparisonNotes ? (
          <Link
            className="inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent"
            href={`/quotations/awards/${comparison.rfq.id}`}
          >
            Award recommendation
          </Link>
        ) : null}
      </div>
      <QuotationComparisonReadinessBanner readiness={comparison.readiness} />
      <QuotationComparisonVendorSummary vendors={comparison.vendors} />
      <QuotationComparisonTable rows={comparison.lineRows} vendors={comparison.vendors} />
      <QuotationCommercialTermsTable terms={comparison.commercialTerms} vendors={comparison.vendors} />
    </WorkflowStateLayout>
  );
}
