import { http, HttpResponse } from "msw";
import {
  approvalTaskCommentFixtures,
  approvalSummaryFixture,
  approvalDelegationFixtures,
  approvalSlaSummaryFixture,
  approvalTaskFixtures,
  approvalPolicyFixture,
  approvalPreviewFixture,
  awardApprovalPolicyFixture,
  awardApprovalPreviewFixture,
  fallbackApprovalPreviewFixture,
  parallelAllApprovalPreviewFixture,
  parallelAnyApprovalPreviewFixture,
  submittedRequisitionApprovalPreviewFixture,
} from "./approval-fixtures";
import { requisitionFixtures } from "@/features/requisitions/mocks/requisitions-fixtures";
import type { CollaborationComment } from "@cognify/api-client/schemas";
import type { ApprovalDelegation, ApprovalPolicy } from "../types/approval-view-model";

let policies: ApprovalPolicy[] = [
  structuredClone(approvalPolicyFixture),
  structuredClone(awardApprovalPolicyFixture),
];
let tasks = structuredClone(approvalTaskFixtures);
let delegations = structuredClone(approvalDelegationFixtures);
let comments = structuredClone(approvalTaskCommentFixtures);

type ApprovalPolicyPayload = Partial<ApprovalPolicy> &
  Pick<
    Partial<ApprovalPolicy["versions"][number]>,
    "priority" | "rules" | "routeTemplate" | "slaRules"
  >;

export function resetApprovalMockState() {
  policies = [structuredClone(approvalPolicyFixture), structuredClone(awardApprovalPolicyFixture)];
  tasks = structuredClone(approvalTaskFixtures);
  delegations = structuredClone(approvalDelegationFixtures);
  comments = structuredClone(approvalTaskCommentFixtures);
}

export const approvalHandlers = [
  http.get("/api/approval-tasks", ({ request }) => {
    const url = new URL(request.url);
    const scope = url.searchParams.get("scope");
    const subjectType = url.searchParams.get("subjectType");
    const status = url.searchParams.get("status");
    const data = tasks.filter((task) => {
      const matchesStatus = !status || task.status === status;
      const matchesSubject = !subjectType || task.subject.type === subjectType;
      const matchesScope =
        !scope ||
        scope === "all" ||
        scope === "assigned_to_me" ||
        (scope === "completed_by_me" && task.decidedBy?.id === "user-2") ||
        (scope === "overdue" && task.dueAt !== null && task.dueAt < "2026-05-18") ||
        (scope === "due_soon" && task.status === "active");

      return matchesStatus && matchesSubject && matchesScope;
    });

    return HttpResponse.json({
      data,
      meta: { currentPage: 1, perPage: 20, total: data.length, lastPage: 1 },
    });
  }),
  http.get("/api/approval-tasks/:taskId", ({ params }) => {
    const task = tasks.find((item) => item.id === params.taskId);
    if (!task) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    return HttpResponse.json({ data: task });
  }),
  http.post("/api/approval-tasks/:taskId/view", ({ params }) => {
    const task = tasks.find((item) => item.id === params.taskId);
    if (!task) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    task.viewedAt = "2026-05-18T01:00:00.000Z";
    return HttpResponse.json({ data: task });
  }),
  http.get("/api/approval-tasks/:taskId/comments", ({ params }) => {
    const taskId = String(params.taskId);
    const task = tasks.find((item) => item.id === taskId);
    if (!task) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    return HttpResponse.json({ data: comments[taskId] ?? [] });
  }),
  http.post("/api/approval-tasks/:taskId/comments", async ({ params, request }) => {
    const taskId = String(params.taskId);
    const task = tasks.find((item) => item.id === taskId);
    if (!task) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    const body = (await request.json()) as { body?: string };
    if (!body.body?.trim()) {
      return HttpResponse.json({ error: { code: "validation_failed" } }, { status: 422 });
    }

    const comment: CollaborationComment = {
      id: `approval-comment-${(comments[taskId] ?? []).length + 1}`,
      subjectType: "approval_task",
      subjectId: taskId,
      author: { id: "user-2", name: "Priya Buyer", email: "priya.buyer@acme.test" },
      body: body.body,
      mentions: [],
      createdAt: "2026-05-18T03:00:00.000Z",
      updatedAt: "2026-05-18T03:00:00.000Z",
    };

    comments[taskId] = [...(comments[taskId] ?? []), comment];

    return HttpResponse.json({ data: comment }, { status: 201 });
  }),
  http.post("/api/approval-tasks/:taskId/approve", async ({ params, request }) => {
    const task = tasks.find((item) => item.id === params.taskId);
    if (!task) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    const body = (await request.json()) as { lockVersion: number };
    if (body.lockVersion !== task.lockVersion) {
      return HttpResponse.json({ error: { code: "conflict" } }, { status: 409 });
    }
    task.status = "approved";
    task.decision = "approved";
    task.decidedBy = task.assignee;
    task.decidedAt = "2026-05-18T01:00:00.000Z";
    task.lockVersion += 1;
    task.permissions = { canView: true, canApprove: false, canReject: false, canRequestChanges: false };
    return HttpResponse.json({ data: task });
  }),
  http.post("/api/approval-tasks/:taskId/reject", async ({ params, request }) => {
    const task = tasks.find((item) => item.id === params.taskId);
    if (!task) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    const body = (await request.json()) as { lockVersion: number; reason?: string };
    if (!body.reason) return HttpResponse.json({ error: { code: "validation_failed" } }, { status: 422 });
    if (body.lockVersion !== task.lockVersion) {
      return HttpResponse.json({ error: { code: "conflict" } }, { status: 409 });
    }
    task.status = "rejected";
    task.decision = "rejected";
    task.decisionReason = body.reason;
    task.decidedBy = task.assignee;
    task.lockVersion += 1;
    return HttpResponse.json({ data: task });
  }),
  http.post("/api/approval-tasks/:taskId/request-changes", async ({ params, request }) => {
    const task = tasks.find((item) => item.id === params.taskId);
    if (!task) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    const body = (await request.json()) as { lockVersion: number; reason?: string; requestedFields?: string[] };
    if (!body.reason) return HttpResponse.json({ error: { code: "validation_failed" } }, { status: 422 });
    if (body.lockVersion !== task.lockVersion) {
      return HttpResponse.json({ error: { code: "conflict" } }, { status: 409 });
    }
    task.status = "changes_requested";
    task.decision = "changes_requested";
    task.decisionReason = body.reason;
    task.requestedFields = body.requestedFields ?? [];
    task.lockVersion += 1;
    return HttpResponse.json({ data: task });
  }),
  http.get("/api/approval-delegations", () => {
    return HttpResponse.json({ data: delegations });
  }),
  http.get("/api/approval-delegations/delegate-candidates", () => {
    return HttpResponse.json({
      data: [
        { id: "2", name: "Priya Buyer", email: "priya.buyer@acme.test" },
        { id: "3", name: "Finance approver", email: "finance.approver@acme.test" },
        { id: "4", name: "Backup buyer", email: "backup.buyer@acme.test" },
      ],
    });
  }),
  http.get("/api/approvals/sla-summary", () => {
    return HttpResponse.json({ data: approvalSlaSummaryFixture });
  }),
  http.post("/api/approval-delegations", async ({ request }) => {
    const body = (await request.json()) as {
      delegateId?: number;
      scope?: string;
      startsAt?: string;
      endsAt?: string;
      reason?: string;
    };

    if (!body.delegateId || !body.startsAt || !body.endsAt || !body.reason) {
      return HttpResponse.json(
        { error: { code: "validation_failed", message: "Delegate, effective dates, and reason are required." } },
        { status: 422 },
      );
    }

    if (body.delegateId === 999) {
      return HttpResponse.json(
        { error: { code: "validation_failed", message: "The selected delegate is outside this tenant." } },
        { status: 422 },
      );
    }

    const delegation: ApprovalDelegation = {
      ...structuredClone(approvalDelegationFixtures[0]!),
      id: `delegation-${delegations.length + 1}`,
      delegateId: String(body.delegateId),
      scope: (body.scope ?? "task_specific") as ApprovalDelegation["scope"],
      startsAt: body.startsAt,
      endsAt: body.endsAt,
      reason: body.reason,
    };

    delegations.push(delegation);
    return HttpResponse.json({ data: delegation }, { status: 201 });
  }),
  http.post("/api/approval-tasks/:taskId/delegate", async ({ params, request }) => {
    const task = tasks.find((item) => item.id === params.taskId);
    if (!task) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    const body = (await request.json()) as { approvalDelegationId?: number; lockVersion?: number };
    if (body.lockVersion !== task.lockVersion) {
      return HttpResponse.json({ error: { code: "conflict" } }, { status: 409 });
    }
    const delegation = delegations.find((item) => item.id === String(body.approvalDelegationId));
    if (!delegation) {
      return HttpResponse.json(
        { error: { code: "validation_failed", message: "The selected delegation is not active." } },
        { status: 422 },
      );
    }

    task.originalAssignee = task.assignee;
    task.assignee = delegation.delegate;
    task.lockVersion += 1;
    task.metadata = { ...(task.metadata ?? {}), delegationId: delegation.id };

    return HttpResponse.json({ data: task });
  }),
  http.get("/api/approval-policies", () => HttpResponse.json({ data: policies })),
  http.get("/api/approval-policies/:policyId", ({ params }) => {
    const policy = policies.find((item) => item.id === params.policyId);
    if (!policy) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    return HttpResponse.json({ data: policy });
  }),
  http.post("/api/approval-policies/preview", async ({ request }) => {
    const body = (await request.json()) as {
      context?: { requisitionId?: string; subjectType?: string };
    };
    const preview =
      body.context?.subjectType === "rfq_award_recommendation"
        ? awardApprovalPreviewFixture
        : previewForRequisition(body.context?.requisitionId);

    return HttpResponse.json({ data: preview });
  }),
  http.post("/api/approval-policies", async ({ request }) => {
    const body = (await request.json()) as ApprovalPolicyPayload;
    const policy: ApprovalPolicy = {
      ...structuredClone(approvalPolicyFixture),
      id: `ap-${policies.length + 101}`,
      name: body.name ?? "Untitled policy",
      description: body.description ?? "",
      subjectType: body.subjectType ?? "requisition",
      versions: [
        {
          ...structuredClone(approvalPolicyFixture.versions[0]!),
          id: `apv-${policies.length + 101}`,
          policyId: `ap-${policies.length + 101}`,
          rules: body.rules ?? [],
          routeTemplate:
            body.routeTemplate ??
            structuredClone(approvalPolicyFixture.versions[0]!.routeTemplate),
          slaRules: body.slaRules ?? [],
        },
      ],
    };
    policies.push(policy);
    return HttpResponse.json({ data: policy }, { status: 201 });
  }),
  http.patch("/api/approval-policies/:policyId", async ({ params, request }) => {
    const body = (await request.json()) as Partial<ApprovalPolicy>;
    const index = policies.findIndex((item) => item.id === params.policyId);
    if (index === -1) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    policies[index] = { ...policies[index]!, ...body };
    return HttpResponse.json({ data: policies[index] });
  }),
  http.post("/api/approval-policies/:policyId/versions", async ({ params, request }) => {
    const policy = policies.find((item) => item.id === params.policyId);
    if (!policy) return HttpResponse.json({ message: "Not found" }, { status: 404 });

    const body = (await request.json()) as ApprovalPolicyPayload;
    const nextVersionNumber =
      Math.max(0, ...policy.versions.map((version) => version.versionNumber)) + 1;
    const version: ApprovalPolicy["versions"][number] = {
      ...structuredClone(approvalPolicyFixture.versions[0]!),
      id: `apv-${policy.id}-${nextVersionNumber}`,
      tenantId: policy.tenantId,
      policyId: policy.id,
      versionNumber: nextVersionNumber,
      status: "draft",
      priority: body.priority ?? 100,
      rules: body.rules ?? [],
      routeTemplate:
        body.routeTemplate ?? structuredClone(approvalPolicyFixture.versions[0]!.routeTemplate),
      slaRules: body.slaRules ?? [],
      publishedById: null,
      publishedAt: null,
      createdAt: new Date("2026-05-17T00:00:00.000Z").toISOString(),
      updatedAt: new Date("2026-05-17T00:00:00.000Z").toISOString(),
    };

    policy.versions.unshift(version);
    return HttpResponse.json({ data: version }, { status: 201 });
  }),
  http.post("/api/approval-policy-versions/:versionId/publish", ({ params }) => {
    const policy = policies.find((item) =>
      item.versions.some((version) => version.id === params.versionId),
    );
    const version = policy?.versions.find((item) => item.id === params.versionId);
    if (!policy || !version) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    policy.status = "active";
    version.status = "published";
    return HttpResponse.json({ data: version });
  }),
  http.post("/api/approval-policy-versions/:versionId/retire", ({ params }) => {
    const policy = policies.find((item) =>
      item.versions.some((version) => version.id === params.versionId),
    );
    const version = policy?.versions.find((item) => item.id === params.versionId);
    if (!policy || !version) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    version.status = "retired";
    version.effectiveUntil = new Date("2026-05-17T00:00:00.000Z").toISOString();
    if (policy.versions.every((item) => item.status !== "published")) {
      policy.status = "draft";
    }
    return HttpResponse.json({ data: version });
  }),
  http.get("/api/requisitions/:requisitionId/approval-preview", ({ params }) => {
    const requisitionStatus =
      requisitionFixtures.find((item) => item.id === params.requisitionId)?.status ?? "draft";
    const preview =
      requisitionStatus === "submitted"
        ? submittedRequisitionApprovalPreviewFixture
        : previewForRequisition(String(params.requisitionId));

    return HttpResponse.json({ data: preview });
  }),
  http.get("/api/requisitions/:requisitionId/approval-summary", ({ params }) => {
    if (params.requisitionId === "req-2") {
      return HttpResponse.json({ data: approvalSummaryFixture });
    }

    return HttpResponse.json({ data: null });
  }),
];

function previewForRequisition(requisitionId?: string) {
  if (requisitionId === "req-parallel-all") {
    return parallelAllApprovalPreviewFixture;
  }

  if (requisitionId === "req-parallel-any") {
    return parallelAnyApprovalPreviewFixture;
  }

  if (requisitionId === "req-fallback") {
    return fallbackApprovalPreviewFixture;
  }

  return approvalPreviewFixture;
}
