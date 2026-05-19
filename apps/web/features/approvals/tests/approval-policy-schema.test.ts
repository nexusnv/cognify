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

  it("rejects non-requisition subject types", () => {
    const parsed = approvalPolicySchema.safeParse({
      ...defaultApprovalPolicyValues,
      subjectType: "award",
    });

    expect(parsed.success).toBe(false);
  });
});
