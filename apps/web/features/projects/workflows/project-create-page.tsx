"use client";

import Link from "next/link";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { Alert, AlertDescription, Button, Card, CardContent } from "@cognify/ui";
import { PageHeader } from "@/components/ui/page-header";
import { ProjectForm } from "../forms/project-form";
import { canManageProjects } from "../utils/project-access";

export function ProjectCreatePage() {
  const currentUserQuery = useCurrentUser();

  if (currentUserQuery.isLoading) {
    return (
      <Card>
        <CardContent className="p-4 text-sm text-muted-foreground">Loading access context</CardContent>
      </Card>
    );
  }

  const currentUser = currentUserQuery.data?.data;

  if (currentUserQuery.isError || !currentUser) {
    return (
      <Alert>
        <AlertDescription>Unable to load access context. Try again.</AlertDescription>
      </Alert>
    );
  }

  const canCreateProject = canManageProjects(currentUser.activeRole);

  return (
    <section className="space-y-5">
      <Button asChild variant="outline" className="w-fit">
        <Link href="/projects">Back to projects</Link>
      </Button>
      <PageHeader
        title="Create project"
        description="Capture ownership, charter, and initial budget context for the workspace."
      />
      {!canCreateProject ? (
        <Card>
          <CardContent className="p-4 text-sm text-muted-foreground">
            Your role does not allow project creation.
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardContent className="p-4">
            <ProjectForm mode="create" />
          </CardContent>
        </Card>
      )}
    </section>
  );
}
