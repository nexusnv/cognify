import { z } from "zod";

export const rfqRequiredDocumentSchema = z.object({
  key: z.string().min(1).max(80),
  label: z.string().min(1).max(160),
  required: z.boolean(),
});

export const rfqUpdateLineItemSchema = z.object({
  description: z.string().min(1).max(255),
  quantity: z.coerce.number().positive(),
  unit: z.string().min(1).max(40),
  notes: z.string().max(1000).nullable().optional(),
});

export const rfqDraftFormSchema = z.object({
  title: z.string().min(3).max(255),
  scopeSummary: z.string().max(5000).nullable(),
  responseDueAt: z.string().nullable(),
  responseInstructions: z.string().max(5000).nullable(),
  requiredDocuments: z.array(rfqRequiredDocumentSchema).max(20),
  lineItems: z.array(rfqUpdateLineItemSchema).max(100),
  evaluationNotes: z.string().max(5000).nullable(),
  internalNotes: z.string().max(5000).nullable(),
});

export const rfqCancelSchema = z.object({
  cancelReason: z.string().min(5).max(1000),
});

export type RfqDraftFormValues = z.infer<typeof rfqDraftFormSchema>;
export type RfqCancelValues = z.infer<typeof rfqCancelSchema>;
