"use client";

import Link from "next/link";
import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import { Alert, AlertDescription, Badge, Button, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
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
      <Card aria-label="Loading quotation comparison workspace">
        <CardContent className="py-4 text-sm text-muted-foreground">Loading quotation comparison workspace</CardContent>
      </Card>
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
      <Alert variant="destructive"><AlertDescription>{message}</AlertDescription></Alert>
    );
  }

  const isNotePending = createNote.isPending || updateNote.isPending || deleteNote.isPending;

  return (
    <RecordWorkspaceLayout
      backHref="/quotations/normalizations"
      backLabel="Back to quotations"
      eyebrow={comparison.rfq.number ?? comparison.rfq.id}
      title={comparison.rfq.title ?? "Quotation comparison"}
      status={<Badge variant="outline">Comparison workspace</Badge>}
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
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Risk scoring not configured</CardTitle>
            </CardHeader>
            <CardContent>
            <p className="text-sm text-muted-foreground">
              This workspace surfaces risk context from normalized evidence only. It does not rank vendors or create award recommendations.
            </p>
            </CardContent>
          </Card>
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
        <Button asChild variant="outline">
          <Link href={`/quotations/scoring/${comparison.rfq.id}`}>Open scoring</Link>
        </Button>
        {comparison.permissions.canManageQuotationComparisonNotes ? (
          <Button asChild variant="outline">
            <Link href={`/quotations/awards/${comparison.rfq.id}`}>Award recommendation</Link>
          </Button>
        ) : null}
      </div>
      <QuotationComparisonReadinessBanner readiness={comparison.readiness} />
      <QuotationComparisonVendorSummary vendors={comparison.vendors} />
      <QuotationComparisonTable rows={comparison.lineRows} vendors={comparison.vendors} />
      <QuotationCommercialTermsTable terms={comparison.commercialTerms} vendors={comparison.vendors} />
    </RecordWorkspaceLayout>
  );
}
