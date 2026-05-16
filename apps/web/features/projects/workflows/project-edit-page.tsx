"use client";

import Link from "next/link";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { useProject } from "../hooks/use-project";
import { ProjectForm } from "../forms/project-form";

export function ProjectEditPage({ projectId }: { projectId: string }) {
  const currentUserQuery = useCurrentUser();
  const projectQuery = useProject(projectId);
  const project = projectQuery.data;

  if (currentUserQuery.isLoading || projectQuery.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading project</div>;
  }

  if (currentUserQuery.isError || projectQuery.isError || !project) {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        Project could not be loaded.
      </div>
    );
  }

  if (!project.permissions.canUpdate) {
    return (
      <section className="space-y-5">
        <Link href={`/projects/${project.id}`} className="inline-flex min-h-11 items-center rounded-md border px-3 text-sm">
          Back to project
        </Link>
        <div className="rounded-md border p-4 text-sm text-muted-foreground">
          You do not have permission to edit this project.
        </div>
      </section>
    );
  }

  return (
    <section className="space-y-5">
      <Link href={`/projects/${project.id}`} className="inline-flex min-h-11 items-center rounded-md border px-3 text-sm">
        Back to project
      </Link>
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold">Edit project</h1>
        <p className="text-sm text-muted-foreground">
          Update ownership, charter, budget, and target dates.
        </p>
      </div>
      <div className="rounded-md border p-4">
        <ProjectForm mode="edit" project={project} />
      </div>
    </section>
  );
}
