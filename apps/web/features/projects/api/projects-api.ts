import {
  activateProject,
  cancelProject,
  completeProject,
  createProject,
  getProject,
  holdProject,
  linkProjectRequisition,
  listProjectActivity,
  listProjectRequisitions,
  listProjects as listProjectsEndpoint,
  resumeProject,
  unlinkProjectRequisition,
  updateProject,
} from "@cognify/api-client/endpoints";
import type {
  ListProjectsParams,
  ProcurementProject as ApiProject,
  ProcurementProjectListResponse as ApiProjectListResponse,
  ProcurementProjectResponse,
  ProjectActivityListResponse,
  ProjectRequisitionListResponse,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";
import type {
  ProcurementProject,
  ProjectFormValues,
  ProjectListResponse,
  ProjectQuery,
} from "../types/project-view-model";

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;
  return { headers: { "X-Tenant-Id": tenantId } };
}

export async function listProjects(query: ProjectQuery = {}) {
  const response = await listProjectsEndpoint(query as ListProjectsParams, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapProjectListResponse(response.data);
}

export async function fetchProject(projectId: string) {
  const response = await getProject(projectId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapProject(response.data.data);
}

export async function createProjectRecord(values: ProjectFormValues) {
  const response = await createProject(values, withActiveTenantHeader());
  if (response.status !== 201) throw response.data;
  return mapProject(response.data.data);
}

export async function updateProjectRecord(projectId: string, values: Partial<ProjectFormValues>) {
  const response = await updateProject(projectId, values, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapProject(response.data.data);
}

export async function transitionProject(
  projectId: string,
  action: "activate" | "hold" | "resume" | "complete" | "cancel",
  reason?: string,
) {
  const payload = reason ? { reason } : undefined;
  const response =
    action === "activate"
      ? await activateProject(projectId, payload, withActiveTenantHeader())
      : action === "hold"
        ? await holdProject(projectId, payload, withActiveTenantHeader())
        : action === "resume"
          ? await resumeProject(projectId, payload, withActiveTenantHeader())
          : action === "complete"
            ? await completeProject(projectId, payload, withActiveTenantHeader())
            : await cancelProject(projectId, payload, withActiveTenantHeader());

  if (response.status !== 200) throw response.data;
  return mapProject(response.data.data);
}

export async function fetchProjectRequisitions(projectId: string) {
  const response = await listProjectRequisitions(projectId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data as ProjectRequisitionListResponse;
}

export async function addProjectRequisitionLink(projectId: string, requisitionId: string) {
  const response = await linkProjectRequisition(
    projectId,
    { requisitionId },
    withActiveTenantHeader(),
  );
  if (response.status !== 201) throw response.data;
  return response.data;
}

export async function removeProjectRequisitionLink(projectId: string, requisitionId: string) {
  const response = await unlinkProjectRequisition(projectId, requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
}

export async function fetchProjectActivity(projectId: string) {
  const response = await listProjectActivity(projectId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data as ProjectActivityListResponse;
}

export function mapProject(project: ApiProject): ProcurementProject {
  return {
    id: project.id,
    tenantId: project.tenantId,
    number: project.number,
    name: project.name,
    charter: project.charter ?? "",
    status: project.status,
    owner: {
      id: project.owner.id,
      name: project.owner.name,
      email: project.owner.email ?? "",
    },
    budgetAmount: project.budgetAmount ?? null,
    currency: project.currency,
    department: project.department ?? "",
    costCenter: project.costCenter ?? "",
    targetStartDate: project.targetStartDate ?? "",
    targetCompletionDate: project.targetCompletionDate ?? "",
    cancelledAt: project.cancelledAt ?? null,
    cancellationReason: project.cancellationReason ?? null,
    completedAt: project.completedAt ?? null,
    summary: project.summary,
    permissions: project.permissions,
    createdAt: project.createdAt,
    updatedAt: project.updatedAt,
  };
}

export function mapProjectListResponse(response: ApiProjectListResponse): ProjectListResponse {
  return {
    data: response.data.map(mapProject),
    meta: response.meta,
  };
}

export function unwrapProjectResponse(response: ProcurementProjectResponse): ProcurementProject {
  return mapProject(response.data);
}
