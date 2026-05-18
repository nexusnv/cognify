import type {
  ApprovalPolicy,
  ApprovalPreview,
  ApprovalPreviewContext,
} from "../types/approval-view-model";

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

export const approvalPreviewContextFixture: ApprovalPreviewContext = {
  requisitionId: "req-2",
  requesterId: "user-1",
  amount: 3400,
  currency: "MYR",
  department: "Operations",
  costCenter: "OPS-220",
  projectId: null,
  lineItemCategories: ["Packing box bundle"],
  riskClassification: "medium",
  vendorId: "vendor-1",
};

export const approvalPreviewFixture: ApprovalPreview = {
  matchedPolicy: {
    id: approvalPolicyFixture.id,
    tenantId: approvalPolicyFixture.tenantId,
    name: approvalPolicyFixture.name,
    subjectType: approvalPolicyFixture.subjectType,
    status: approvalPolicyFixture.status,
  },
  matchedVersion: {
    id: approvalPolicyFixture.versions[0].id,
    tenantId: approvalPolicyFixture.tenantId,
    policyId: approvalPolicyFixture.id,
    versionNumber: approvalPolicyFixture.versions[0].versionNumber,
    status: approvalPolicyFixture.versions[0].status,
    priority: approvalPolicyFixture.versions[0].priority,
    rules: approvalPolicyFixture.versions[0].rules,
    routeTemplate: approvalPolicyFixture.versions[0].routeTemplate,
    slaRules: approvalPolicyFixture.versions[0].slaRules,
  },
  matchedConditions: [
    {
      field: "amount",
      operator: "gte",
      value: 1000,
      matched: true,
      summary: "amount gte 1000 matched",
    },
  ],
  stages: [
    {
      name: "Manager review",
      completionRule: "all",
      approvers: [{ type: "role", role: "approver", label: "Approver" }],
      fallbackApprovers: [{ type: "role", role: "buyer", label: "Buyer fallback" }],
      dueAt: "2026-05-19T00:00:00.000Z",
      warnings: [],
    },
  ],
  warnings: [
    {
      code: "missing_context",
      message: "Missing required approval context: riskClassification, vendorId",
    },
  ],
  estimatedDueAt: "2026-05-19T00:00:00.000Z",
  createsTasks: false,
};

export const submittedRequisitionApprovalPreviewFixture: ApprovalPreview = {
  ...approvalPreviewFixture,
  matchedConditions: [
    {
      field: "department",
      operator: "equals",
      value: "Operations",
      matched: true,
      summary: "department equals Operations matched",
    },
  ],
  warnings: [],
};

export const fallbackApprovalPreviewFixture: ApprovalPreview = {
  ...approvalPreviewFixture,
  matchedPolicy: {
    ...approvalPreviewFixture.matchedPolicy,
    id: "ap-fallback",
    name: "Fallback buyer review",
  },
  matchedVersion: {
    ...approvalPreviewFixture.matchedVersion,
    id: "apv-fallback",
    versionNumber: 2,
  },
  stages: [
    {
      name: "Fallback buyer review",
      completionRule: "all",
      approvers: [{ type: "role", role: "buyer", label: "Buyer fallback" }],
      fallbackApprovers: [{ type: "role", role: "admin", label: "Admin fallback" }],
      dueAt: "2026-05-20T00:00:00.000Z",
      warnings: [],
    },
  ],
  warnings: [],
  estimatedDueAt: "2026-05-20T00:00:00.000Z",
};
