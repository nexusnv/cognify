import { http, HttpResponse } from "msw";
import {
  approvalSummaryFixture,
  approvalTaskFixtures,
  approvalPolicyFixture,
  approvalPreviewFixture,
  fallbackApprovalPreviewFixture,
  parallelAllApprovalPreviewFixture,
  parallelAnyApprovalPreviewFixture,
  submittedRequisitionApprovalPreviewFixture,
} from "./approval-fixtures";
import { requisitionFixtures } from "@/features/requisitions/mocks/requisitions-fixtures";
import type { ApprovalPolicy } from "../types/approval-view-model";

let policies: ApprovalPolicy[] = [structuredClone(approvalPolicyFixture)];
let tasks = structuredClone(approvalTaskFixtures);

type ApprovalPolicyPayload = Partial<ApprovalPolicy> &
  Pick<
    Partial<ApprovalPolicy["versions"][number]>,
    "priority" | "rules" | "routeTemplate" | "slaRules"
  >;

export function resetApprovalMockState() {
  policies = [structuredClone(approvalPolicyFixture)];
  tasks = structuredClone(approvalTaskFixtures);
}

export const approvalHandlers = [
  http.get("/api/approval-tasks", ({ request }) => {
    const url = new URL(request.url);
    const scope = url.searchParams.get("scope");
    const status = url.searchParams.get("status");
    const data = tasks.filter((task) => {
      const matchesStatus = !status || task.status === status;
      const matchesScope =
        !scope ||
        scope === "all" ||
        scope === "assigned_to_me" ||
        (scope === "completed_by_me" && task.decidedBy?.id === "user-2") ||
        (scope === "overdue" && task.dueAt !== null && task.dueAt < "2026-05-18") ||
        (scope === "due_soon" && task.status === "active");

      return matchesStatus && matchesScope;
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
  http.get("/api/approval-policies", () => HttpResponse.json({ data: policies })),
  http.get("/api/approval-policies/:policyId", ({ params }) => {
    const policy = policies.find((item) => item.id === params.policyId);
    if (!policy) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    return HttpResponse.json({ data: policy });
  }),
  http.post("/api/approval-policies/preview", async ({ request }) => {
    const body = (await request.json()) as { context?: { requisitionId?: string } };
    const preview = previewForRequisition(body.context?.requisitionId);

    return HttpResponse.json({ data: preview });
  }),
  http.post("/api/approval-policies", async ({ request }) => {
    const body = (await request.json()) as ApprovalPolicyPayload;
    const policy: ApprovalPolicy = {
      ...structuredClone(approvalPolicyFixture),
      id: `ap-${policies.length + 101}`,
      name: body.name ?? "Untitled policy",
      description: body.description ?? "",
      subjectType: "requisition",
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
