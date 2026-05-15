"use client";

import { useEffect } from "react";
import { toast } from "sonner";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { rememberRecentRecord } from "@/features/search/hooks/use-recent-records";
import { ProjectActionDialog } from "../components/project-action-dialog";
import { ProjectActivityTimeline } from "../components/project-activity-timeline";
import { ProjectBudgetSummary } from "../components/project-budget-summary";
import { ProjectRequisitionPipeline } from "../components/project-requisition-pipeline";
import { ProjectStatusBadge } from "../components/project-status-badge";
import { useProject } from "../hooks/use-project";
import { useProjectStatusAction } from "../hooks/use-project-actions";
import { useProjectActivity, useProjectRequisitions } from "../hooks/use-project-requisitions";

export function ProjectDetailPage({ projectId }: { projectId: string }) {
  const projectQuery = useProject(projectId);
  const requisitionsQuery = useProjectRequisitions(projectId);
  const activityQuery = useProjectActivity(projectId);
  const transitionMutation = useProjectStatusAction(projectId);

  const project = projectQuery.data;

  useEffect(() => {
    if (!project) return;

    rememberRecentRecord({
      type: "project",
      id: project.id,
      title: project.name,
      subtitle: project.number,
      status: project.status,
      href: `/projects/${project.id}`,
      updatedAt: project.updatedAt,
    });
  }, [project]);

  if (projectQuery.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading project workspace</div>;
  }

  if (projectQuery.isError || !project) {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        Project could not be loaded.
      </div>
    );
  }

  const actions = (
    <>
      {project.permissions.canActivate ? (
        <ProjectActionDialog
          action="activate"
          title="Activate project?"
          description="Move this project from draft to active sourcing."
          confirmLabel="Activate project"
          triggerLabel="Activate"
          isPending={transitionMutation.isPending}
          onSubmit={async () => {
            await transitionMutation.mutateAsync({ action: "activate" });
            toast.success("Project activated");
          }}
        />
      ) : null}
      {project.permissions.canHold ? (
        <ProjectActionDialog
          action="hold"
          title="Put project on hold?"
          description="Pause project progress while preserving current links and context."
          confirmLabel="Put on hold"
          triggerLabel="Hold"
          isPending={transitionMutation.isPending}
          onSubmit={async () => {
            await transitionMutation.mutateAsync({ action: "hold" });
            toast.success("Project put on hold");
          }}
        />
      ) : null}
      {project.permissions.canResume ? (
        <ProjectActionDialog
          action="resume"
          title="Resume project?"
          description="Return this project to active execution."
          confirmLabel="Resume project"
          triggerLabel="Resume"
          isPending={transitionMutation.isPending}
          onSubmit={async () => {
            await transitionMutation.mutateAsync({ action: "resume" });
            toast.success("Project resumed");
          }}
        />
      ) : null}
      {project.permissions.canComplete ? (
        <ProjectActionDialog
          action="complete"
          title="Complete project?"
          description="Mark this project as completed."
          confirmLabel="Complete project"
          triggerLabel="Complete"
          isPending={transitionMutation.isPending}
          onSubmit={async () => {
            await transitionMutation.mutateAsync({ action: "complete" });
            toast.success("Project completed");
          }}
        />
      ) : null}
      {project.permissions.canCancel ? (
        <ProjectActionDialog
          action="cancel"
          title="Cancel project?"
          description="Cancellation requires a reason and records the action for audit history."
          confirmLabel="Cancel project"
          triggerLabel="Cancel"
          triggerVariant="destructive"
          isPending={transitionMutation.isPending}
          onSubmit={async ({ reason }) => {
            await transitionMutation.mutateAsync({ action: "cancel", reason });
            toast.success("Project cancelled");
          }}
        />
      ) : null}
    </>
  );

  return (
    <RecordWorkspaceLayout
      backHref="/projects"
      backLabel="Back to projects"
      eyebrow={project.number}
      title={project.name}
      status={<ProjectStatusBadge status={project.status} />}
      metadata={[
        { id: "owner", label: "Owner", value: project.owner.name },
        {
          id: "budget",
          label: "Budget",
          value: formatMoney(project.budgetAmount ?? 0, project.currency),
        },
        {
          id: "target",
          label: "Target completion",
          value: project.targetCompletionDate || "No target",
        },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "budget", label: "Budget" },
        { id: "pipeline", label: "Pipeline" },
        { id: "activity", label: "Activity" },
      ]}
      primaryActions={actions}
      sidebar={
        <>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Approvals placeholder</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              Approval routing is not active for projects yet.
            </p>
          </section>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Risk placeholder</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              Project risks are reserved for a later governance slice.
            </p>
          </section>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Award placeholder</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              Award records will appear here after award workflows are implemented.
            </p>
          </section>
        </>
      }
    >
      <section id="overview" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Overview</h2>
        <p className="mt-2 text-sm leading-6">
          {project.charter || "No charter has been captured for this project yet."}
        </p>
      </section>

      <ProjectBudgetSummary
        budgetAmount={project.budgetAmount}
        currency={project.currency}
        summary={project.summary}
      />

      <ProjectRequisitionPipeline requisitions={requisitionsQuery.data?.data ?? []} />

      <section id="activity" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Activity</h2>
        <div className="mt-3">
          <ProjectActivityTimeline events={activityQuery.data?.data ?? []} />
        </div>
      </section>
    </RecordWorkspaceLayout>
  );
}

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(amount);
}
