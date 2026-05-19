"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@cognify/ui";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { SourcingIntakeDecisionDialog } from "../components/sourcing-intake-decision-dialog";
import { SourcingIntakeReviewForm } from "../components/sourcing-intake-review-form";
import { SourcingIntakeStatusBadge } from "../components/sourcing-intake-status-badge";
import { useCreateRfqDraftFromIntake } from "../hooks/use-rfq-draft-actions";
import { useClaimSourcingIntakeReview } from "../hooks/use-sourcing-intake-actions";
import { useSourcingIntakeReview } from "../hooks/use-sourcing-intake-review";

export function SourcingIntakeDetailPage({ reviewId }: { reviewId: string }) {
  const router = useRouter();
  const reviewQuery = useSourcingIntakeReview(reviewId);
  const claimMutation = useClaimSourcingIntakeReview(reviewId);
  const createRfqMutation = useCreateRfqDraftFromIntake(reviewId);
  const [createRfqError, setCreateRfqError] = useState<string | null>(null);
  const review = reviewQuery.data;

  async function handleCreateRfq() {
    setCreateRfqError(null);

    try {
      const rfq = await createRfqMutation.mutateAsync();
      router.push(`/sourcing/rfqs/${rfq.id}`);
    } catch {
      setCreateRfqError("RFQ draft could not be created. Refresh and try again.");
    }
  }

  if (reviewQuery.isLoading) {
    return <div aria-label="Loading sourcing intake" className="rounded-md border p-4 text-sm text-muted-foreground">Loading sourcing intake</div>;
  }

  if (reviewQuery.isError || !review) {
    return <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">Unable to load sourcing intake review.</div>;
  }

  return (
    <RecordWorkspaceLayout
      backHref="/sourcing/intake"
      backLabel="Back to sourcing intake"
      eyebrow={review.requisition.number}
      title={review.requisition.title}
      status={<SourcingIntakeStatusBadge status={review.status} />}
      metadata={[
        { id: "requester", label: "Requester", value: review.requisition.requester?.name ?? "Unknown" },
        { id: "buyer", label: "Buyer", value: review.assignedBuyer?.name ?? "Unassigned" },
        { id: "needed", label: "Needed by", value: review.requisition.neededByDate ?? "Not set" },
      ]}
      sections={[
        { id: "summary", label: "Summary" },
        { id: "handoff", label: "Handoff" },
      ]}
      primaryActions={
        <div className="flex flex-col gap-2">
          {review.permissions.canClaim ? (
            <Button onClick={() => claimMutation.mutate()} disabled={claimMutation.isPending}>
              {claimMutation.isPending ? "Claiming" : "Claim"}
            </Button>
          ) : null}
          {review.permissions.canRecordDecision ? <SourcingIntakeDecisionDialog review={review} /> : null}
          {review.permissions.canCreateRfq ? (
            <Button onClick={handleCreateRfq} disabled={createRfqMutation.isPending}>
              {createRfqMutation.isPending ? "Creating RFQ" : "Create RFQ"}
            </Button>
          ) : null}
          {createRfqError ? (
            <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
              {createRfqError}
            </div>
          ) : null}
        </div>
      }
      sidebar={<SourcingIntakeReviewForm review={review} />}
    >
      <section id="summary" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Requisition summary</h2>
        <dl className="mt-3 grid gap-3 sm:grid-cols-2">
          <div>
            <dt className="text-sm text-muted-foreground">Department</dt>
            <dd>{review.requisition.department ?? "Not set"}</dd>
          </div>
          <div>
            <dt className="text-sm text-muted-foreground">Estimated total</dt>
            <dd>{formatMoney(review.requisition.estimatedTotal, review.requisition.currency ?? "MYR")}</dd>
          </div>
          <div>
            <dt className="text-sm text-muted-foreground">Project</dt>
            <dd>{review.project ? <Link className="underline" href={`/projects/${review.project.id}`}>{review.project.name}</Link> : "No project"}</dd>
          </div>
          <div>
            <dt className="text-sm text-muted-foreground">Sourcing path</dt>
            <dd>{review.sourcingPath ? review.sourcingPath.replaceAll("_", " ") : "Not decided"}</dd>
          </div>
        </dl>
      </section>

      <section id="handoff" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Sourcing handoff</h2>
        {review.status === "ready_for_rfq" ? (
          <p className="mt-2 text-sm text-muted-foreground">This review is ready for RFQ drafting. Create or reveal the draft RFQ to shape the sourcing package before vendor invitations.</p>
        ) : review.status === "clarification_requested" ? (
          <p className="mt-2 text-sm text-muted-foreground">Clarification has been requested through the requisition correction flow.</p>
        ) : (
          <p className="mt-2 text-sm text-muted-foreground">Record the buyer intake decision before RFQ or closeout work starts.</p>
        )}
        {review.decisionReason ? <p className="mt-3 text-sm">{review.decisionReason}</p> : null}
      </section>
    </RecordWorkspaceLayout>
  );
}

function formatMoney(amount: number | string | null | undefined, currency: string) {
  const value = amount == null ? 0 : Number(amount);
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(Number.isNaN(value) ? 0 : value);
}
