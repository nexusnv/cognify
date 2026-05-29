import { describe, expect, it } from "vitest";
import { approvalPolicySchema, defaultApprovalPolicyValues } from "../schemas/approval-policy-schema";

describe("approvalPolicySchema", () => {
  it("accepts a valid requisition approval policy", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      name: "Standard requisition approval",
    });

    expect(parsed.success).toBe(true);
  });

  it("rejects missing policy names", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      name: "",
    });

    expect(parsed.success).toBe(false);
  });

  it("rejects empty stages", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      routeTemplate: { stages: [] },
    });

    expect(parsed.success).toBe(false);
  });

  it("rejects invalid completion rules", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      routeTemplate: {
        stages: [
          {
            name: "Manager review",
            completionRule: "majority",
            approvers: [{ type: "role", role: "approver" }],
          },
        ],
      },
    });

    expect(parsed.success).toBe(false);
  });

  it("rejects escalation earlier than due date", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      slaRules: [{ stage: "Manager review", dueInHours: 48, escalateAfterHours: 24 }],
    });

    expect(parsed.success).toBe(false);
  });

  it("rejects SLA rules that reference unknown route stages", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      slaRules: [{ stage: "Finance review", dueInHours: 48, escalateAfterHours: 72 }],
    });

    expect(parsed.success).toBe(false);
  });

  it("accepts award recommendation approval policies with fallback approvers", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      name: "Award recommendation approval",
      subjectType: "rfq_award_recommendation",
      rules: [
        { field: "recommendedAmount", operator: "gte", value: 10000 },
        { field: "riskClassification", operator: "equals", value: "high" },
      ],
      routeTemplate: {
        stages: [
          {
            name: "Commercial review",
            completionRule: "all",
            approvers: [{ type: "role", role: "buyer", label: "Buyer" }],
            fallbackApprovers: [{ type: "role", role: "admin", label: "Admin" }],
          },
        ],
      },
      slaRules: [{ stage: "Commercial review", dueInHours: 24, escalateAfterHours: 36 }],
    });

    expect(parsed.success).toBe(true);
  });

  it("rejects requisition fields on award policies", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      name: "Award recommendation approval",
      subjectType: "rfq_award_recommendation",
      rules: [{ field: "department", operator: "equals", value: "Operations" }],
    });

    expect(parsed.success).toBe(false);
  });

  it("rejects award fields on requisition policies", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      rules: [{ field: "recommendedAmount", operator: "gte", value: 10000 }],
    });

    expect(parsed.success).toBe(false);
  });

  it("rejects fallback approvers without required role or user IDs", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      routeTemplate: {
        stages: [
          {
            name: "Manager review",
            completionRule: "all",
            approvers: [{ type: "role", role: "approver" }],
            fallbackApprovers: [{ type: "role", role: "" }],
          },
        ],
      },
    });

    expect(parsed.success).toBe(false);
  });

  it("rejects unsupported subject types", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      subjectType: "award",
    });

    expect(parsed.success).toBe(false);
  });
});
