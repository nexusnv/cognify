import { http, HttpResponse } from "msw";
import type { ProcurementProject, ProjectRequisition } from "@cognify/api-client/schemas";
import {
  projectActivityFixture,
  projectListResponseFixture,
  projectRequisitionsFixture,
  projectResponseFixture,
} from "./project-fixtures";

let projects: ProcurementProject[] = [...projectListResponseFixture.data];
let requisitions: ProjectRequisition[] = structuredClone(projectRequisitionsFixture.data);

export function resetProjectMockState() {
  projects = [...projectListResponseFixture.data];
  requisitions = structuredClone(projectRequisitionsFixture.data);
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

  http.post("/api/projects/:projectId/:action", ({ params, request }) => {
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
      return request.json().then((body) => {
        const reason = (body as { reason?: string }).reason ?? null;
        const cancelled = { ...updated, cancellationReason: reason, cancelledAt: new Date().toISOString() };
        projects = projects.map((item) => (item.id === cancelled.id ? cancelled : item));
        return HttpResponse.json({ data: cancelled });
      });
    }

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
    const existing = requisitions.find((item) => item.id === payload.requisitionId);
    if (!existing) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    const linked = { ...existing, projectId: String(params.projectId) };
    requisitions = requisitions.map((item) => (item.id === linked.id ? linked : item));
    return HttpResponse.json({ data: linked }, { status: 201 });
  }),

  http.delete("/api/projects/:projectId/requisitions/:requisitionId", ({ params }) => {
    const existing = requisitions.find((item) => item.id === params.requisitionId);
    if (!existing) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    const unlinked = { ...existing, projectId: null };
    requisitions = requisitions.map((item) => (item.id === unlinked.id ? unlinked : item));
    return HttpResponse.json({ data: unlinked });
  }),

  http.get("/api/projects/:projectId/activity", () => {
    return HttpResponse.json(projectActivityFixture);
  }),
];
