import { z } from "zod";

export const requisitionRuleFields = [
  "amount",
  "department",
  "costCenter",
  "projectId",
  "riskClassification",
] as const;

export const awardRuleFields = [
  "recommendedAmount",
  "recommendedCurrency",
  "recommendedVendorId",
  "scorecardWeightedTotal",
  "riskClassification",
  "riskSummaryPresent",
  "exceptionSummaryPresent",
] as const;

const ruleFieldsBySubject = {
  requisition: requisitionRuleFields,
  rfq_award_recommendation: awardRuleFields,
} as const;

const approvalSubjectTypeSchema = z.enum(["requisition", "rfq_award_recommendation"]);

const approvalRuleSchema = z.object({
  field: z.string().min(1, "Rule field is required"),
  operator: z.enum(["equals", "in", "gte", "lte", "between"]),
  value: z.union([z.string(), z.number(), z.boolean(), z.array(z.unknown())]),
});

const approvalApproverSchema = z
  .object({
    type: z.enum(["role", "user"]),
    role: z.string().optional(),
    userId: z.string().optional(),
    label: z.string().optional(),
  })
  .superRefine((value, context) => {
    if (value.type === "role" && !value.role?.trim()) {
      context.addIssue({
        code: "custom",
        path: ["role"],
        message: "Role is required for role approvers",
      });
    }

    if (value.type === "user" && !value.userId?.trim()) {
      context.addIssue({
        code: "custom",
        path: ["userId"],
        message: "User ID is required for user approvers",
      });
    }
  });

const approvalStageSchema = z.object({
  name: z.string().min(1, "Stage name is required"),
  completionRule: z.enum(["all", "any"], {
    error: "Completion rule must be all or any",
  }),
  approvers: z.array(approvalApproverSchema).min(1, "At least one approver is required"),
  fallbackApprovers: z.array(approvalApproverSchema).default([]),
});

export const approvalPolicySchema = z
  .object({
    name: z.string().min(1, "Policy name is required").max(255),
    description: z.string().max(5000).optional().default(""),
    subjectType: approvalSubjectTypeSchema,
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
    const allowedStages = new Set(value.routeTemplate.stages.map((stage) => stage.name));

    value.slaRules.forEach((rule, index) => {
      if (!allowedStages.has(rule.stage)) {
        context.addIssue({
          code: "custom",
          path: ["slaRules", index, "stage"],
          message: `${rule.stage} is not a valid stage for ${value.subjectType} policies`,
        });
      }

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

    const supportedFields = new Set<string>(ruleFieldsBySubject[value.subjectType]);
    value.rules.forEach((rule, index) => {
      if (!supportedFields.has(rule.field)) {
        context.addIssue({
          code: "custom",
          path: ["rules", index, "field"],
          message: `${rule.field} is not available for ${value.subjectType} policies`,
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
        fallbackApprovers: [{ type: "role", role: "admin", label: "Admin fallback" }],
      },
    ],
  },
  slaRules: [{ stage: "Manager review", dueInHours: 48, escalateAfterHours: 72 }],
};
