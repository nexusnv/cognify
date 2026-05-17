import { ApprovalPolicyDetailPage } from "@/features/approvals/workflows/approval-policy-detail-page";

export default async function Page({ params }: { params: Promise<{ policyId: string }> }) {
  const { policyId } = await params;

  return <ApprovalPolicyDetailPage policyId={policyId} />;
}
