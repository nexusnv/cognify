import { z } from "zod";

export const sourcingIntakeChecklistItemSchema = z.object({
  key: z.string().min(1),
  label: z.string().min(1),
  complete: z.boolean(),
});

export const sourcingIntakeReviewFormSchema = z.object({
  category: z.string().max(255).optional().nullable(),
  subcategory: z.string().max(255).optional().nullable(),
  urgency: z.enum(["low", "standard", "urgent"]).optional().nullable(),
  complexity: z.enum(["low", "medium", "high"]).optional().nullable(),
  targetDecisionDate: z.string().optional().nullable(),
  checklist: z.array(sourcingIntakeChecklistItemSchema),
  internalNotes: z.string().max(5000).optional().nullable(),
});

export const sourcingIntakeDecisionSchema = z.object({
  sourcingPath: z.enum(["needs_rfq", "needs_clarification", "direct_award", "no_sourcing_required"]),
  decisionReason: z.string().min(10, "Decision reason must be at least 10 characters"),
  clarificationMessage: z.string().optional().nullable(),
  clarificationFields: z.array(z.string()).optional(),
});

export type SourcingIntakeReviewFormValues = z.infer<typeof sourcingIntakeReviewFormSchema>;
export type SourcingIntakeDecisionValues = z.infer<typeof sourcingIntakeDecisionSchema>;
