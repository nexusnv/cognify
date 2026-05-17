import { ProjectDetailPage } from "@/features/projects/workflows/project-detail-page";

export default async function ProjectWorkspacePage({
  params,
}: {
  params: Promise<{ projectId: string }>;
}) {
  const { projectId } = await params;
  return <ProjectDetailPage projectId={projectId} />;
}
