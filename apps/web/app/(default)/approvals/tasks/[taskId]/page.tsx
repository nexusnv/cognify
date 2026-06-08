import { ApprovalTaskDetailPage } from "@/features/approvals/workflows/approval-task-detail-page";

export default async function Page({ params }: { params: Promise<{ taskId: string }> }) {
  const { taskId } = await params;

  return <ApprovalTaskDetailPage taskId={taskId} />;
}
