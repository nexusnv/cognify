import { z } from "zod";

const approvalRuleSchema = z.object({
  field: z.string().min(1, "Rule field is required"),
  operator: z.enum(["equals", "in", "gte", "lte", "between"]),
  value: z.union([z.string(), z.number(), z.boolean(), z.array(z.unknown())]),
});

const approvalStageSchema = z.object({
  name: z.string().min(1, "Stage name is required"),
  completionRule: z.enum(["all", "any"], {
    error: "Completion rule must be all or any",
  }),
  approvers: z
    .array(
      z.object({
        type: z.string().min(1),
        role: z.string().optional(),
        userId: z.string().optional(),
        label: z.string().optional(),
      }),
    )
    .min(1, "At least one approver is required"),
});

export const approvalPolicySchema = z
  .object({
    name: z.string().min(1, "Policy name is required").max(255),
    description: z.string().max(5000).optional().default(""),
    subjectType: z.literal("requisition", {
      error: "Only requisition policies are supported",
    }),
    rules: z.array(approvalRuleSchema).default([]),
    routeTemplate: z.object({
      stages: z.array(approvalStageSchema).min(1, "At least one approval stage is required"),
    }),
    slaRules: z
      .array(
        z.object({
          stage: z.string().min(1, "SLA stage is required"),
          dueInHours: z.number().int().positive("Due duration must be positive"),
          escalateAfterHours: z
            .number()
            .int()
            .positive("Escalation duration must be positive")
            .optional(),
        }),
      )
      .default([]),
  })
  .superRefine((value, context) => {
    value.slaRules.forEach((rule, index) => {
      if (
        rule.escalateAfterHours !== undefined &&
        rule.escalateAfterHours < rule.dueInHours
      ) {
        context.addIssue({
          code: "custom",
          path: ["slaRules", index, "escalateAfterHours"],
          message: "Escalation cannot occur before the due date",
        });
      }
    });
  });

export type ApprovalPolicySchemaValues = z.infer<typeof approvalPolicySchema>;
export type ApprovalPolicySchemaInput = z.input<typeof approvalPolicySchema>;

export const defaultApprovalPolicyValues: ApprovalPolicySchemaValues = {
  name: "",
  description: "",
  subjectType: "requisition",
  rules: [],
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
};
