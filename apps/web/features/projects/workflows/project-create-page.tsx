"use client";

import Link from "next/link";
import { ProjectForm } from "../forms/project-form";

export function ProjectCreatePage() {
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
      <div className="rounded-md border p-4">
        <ProjectForm mode="create" />
      </div>
    </section>
  );
}
