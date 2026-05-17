import type { ApprovalPolicy } from "../types/approval-view-model";

export const approvalPolicyFixture: ApprovalPolicy = {
  id: "ap-100",
  tenantId: "2",
  name: "Standard requisition approval",
  description: "Default requisition approval route.",
  subjectType: "requisition",
  status: "draft",
  versions: [
    {
      id: "apv-100",
      tenantId: "2",
      policyId: "ap-100",
      versionNumber: 1,
      status: "draft",
      priority: 100,
      effectiveFrom: null,
      effectiveUntil: null,
      rules: [{ field: "amount", operator: "gte", value: 1000 }],
      routeTemplate: {
        stages: [
          {
            name: "Manager review",
            completionRule: "all",
            approvers: [{ type: "role", role: "approver", label: "Approver" }],
          },
        ],
      },
      slaRules: [{ stage: "Manager review", dueInHours: 48, escalateAfterHours: 72 }],
      publishedById: null,
      publishedAt: null,
      createdAt: "2026-05-17T00:00:00.000000Z",
      updatedAt: "2026-05-17T00:00:00.000000Z",
    },
  ],
  createdAt: "2026-05-17T00:00:00.000000Z",
  updatedAt: "2026-05-17T00:00:00.000000Z",
};
