"use client";

import Link from "next/link";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { ProjectForm } from "../forms/project-form";
import { canManageProjects } from "../utils/project-access";

export function ProjectCreatePage() {
  const currentUserQuery = useCurrentUser();

  if (currentUserQuery.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading access context</div>;
  }

  const currentUser = currentUserQuery.data?.data;

  if (currentUserQuery.isError || !currentUser) {
    return (
      <div className="rounded-md border p-4 text-sm text-muted-foreground">
        Unable to load access context. Try again.
      </div>
    );
  }

  const canCreateProject = canManageProjects(currentUser.activeRole);

  return (
    <section className="space-y-5">
      <Link href="/projects" className="inline-flex min-h-11 items-center rounded-md border px-3 text-sm">
        Back to projects
      </Link>
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold">Create project</h1>
        <p className="text-sm text-muted-foreground">
          Capture ownership, charter, and initial budget context for the workspace.
        </p>
      </div>
      {!canCreateProject ? (
        <div className="rounded-md border p-4 text-sm text-muted-foreground">
          Your role does not allow project creation.
        </div>
      ) : (
        <div className="rounded-md border p-4">
          <ProjectForm mode="create" />
        </div>
      )}
    </section>
  );
}
