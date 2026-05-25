import { Badge } from "@cognify/ui";
import type { RfqAwardRecommendation } from "@cognify/api-client/schemas";

type Props = {
  context: RfqAwardRecommendation;
  submitBlockReason: string | null;
  draftSelection: {
    recommendedVendorId: string | null;
    recommendedQuotationVersionId: string | null;
  };
};

export function RfqAwardDecisionSummary({ context, submitBlockReason, draftSelection }: Props) {
  const recommendation = context.recommendation;
  const selected = context.vendorOptions.find((option) => option.vendorId === draftSelection.recommendedVendorId);
  const pending = recommendation?.status === "pending_approval";

  return (
    <section className="rounded-md border p-4" aria-label="Decision summary">
      <h2 className="text-base font-semibold">Summary</h2>
      <div className="mt-3 space-y-2 text-sm">
        <p>Vendor: {selected?.vendorName ?? "Not selected"}</p>
        <p>Quotation version: {draftSelection.recommendedQuotationVersionId ?? "Not selected"}</p>
        <p>Status: <Badge variant={pending ? "secondary" : "outline"}>{recommendation?.status ?? "draft"}</Badge></p>
        {context.readiness.blockingMessages.map((message) => (
          <p key={message}>{message}</p>
        ))}
        {submitBlockReason ? <p>{submitBlockReason}</p> : null}
        {pending ? <p>Recommendation is pending approval and read-only.</p> : null}
        {recommendation?.withdrawalReason ? <p>Withdrawal reason: {recommendation.withdrawalReason}</p> : null}
      </div>
    </section>
  );
}
