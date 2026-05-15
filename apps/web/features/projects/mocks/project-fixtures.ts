import type {
  ProcurementProjectListResponse,
  ProcurementProjectResponse,
  ProjectActivityListResponse,
  ProjectRequisitionListResponse,
} from "@cognify/api-client/schemas";

export const projectResponseFixture: ProcurementProjectResponse = {
  data: {
    id: "501",
    tenantId: "1",
    number: "PRJ-2026-000501",
    name: "Office refresh",
    charter: "Refresh the Kuala Lumpur office.",
    status: "active",
    owner: { id: "12", name: "Priya Buyer", email: "priya@example.test" },
    budgetAmount: 25000,
    currency: "MYR",
    department: "Operations",
    costCenter: "OPS-100",
    targetStartDate: "2026-06-01",
    targetCompletionDate: "2026-09-30",
    cancelledAt: null,
    cancellationReason: null,
    completedAt: null,
    summary: {
      estimatedRequisitionTotal: 12000,
      linkedRequisitionCount: 2,
      draftRequisitionCount: 1,
      submittedRequisitionCount: 1,
      changesRequestedRequisitionCount: 0,
      stoppedRequisitionCount: 0,
      approvalPlaceholderCount: 0,
      awardPlaceholderCount: 0,
    },
    permissions: {
      canUpdate: true,
      canActivate: false,
      canHold: true,
      canResume: false,
      canComplete: true,
      canCancel: true,
      canLinkRequisitions: true,
      canUnlinkRequisitions: true,
      canViewActivity: true,
    },
    createdAt: "2026-05-15T00:00:00.000000Z",
    updatedAt: "2026-05-15T01:00:00.000000Z",
  },
};

export const projectListResponseFixture: ProcurementProjectListResponse = {
  data: [projectResponseFixture.data],
  meta: { currentPage: 1, perPage: 15, total: 1, lastPage: 1 },
};

export const projectRequisitionsFixture: ProjectRequisitionListResponse = {
  data: [
    {
      id: "req-1",
      number: "REQ-2026-000001",
      title: "Workstation replacement",
      status: "draft",
      projectId: "501",
      requester: {
        id: "21",
        name: "Nadia Requester",
        email: "nadia@example.test",
      },
      estimatedTotal: 8000,
      updatedAt: "2026-05-15T02:00:00.000000Z",
    },
    {
      id: "req-2",
      number: "REQ-2026-000002",
      title: "Monitor procurement",
      status: "submitted",
      projectId: "501",
      requester: {
        id: "22",
        name: "Arif Requester",
        email: "arif@example.test",
      },
      estimatedTotal: 4000,
      updatedAt: "2026-05-15T03:00:00.000000Z",
    },
  ],
};

export const projectActivityFixture: ProjectActivityListResponse = {
  data: [
    {
      id: "evt-1",
      type: "project.created",
      actor: {
        id: "12",
        name: "Priya Buyer",
        email: "priya@example.test",
      },
      metadata: { status: "draft" },
      occurredAt: "2026-05-15T00:00:00.000000Z",
    },
  ],
};
