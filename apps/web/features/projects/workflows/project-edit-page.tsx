"use client";

import Link from "next/link";
import { Alert, AlertDescription, Button, Card, CardContent } from "@cognify/ui";
import { PageHeader } from "@/components/ui/page-header";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { useProject } from "../hooks/use-project";
import { ProjectForm } from "../forms/project-form";

export function ProjectEditPage({ projectId }: { projectId: string }) {
  const currentUserQuery = useCurrentUser();
  const projectQuery = useProject(projectId);
  const project = projectQuery.data;

  if (currentUserQuery.isLoading || projectQuery.isLoading) {
    return (
      <Card>
        <CardContent className="p-4 text-sm text-muted-foreground">Loading project</CardContent>
      </Card>
    );
  }

  if (currentUserQuery.isError || projectQuery.isError || !project) {
    return (
      <Alert variant="destructive">
        <AlertDescription>Project could not be loaded.</AlertDescription>
      </Alert>
    );
  }

  if (!project.permissions.canUpdate) {
    return (
      <section className="space-y-5">
        <Button asChild variant="outline" className="w-fit">
          <Link href={`/projects/${project.id}`}>Back to project</Link>
        </Button>
        <PageHeader title="Edit project" description="Update ownership, charter, budget, and target dates." />
        <Card>
          <CardContent className="p-4 text-sm text-muted-foreground">
            You do not have permission to edit this project.
          </CardContent>
        </Card>
      </section>
    );
  }

  return (
    <section className="space-y-5">
      <Button asChild variant="outline" className="w-fit">
        <Link href={`/projects/${project.id}`}>Back to project</Link>
      </Button>
      <PageHeader title="Edit project" description="Update ownership, charter, budget, and target dates." />
      <Card>
        <CardContent className="p-4">
          <ProjectForm mode="edit" project={project} />
        </CardContent>
      </Card>
    </section>
  );
}
