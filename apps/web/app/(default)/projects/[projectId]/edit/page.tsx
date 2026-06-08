import { ProjectEditPage } from "@/features/projects/workflows/project-edit-page";

export default async function ProjectEditWorkspacePage({
  params,
}: {
  params: Promise<{ projectId: string }>;
}) {
  const { projectId } = await params;
  return <ProjectEditPage projectId={projectId} />;
}
