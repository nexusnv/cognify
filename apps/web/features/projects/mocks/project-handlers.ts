import { http, HttpResponse } from "msw";
import type { ProcurementProject, ProjectRequisition } from "@cognify/api-client/schemas";
import {
  projectActivityFixture,
  projectListResponseFixture,
  projectRequisitionsFixture,
  projectResponseFixture,
} from "./project-fixtures";
import { requisitionFixtures } from "@/features/requisitions/mocks/requisitions-fixtures";

let projects: ProcurementProject[] = [...projectListResponseFixture.data];
let requisitions: ProjectRequisition[] = structuredClone(projectRequisitionsFixture.data);
let requisitionCatalog: ProjectRequisition[] = structuredClone(
  requisitionFixtures as unknown as ProjectRequisition[],
);

export function resetProjectMockState() {
  projects = [...projectListResponseFixture.data];
  requisitions = structuredClone(projectRequisitionsFixture.data);
  requisitionCatalog = structuredClone(requisitionFixtures as unknown as ProjectRequisition[]);
}

function refreshProjectSummary(projectId: string) {
  const linked = requisitions.filter((item) => item.projectId === projectId);
  const summary = summarizeProjectRequisitions(linked);

  projects = projects.map((item) =>
    item.id === projectId
      ? {
          ...item,
          summary: {
            ...item.summary,
            ...summary,
          },
        }
      : item,
  );
}

function summarizeProjectRequisitions(linked: ProjectRequisition[]) {
  return {
    linkedRequisitionCount: linked.length,
    estimatedRequisitionTotal: linked.reduce((total, item) => total + item.estimatedTotal, 0),
    draftRequisitionCount: linked.filter((item) => item.status === "draft").length,
    submittedRequisitionCount: linked.filter((item) => item.status === "submitted").length,
    changesRequestedRequisitionCount: linked.filter((item) => item.status === "changes_requested").length,
    stoppedRequisitionCount: linked.filter((item) =>
      ["withdrawn", "cancelled", "on_hold", "pending_approval"].includes(item.status),
    ).length,
  };
}

export const projectHandlers = [
  http.get("/api/projects", () => {
    return HttpResponse.json({
      data: projects,
      meta: {
        currentPage: 1,
        perPage: 15,
        total: projects.length,
        lastPage: 1,
      },
    });
  }),

  http.post("/api/projects", async ({ request }) => {
    const payload = (await request.json()) as Record<string, string>;
    const nextId = String(Number(projects[0]?.id ?? "500") + 1);
    const now = new Date().toISOString();

    const created = {
      ...projectResponseFixture.data,
      id: nextId,
      number: `PRJ-2026-${nextId.padStart(6, "0")}`,
      name: payload.name ?? projectResponseFixture.data.name,
      charter: payload.charter ?? "",
      owner: {
        ...projectResponseFixture.data.owner,
        id: payload.ownerId ?? projectResponseFixture.data.owner.id,
      },
      budgetAmount: Number(payload.budgetAmount ?? 0),
      currency: payload.currency ?? "MYR",
      department: payload.department ?? "",
      costCenter: payload.costCenter ?? "",
      targetStartDate: payload.targetStartDate ?? "",
      targetCompletionDate: payload.targetCompletionDate ?? "",
      status: "draft" as const,
      permissions: {
        ...projectResponseFixture.data.permissions,
        canActivate: true,
      },
      summary: {
        ...projectResponseFixture.data.summary,
        linkedRequisitionCount: 0,
        estimatedRequisitionTotal: 0,
      },
      createdAt: now,
      updatedAt: now,
    };

    projects = [created, ...projects];

    return HttpResponse.json({ data: created }, { status: 201 });
  }),

  http.get("/api/projects/:projectId", ({ params }) => {
    const project = projects.find((item) => item.id === params.projectId);
    if (!project) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    return HttpResponse.json({ data: project });
  }),

  http.patch("/api/projects/:projectId", async ({ params, request }) => {
    const payload = (await request.json()) as Record<string, string>;
    const project = projects.find((item) => item.id === params.projectId);
    if (!project) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    const updated = {
      ...project,
      ...payload,
      updatedAt: new Date().toISOString(),
    };

    projects = projects.map((item) => (item.id === updated.id ? updated : item));
    return HttpResponse.json({ data: updated });
  }),

  http.get("/api/projects/:projectId/requisitions", ({ params }) => {
    const project = projects.find((item) => item.id === params.projectId);
    if (!project) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    return HttpResponse.json({ data: requisitions.filter((item) => item.projectId === project.id) });
  }),

  http.post("/api/projects/:projectId/requisitions", async ({ params, request }) => {
    const payload = (await request.json()) as { requisitionId: string };
    const projectId = String(params.projectId);
    const project = projects.find((item) => item.id === projectId);
    if (!project) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    const existing =
      requisitions.find((item) => item.id === payload.requisitionId) ??
      requisitionCatalog.find((item) => item.id === payload.requisitionId);
    if (!existing) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    if (existing.projectId !== null && existing.projectId !== "" && existing.projectId !== projectId) {
      return HttpResponse.json({ message: "Forbidden" }, { status: 403 });
    }

    const linked = { ...existing, projectId };
    requisitions = requisitions.some((item) => item.id === linked.id)
      ? requisitions.map((item) => (item.id === linked.id ? linked : item))
      : [linked, ...requisitions];
    requisitionCatalog = requisitionCatalog.map((item) => (item.id === linked.id ? linked : item));
    refreshProjectSummary(projectId);
    return HttpResponse.json({ data: linked }, { status: 201 });
  }),

  http.delete("/api/projects/:projectId/requisitions/:requisitionId", ({ params }) => {
    const projectId = String(params.projectId);
    const project = projects.find((item) => item.id === projectId);
    if (!project) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    const existing =
      requisitions.find((item) => item.id === params.requisitionId) ??
      requisitionCatalog.find((item) => item.id === params.requisitionId);
    if (!existing) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    if (existing.projectId !== projectId) {
      return HttpResponse.json({ message: "Forbidden" }, { status: 403 });
    }

    const unlinked = { ...existing, projectId: null };
    requisitions = requisitions.map((item) => (item.id === unlinked.id ? unlinked : item));
    requisitionCatalog = requisitionCatalog.map((item) => (item.id === unlinked.id ? unlinked : item));
    refreshProjectSummary(projectId);
    return HttpResponse.json({ data: unlinked });
  }),

  http.post("/api/projects/:projectId/:action", async ({ params, request }) => {
    if (!["activate", "hold", "resume", "complete", "cancel"].includes(String(params.action))) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }

    const project = projects.find((item) => item.id === params.projectId);
    if (!project) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    const nextStatus: ProcurementProject["status"] =
      params.action === "activate"
        ? "active"
        : params.action === "hold"
          ? "on_hold"
          : params.action === "resume"
            ? "active"
            : params.action === "complete"
              ? "completed"
              : "cancelled";

    const updated = {
      ...project,
      status: nextStatus,
      updatedAt: new Date().toISOString(),
    };

    if (params.action === "cancel") {
      const body = (await request.json()) as { reason?: string };
      const reason = body.reason ?? null;
      const cancelled = { ...updated, cancellationReason: reason, cancelledAt: new Date().toISOString() };
      projects = projects.map((item) => (item.id === cancelled.id ? cancelled : item));
      return HttpResponse.json({ data: cancelled });
    }

    projects = projects.map((item) => (item.id === updated.id ? updated : item));
    return HttpResponse.json({ data: updated });
  }),

  http.get("/api/projects/:projectId/activity", () => {
    return HttpResponse.json(projectActivityFixture);
  }),
];
